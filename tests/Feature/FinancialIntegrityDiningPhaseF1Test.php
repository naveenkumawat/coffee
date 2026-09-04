<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\ReferralStatus;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\CustomerReferral;
use App\Models\CustomerReward;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Invoice\DiningInvoiceServiceInterface;
use App\Services\Referral\ReferralServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialIntegrityDiningPhaseF1Test extends TestCase
{
    use RefreshDatabase;

    public function test_multi_round_dining_promotion_applies_once_at_final_bill(): void
    {
        $this->enableDining();
        $this->setTax(enabled: false, percent: '0.00');

        Promotion::factory()->automatic()->dineIn()->percentage(10)->create([
            'name' => 'Dine 10',
        ]);

        $dining = app(DiningSessionServiceInterface::class);
        $waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('100.00');

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 2]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $round1 = $dining->placeRound($session, $waiter);
        $dining->addDraftItem($session->fresh(), (int) $variant->id, 1, $waiter);
        $round2 = $dining->placeRound($session->fresh(), $waiter);

        $this->assertSame('0.00', (string) $round1->discount_total);
        $this->assertSame('0.00', (string) $round2->discount_total);

        $session = $dining->generateFinalBill($session->fresh(), $waiter);

        $this->assertSame('200.00', (string) $session->subtotal_amount);
        $this->assertSame('20.00', (string) $session->discount_amount);
        $this->assertSame('180.00', (string) $session->total_amount);
        $this->assertSame(1, $session->promotions()->count());
    }

    public function test_final_dining_snapshot_immutable_after_gst_setting_change(): void
    {
        $this->enableDining();
        $this->setTax(enabled: true, percent: '5.00', inclusive: false);

        $dining = app(DiningSessionServiceInterface::class);
        $waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('100.00');

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);
        $session = $dining->generateFinalBill($session->fresh(), $waiter);

        $snapTotal = (string) $session->total_amount;
        $snapTax = (string) $session->tax_amount;

        $this->setTax(enabled: true, percent: '18.00', inclusive: false);

        $display = $dining->displayBill($session->fresh());
        $this->assertTrue($display['finalized']);
        $this->assertSame($snapTotal, $display['total']);
        $this->assertSame($snapTax, $display['tax']);

        $pdf = app(DiningInvoiceServiceInterface::class)->downloadPdf($session->fresh());
        $this->assertSame(200, $pdf->getStatusCode());
        $this->assertSame($snapTotal, (string) $session->fresh()->total_amount);
    }

    public function test_dining_pdf_total_matches_session_total_amount(): void
    {
        $this->enableDining();
        $this->setTax(enabled: false, percent: '0.00');

        $dining = app(DiningSessionServiceInterface::class);
        $waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('42.50');

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 2, $waiter);
        $dining->placeRound($session, $waiter);
        $session = $dining->generateFinalBill($session->fresh(), $waiter);

        $bill = $dining->finalizedBill($session);
        $this->assertSame((string) $session->total_amount, $bill['total']);
        $this->assertSame('85.00', $bill['total']);
    }

    public function test_operator_and_admin_can_confirm_and_reject_dining_upi_but_waiter_cannot(): void
    {
        Storage::fake('local');
        $this->enableDining();
        $this->setTax(enabled: false, percent: '0.00');

        $dining = app(DiningSessionServiceInterface::class);
        $waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $operator = User::factory()->create(['role' => UserRole::Operator, 'is_active' => true]);
        $admin = User::factory()->manager()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('10.00');

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);
        $session = $dining->generateFinalBill($session->fresh(), $waiter);
        $dining->changePaymentMethod($session, 'manual_upi', $operator);
        $session = $dining->uploadPaymentProof(
            $session->fresh(),
            $waiter,
            UploadedFile::fake()->image('proof.jpg'),
        );

        $this->actingAs($waiter, 'admin')
            ->post(route('operator.dining-sessions.payment.confirm', $session))
            ->assertForbidden();

        $this->actingAs($operator, 'admin')
            ->post(route('operator.dining-sessions.payment-proof.reject', $session), [
                'notes' => 'Blurry',
            ])
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Rejected, $session->fresh()->payment_status);

        $session = $dining->uploadPaymentProof(
            $session->fresh(),
            $waiter,
            UploadedFile::fake()->image('proof2.jpg'),
        );

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.dining-sessions.payment.confirm', $session))
            ->assertRedirect();

        $this->assertSame(DiningSessionStatus::Closed, $session->fresh()->status);
        $this->assertSame(PaymentStatus::Confirmed, $session->fresh()->payment_status);
    }

    public function test_upi_cannot_silently_become_cash_and_method_immutable_after_confirm(): void
    {
        $this->enableDining();
        $this->setTax(enabled: false, percent: '0.00');

        $dining = app(DiningSessionServiceInterface::class);
        $waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('10.00');

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);
        $session = $dining->generateFinalBill($session->fresh(), $waiter);
        $dining->changePaymentMethod($session, 'manual_upi', $waiter);

        try {
            $dining->markCashReceived($session->fresh(), $waiter);
            $this->fail('Expected cash receive to fail for UPI session.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment', $e->errors());
        }

        $dining->changePaymentMethod($session->fresh(), 'cash', $waiter);
        $session = $dining->markCashReceived($session->fresh(), $waiter);
        $this->assertSame(PaymentMethod::Cash, $session->payment_method);

        try {
            $dining->changePaymentMethod($session->fresh(), 'manual_upi', $waiter);
            $this->fail('Expected payment method change after confirm to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_method', $e->errors());
        }
    }

    public function test_dining_session_payment_qualifies_referral_once_not_per_round(): void
    {
        $this->enableDining();
        $this->enableReferrals();
        $this->setTax(enabled: false, percent: '0.00');

        $referrer = User::factory()->customer()->create();
        app(ReferralServiceInterface::class)->ensureCustomerReferralCode($referrer);
        $friend = User::factory()->customer()->create();
        CustomerReferral::query()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $friend->id,
            'referral_code_snapshot' => $referrer->referral_code,
            'status' => ReferralStatus::Registered,
        ]);

        $dining = app(DiningSessionServiceInterface::class);
        $waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('50.00');

        $session = $dining->startSession($table, $friend, $waiter, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $friend);
        $dining->placeRound($session, $waiter);
        $dining->addDraftItem($session->fresh(), (int) $variant->id, 1, $friend);
        $dining->placeRound($session->fresh(), $waiter);

        $this->assertSame(0, CustomerReward::query()->where('user_id', $referrer->id)->count());

        $session = $dining->generateFinalBill($session->fresh(), $waiter);
        $session = $dining->markCashReceived($session, $waiter);

        $this->assertSame(1, CustomerReward::query()->where('user_id', $referrer->id)->count());
        $this->assertSame(ReferralStatus::Rewarded, CustomerReferral::query()->where('referred_user_id', $friend->id)->first()->status);

        app(ReferralServiceInterface::class)->qualifyDiningSessionIfEligible($session->fresh());
        $this->assertSame(1, CustomerReward::query()->where('user_id', $referrer->id)->count());
    }

    public function test_anonymous_dining_does_not_qualify_referral(): void
    {
        $this->enableDining();
        $this->enableReferrals();
        $this->setTax(enabled: false, percent: '0.00');

        $dining = app(DiningSessionServiceInterface::class);
        $waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('50.00');

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);
        $session = $dining->generateFinalBill($session->fresh(), $waiter);
        $dining->markCashReceived($session, $waiter);

        $this->assertSame(0, CustomerReward::query()->count());
    }

    protected function enableDining(): void
    {
        $this->putSetting(WebsiteSettingKey::FulfilmentDineInEnabled, '1');
        $this->putSetting(WebsiteSettingKey::OrderingManualClosed, '0');
    }

    protected function enableReferrals(): void
    {
        $this->putSetting(WebsiteSettingKey::ReferralEnabled, '1');
        $this->putSetting(WebsiteSettingKey::ReferralRewardType, 'coupon');
        $this->putSetting(WebsiteSettingKey::ReferralCouponDiscountType, 'fixed');
        $this->putSetting(WebsiteSettingKey::ReferralCouponDiscountValue, '10.00');
        $this->putSetting(WebsiteSettingKey::ReferralRewardRedemptionDurationDays, '30');
    }

    protected function setTax(bool $enabled, string $percent, bool $inclusive = false): void
    {
        $this->putSetting(WebsiteSettingKey::TaxEnabled, $enabled ? '1' : '0');
        $this->putSetting(WebsiteSettingKey::TaxLabel, 'GST');
        $this->putSetting(WebsiteSettingKey::TaxPercent, $percent);
        $this->putSetting(WebsiteSettingKey::TaxInclusive, $inclusive ? '1' : '0');
    }

    protected function putSetting(WebsiteSettingKey $key, ?string $value): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => $value,
            ],
        );
    }

    protected function makeVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);
    }
}
