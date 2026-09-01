<?php

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            WebsiteSettingKey::ReferralEnabled->value => '1',
            WebsiteSettingKey::ReferralRewardType->value => 'free_drink',
            WebsiteSettingKey::ReferralRewardProductId->value => null,
            WebsiteSettingKey::ReferralRewardVariantId->value => null,
            WebsiteSettingKey::ReferralRewardQuantity->value => '1',
            WebsiteSettingKey::ReferralCouponDiscountType->value => 'fixed',
            WebsiteSettingKey::ReferralCouponDiscountValue->value => '50.00',
            WebsiteSettingKey::ReferralCouponMaxDiscount->value => null,
            WebsiteSettingKey::ReferralCouponMinimumSubtotal->value => null,
            WebsiteSettingKey::ReferralMinimumQualifyingOrderAmount->value => null,
            WebsiteSettingKey::ReferralRewardRedemptionDurationDays->value => '30',
            WebsiteSettingKey::ReferralMaxRewardsPerCustomerMonth->value => null,
        ];

        foreach (WebsiteSettingKey::cases() as $key) {
            if ($key->section() !== 'referral') {
                continue;
            }

            WebsiteSetting::query()->firstOrCreate(
                ['key' => $key->value],
                [
                    'section' => $key->section(),
                    'value_type' => $key->valueType(),
                    'value' => $defaults[$key->value] ?? null,
                ],
            );
        }
    }

    public function down(): void
    {
        WebsiteSetting::query()
            ->whereIn('key', array_map(
                static fn (WebsiteSettingKey $key): string => $key->value,
                array_values(array_filter(
                    WebsiteSettingKey::cases(),
                    static fn (WebsiteSettingKey $key): bool => $key->section() === 'referral',
                )),
            ))
            ->delete();
    }
};
