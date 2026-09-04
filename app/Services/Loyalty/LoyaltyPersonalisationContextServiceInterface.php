<?php

namespace App\Services\Loyalty;

use App\Models\User;

interface LoyaltyPersonalisationContextServiceInterface
{
    /**
     * Request-scoped loyalty summary for targeting / campaigns / merchandising.
     *
     * Never mutates loyalty balances or ledger rows.
     * Guests and failed reads return a safe empty context (fail closed for loyalty rules).
     *
     * @param  array{
     *     available_now?: list<array<string, mixed>>,
     *     next_reward?: array<string, mixed>|null,
     *     recently_redeemed_rewards?: list<array<string, mixed>>,
     *     skip_discovery?: bool
     * }  $discovery
     * @return array<string, mixed>
     */
    public function forActor(?User $customer, array $discovery = []): array;

    /**
     * Customer-safe personalisation_summary subset (no ledger/admin internals).
     *
     * @param  array<string, mixed>  $signals
     * @return array<string, mixed>
     */
    public function toCustomerSafeSummary(array $signals): array;

    /**
     * Deterministic points band for display/targeting balances.
     */
    public function pointsBand(int $displayPoints): string;
}
