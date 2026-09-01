<?php

namespace Database\Seeders;

use App\Enums\CustomerRewardStatus;
use App\Enums\CustomerRewardType;
use App\Enums\ReferralStatus;
use App\Enums\WebsiteSettingKey;
use App\Models\CustomerReferral;
use App\Models\CustomerReward;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Referral\ReferralServiceInterface;
use Illuminate\Database\Seeder;

/**
 * Local/testing referral demo data only.
 */
class DemoReferralSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $variant = ProductVariant::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true)->where('is_available', true))
            ->with('product')
            ->orderBy('id')
            ->first();

        if ($variant?->product instanceof Product) {
            WebsiteSetting::query()->updateOrCreate(
                ['key' => WebsiteSettingKey::ReferralEnabled->value],
                [
                    'section' => WebsiteSettingKey::ReferralEnabled->section(),
                    'value_type' => WebsiteSettingKey::ReferralEnabled->valueType(),
                    'value' => '1',
                ],
            );
            WebsiteSetting::query()->updateOrCreate(
                ['key' => WebsiteSettingKey::ReferralRewardType->value],
                [
                    'section' => WebsiteSettingKey::ReferralRewardType->section(),
                    'value_type' => WebsiteSettingKey::ReferralRewardType->valueType(),
                    'value' => 'free_drink',
                ],
            );
            WebsiteSetting::query()->updateOrCreate(
                ['key' => WebsiteSettingKey::ReferralRewardProductId->value],
                [
                    'section' => WebsiteSettingKey::ReferralRewardProductId->section(),
                    'value_type' => WebsiteSettingKey::ReferralRewardProductId->valueType(),
                    'value' => (string) $variant->product_id,
                ],
            );
            WebsiteSetting::query()->updateOrCreate(
                ['key' => WebsiteSettingKey::ReferralRewardVariantId->value],
                [
                    'section' => WebsiteSettingKey::ReferralRewardVariantId->section(),
                    'value_type' => WebsiteSettingKey::ReferralRewardVariantId->valueType(),
                    'value' => (string) $variant->getKey(),
                ],
            );
            WebsiteSetting::query()->updateOrCreate(
                ['key' => WebsiteSettingKey::ReferralRewardQuantity->value],
                [
                    'section' => WebsiteSettingKey::ReferralRewardQuantity->section(),
                    'value_type' => WebsiteSettingKey::ReferralRewardQuantity->valueType(),
                    'value' => '1',
                ],
            );
            WebsiteSetting::query()->updateOrCreate(
                ['key' => WebsiteSettingKey::ReferralRewardRedemptionDurationDays->value],
                [
                    'section' => WebsiteSettingKey::ReferralRewardRedemptionDurationDays->section(),
                    'value_type' => WebsiteSettingKey::ReferralRewardRedemptionDurationDays->valueType(),
                    'value' => '30',
                ],
            );
        }

        $referrals = app(ReferralServiceInterface::class);

        $priya = User::query()->where('email', 'priya@example.com')->first();
        $arjun = User::query()->where('email', 'arjun@example.com')->first();

        if ($priya === null) {
            return;
        }

        $code = $referrals->ensureCustomerReferralCode($priya);

        if ($arjun !== null && ! CustomerReferral::query()->where('referred_user_id', $arjun->getKey())->exists()) {
            $arjun->forceFill(['referred_by_user_id' => $priya->getKey()])->save();

            $referral = CustomerReferral::query()->create([
                'referrer_user_id' => $priya->getKey(),
                'referred_user_id' => $arjun->getKey(),
                'referral_code_snapshot' => $code,
                'status' => ReferralStatus::Rewarded,
                'qualified_at' => now()->subDays(2),
            ]);

            if ($variant?->product instanceof Product) {
                CustomerReward::query()->create([
                    'user_id' => $priya->getKey(),
                    'source_type' => 'referral',
                    'source_referral_id' => $referral->getKey(),
                    'reward_type' => CustomerRewardType::FreeDrink,
                    'status' => CustomerRewardStatus::Available,
                    'earned_at' => now()->subDays(2),
                    'expires_at' => now()->addDays(28),
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->getKey(),
                    'product_name_snapshot' => $variant->product->name,
                    'variant_name_snapshot' => $variant->name,
                    'quantity' => 1,
                ]);
            }
        }
    }
}
