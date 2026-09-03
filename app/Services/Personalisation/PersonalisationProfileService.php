<?php

namespace App\Services\Personalisation;

use App\Jobs\RebuildPersonalisationProfileJob;
use App\Models\PersonalisationProfile;
use App\Models\User;
use App\Repositories\Personalisation\PersonalisationProfileRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class PersonalisationProfileService implements PersonalisationProfileServiceInterface
{
    public function __construct(
        protected PersonalisationProfileRepositoryInterface $profiles,
        protected PersonalisationProfileBuilder $builder,
    ) {}

    public function isBehaviourTrackingEnabled(): bool
    {
        return (bool) config('coffee.behaviour.enabled', true);
    }

    public function getForCustomer(int $customerId): ?PersonalisationProfile
    {
        return $this->profiles->findForCustomer($customerId);
    }

    public function getForVisitor(string $visitorKey): ?PersonalisationProfile
    {
        return $this->profiles->findForVisitor($visitorKey);
    }

    /**
     * Internal read contract for future recommendation/campaign engines.
     *
     * @return array<string, mixed>|null
     */
    public function profilePayloadForCustomer(int $customerId): ?array
    {
        $profile = $this->getForCustomer($customerId);

        return $profile ? $this->toEnginePayload($profile) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profilePayloadForVisitor(string $visitorKey): ?array
    {
        $profile = $this->getForVisitor($visitorKey);

        return $profile ? $this->toEnginePayload($profile) : null;
    }

    public function rebuildForCustomer(int $customerId): PersonalisationProfile
    {
        $lookbackDays = max(1, (int) config('coffee.behaviour.profile.lookback_days', 180));
        $since = now()->subDays($lookbackDays);

        $events = $this->isBehaviourTrackingEnabled()
            ? $this->profiles->behaviourEventsForCustomer($customerId, $since)
            : collect();

        // Canonical completed orders remain available even when optional tracking is disabled.
        $orders = $this->profiles->completedOrdersForCustomer($customerId, $since);

        $attributes = $this->builder->build($events, $orders);

        return $this->profiles->upsertCustomerProfile($customerId, $attributes);
    }

    public function rebuildForVisitor(string $visitorKey): PersonalisationProfile
    {
        $lookbackDays = max(1, (int) config('coffee.behaviour.profile.lookback_days', 180));
        $since = now()->subDays($lookbackDays);

        $events = $this->isBehaviourTrackingEnabled()
            ? $this->profiles->behaviourEventsForVisitor($visitorKey, $since)
            : collect();

        $attributes = $this->builder->build($events, collect());

        return $this->profiles->upsertVisitorProfile($visitorKey, $attributes);
    }

    public function resetForCustomer(int $customerId): bool
    {
        return $this->profiles->deleteForCustomer($customerId);
    }

    public function resetForVisitor(string $visitorKey): bool
    {
        return $this->profiles->deleteForVisitor($visitorKey);
    }

    /**
     * Queue a non-blocking rebuild. Never throws to callers.
     */
    public function dispatchRebuildForCustomer(int $customerId): void
    {
        try {
            RebuildPersonalisationProfileJob::dispatch(customerId: $customerId);
        } catch (Throwable $exception) {
            Log::warning('personalisation.dispatch_customer_failed', [
                'customer_id' => $customerId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function dispatchRebuildForVisitor(string $visitorKey): void
    {
        try {
            RebuildPersonalisationProfileJob::dispatch(visitorKey: $visitorKey);
        } catch (Throwable $exception) {
            Log::warning('personalisation.dispatch_visitor_failed', [
                'visitor_key' => $visitorKey,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * After a valid P2.1 visitor claim, rebuild the customer profile from
     * attached events + orders. Visitor-only profile row is cleared to avoid
     * duplicated derived state (source events now carry customer_id).
     */
    public function afterVisitorMerged(string $visitorKey, User $customer): void
    {
        try {
            $this->profiles->deleteForVisitor($visitorKey);
            $this->dispatchRebuildForCustomer((int) $customer->getKey());
        } catch (Throwable $exception) {
            Log::warning('personalisation.after_merge_failed', [
                'visitor_key' => $visitorKey,
                'customer_id' => $customer->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function rebuildStale(int $limit = 100): int
    {
        $hours = max(1, (int) config('coffee.behaviour.profile.stale_after_hours', 24));
        $staleBefore = now()->subHours($hours);
        $rebuilt = 0;

        foreach ($this->profiles->staleProfiles($staleBefore, $limit) as $profile) {
            try {
                if ($profile->customer_id) {
                    $this->rebuildForCustomer((int) $profile->customer_id);
                } elseif ($profile->visitor_key) {
                    $this->rebuildForVisitor((string) $profile->visitor_key);
                }
                $rebuilt++;
            } catch (Throwable $exception) {
                Log::warning('personalisation.stale_rebuild_failed', [
                    'profile_id' => $profile->getKey(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $rebuilt;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toEnginePayload(PersonalisationProfile $profile): array
    {
        return [
            'customer_id' => $profile->customer_id,
            'visitor_key' => $profile->visitor_key,
            'profile_version' => $profile->profile_version,
            'has_sufficient_evidence' => $profile->has_sufficient_evidence,
            'event_sample_count' => $profile->event_sample_count,
            'order_sample_count' => $profile->order_sample_count,
            'last_activity_at' => $profile->last_activity_at?->toIso8601String(),
            'calculated_at' => $profile->calculated_at?->toIso8601String(),
            'category_affinities' => $profile->category_affinities ?? [],
            'product_affinities' => $profile->product_affinities ?? [],
            'flavour_affinities' => $profile->flavour_affinities ?? [],
            'preferred_variants' => $profile->preferred_variants ?? [],
            'addon_preferences' => $profile->addon_preferences ?? [],
            'recent_product_ids' => $profile->recent_product_ids ?? [],
            'recent_category_ids' => $profile->recent_category_ids ?? [],
            'purchase_frequency' => $profile->purchase_frequency,
            'repeat_purchase_product_ids' => $profile->repeat_purchase_product_ids ?? [],
            'spend_band' => $profile->spend_band,
            'time_of_day_preferences' => $profile->time_of_day_preferences ?? [],
            'signals_meta' => $profile->signals_meta,
        ];
    }
}
