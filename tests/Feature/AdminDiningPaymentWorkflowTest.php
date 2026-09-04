<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDiningPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_show_renders_payment_forms_as_html_not_escaped_text(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('administrator.dining-sessions.show', $session))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<form method="POST"', $html);
        $this->assertStringNotContainsString('&lt;form method=&quot;POST&quot;', $html);
        $this->assertStringContainsString('Set method', $html);
        $this->assertStringContainsString('Reopen', $html);
        $this->assertStringContainsString('Close', $html);
    }

    public function test_admin_show_displays_utr_and_verify_reject_while_awaiting_review(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);

        app(DiningSessionServiceInterface::class)->submitPaymentTransactionId(
            $session,
            $customer,
            'ADMINUTR123456',
        );

        $html = $this->actingAs($admin, 'admin')
            ->get(route('administrator.dining-sessions.show', $session->fresh()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Verification Pending', $html);
        $this->assertStringContainsString('ADMINUTR123456', $html);
        $this->assertStringContainsString('Verify Payment', $html);
        $this->assertStringContainsString('Reject / Not Found', $html);
        $this->assertStringNotContainsString('Set method', $html);
    }

    public function test_admin_verify_closes_session_releases_table_and_is_idempotent(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);
        $table = $session->cafeTable;
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->submitPaymentTransactionId($session, $customer, 'CLOSEUTR123456');
        $session = $dining->confirmPayment($session->fresh(), $admin);

        $this->assertSame(PaymentStatus::Confirmed, $session->payment_status);
        $this->assertSame(DiningSessionStatus::Closed, $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertNull($dining->findActiveForCustomer($customer));
        $this->assertNull($dining->findActiveForTable($table));

        $tableState = $dining->tableOperationalStates()
            ->first(static fn (array $row): bool => (int) $row['table']->id === (int) $table->id);
        $this->assertSame('available', $tableState['state'] ?? null);

        $closedAt = $session->closed_at;
        $again = $dining->confirmPayment($session->fresh(), $admin);

        $this->assertSame(DiningSessionStatus::Closed, $again->status);
        $this->assertTrue($closedAt?->equalTo($again->closed_at));

        try {
            $dining->placeRound($again, $customer);
            $this->fail('Expected new rounds to be blocked after payment close.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('session', $e->errors());
        }

        try {
            $dining->submitPaymentTransactionId($again->fresh(), $customer, 'AFTERPAIDUTR001');
            $this->fail('Expected payment resubmission to be blocked after paid close.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transaction_id', $e->errors());
        }

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.dining.sessions.active'))
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->getJson(route('api.v1.dining.sessions.show', $again))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::Closed->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Confirmed->value)
            ->assertJsonPath('data.capabilities.can_add_rounds', false)
            ->assertJsonPath('data.capabilities.can_pay', false)
            ->assertJsonPath('data.capabilities.can_request_bill', false)
            ->assertJsonPath('data.capabilities.can_submit_transaction_id', false);
    }

    public function test_reject_and_awaiting_review_do_not_close_or_release_table(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);
        $table = $session->cafeTable;
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->submitPaymentTransactionId($session, $customer, 'PENDINGUTR111');

        $this->assertSame(PaymentStatus::AwaitingReview, $session->payment_status);
        $this->assertNotSame(DiningSessionStatus::Closed, $session->status);
        $this->assertNotNull($dining->findActiveForTable($table));

        $dining->rejectPaymentProof($session->fresh(), $admin, 'Not found');

        $session = $session->fresh();
        $this->assertSame(PaymentStatus::Rejected, $session->payment_status);
        $this->assertNotSame(DiningSessionStatus::Closed, $session->status);
        $this->assertNull($session->closed_at);
        $this->assertNotNull($dining->findActiveForTable($table));
    }

    public function test_cash_pending_confirmation_does_not_close_session(): void
    {
        $this->enableDiningPayments();

        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->setPaymentMethod($session, 'cash');

        $this->assertSame(PaymentMethod::Cash, $session->payment_method);
        $this->assertNotSame(PaymentStatus::Confirmed, $session->payment_status);
        $this->assertNotSame(DiningSessionStatus::Closed, $session->status);
        $this->assertNull($session->closed_at);
        $this->assertNotNull($dining->findActiveForCustomer($customer));
    }

    public function test_admin_verify_marks_paid_and_hides_verification_actions(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->submitPaymentTransactionId($session, $customer, 'VERIFYUTR654321');

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.dining-sessions.payment.confirm', $session))
            ->assertRedirect();

        $session = $session->fresh();
        $this->assertSame(PaymentStatus::Confirmed, $session->payment_status);
        $this->assertSame(DiningSessionStatus::Closed, $session->status);
        $this->assertNotNull($session->closed_at);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('administrator.dining-sessions.show', $session))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Verify Payment', $html);
        $this->assertStringNotContainsString('Reject / Not Found', $html);
        $this->assertStringContainsString('VERIFYUTR654321', $html);
    }

    public function test_admin_reject_requires_reason_and_allows_customer_resubmit(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->submitPaymentTransactionId($session, $customer, 'REJECTUTR111111');

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.dining-sessions.payment-proof.reject', $session), [])
            ->assertSessionHasErrors('notes');

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.dining-sessions.payment-proof.reject', $session), [
                'notes' => 'Transaction not found',
            ])
            ->assertRedirect();

        $session = $session->fresh();
        $this->assertSame(PaymentStatus::Rejected, $session->payment_status);
        $this->assertSame('REJECTUTR111111', $session->payment_reference);
        $this->assertSame('Transaction not found', $session->payment_proof_rejection_notes);
        $this->assertTrue($session->canSubmitManualPaymentEvidence());
        $this->assertTrue($session->canResubmitManualPaymentEvidence());

        $session = $dining->submitPaymentTransactionId($session, $customer, 'REJECTUTR222222');

        $this->assertSame(PaymentStatus::AwaitingReview, $session->payment_status);
        $this->assertSame('REJECTUTR222222', $session->payment_reference);
        $this->assertFalse($session->canSubmitManualPaymentEvidence());
        $this->assertFalse($session->canResubmitManualPaymentEvidence());
    }

    public function test_second_utr_submission_while_awaiting_review_is_rejected(): void
    {
        $this->enableDiningPayments();

        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.dining.sessions.payment-proof', $session), [
            'transaction_id' => 'FIRSTUTR123456',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::AwaitingReview->value)
            ->assertJsonPath('data.capabilities.can_submit_transaction_id', false)
            ->assertJsonPath('data.capabilities.can_resubmit_transaction_id', false);

        $this->postJson(route('api.v1.dining.sessions.payment-proof', $session->fresh()), [
            'transaction_id' => 'SECONDUTR654321',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_id']);

        $this->assertSame('FIRSTUTR123456', $session->fresh()->payment_reference);
    }

    public function test_payment_method_cannot_change_after_utr_submission(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->submitPaymentTransactionId($session, $customer, 'LOCKMETHOD123');

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.dining-sessions.payment-method', $session), [
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(PaymentMethod::Manual, $session->fresh()->payment_method);
        $this->assertSame(PaymentStatus::AwaitingReview, $session->fresh()->payment_status);
    }

    public function test_paid_session_blocks_resubmit_capabilities(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->submitPaymentTransactionId($session, $customer, 'PAIDUTR999999');
        $dining->confirmPayment($session->fresh(), $admin);

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.dining.sessions.show', $session->fresh()))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::Closed->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Confirmed->value)
            ->assertJsonPath('data.capabilities.can_submit_transaction_id', false)
            ->assertJsonPath('data.capabilities.can_resubmit_transaction_id', false)
            ->assertJsonPath('data.capabilities.can_pay', false);
    }

    public function test_customer_api_capabilities_follow_utr_lifecycle(): void
    {
        $this->enableDiningPayments();

        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);
        $dining = app(DiningSessionServiceInterface::class);

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.dining.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.capabilities.can_submit_transaction_id', true)
            ->assertJsonPath('data.capabilities.can_resubmit_transaction_id', false);

        $this->postJson(route('api.v1.dining.sessions.payment-proof', $session), [
            'transaction_id' => 'CAPUTR111111',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::AwaitingReview->value)
            ->assertJsonPath('data.capabilities.can_submit_transaction_id', false)
            ->assertJsonPath('data.capabilities.can_resubmit_transaction_id', false)
            ->assertJsonPath('data.payment_status_label', 'Verification Pending');

        $dining->rejectPaymentProof($session->fresh(), $admin, 'Amount mismatch');

        $this->getJson(route('api.v1.dining.sessions.show', $session->fresh()))
            ->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::Rejected->value)
            ->assertJsonPath('data.payment_rejection_reason', 'Amount mismatch')
            ->assertJsonPath('data.capabilities.can_submit_transaction_id', true)
            ->assertJsonPath('data.capabilities.can_resubmit_transaction_id', true);
    }

    private function billReadySession(User $customer)
    {
        $dining = app(DiningSessionServiceInterface::class);
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('95.00');

        $session = $dining->startSession($table, $customer, $customer, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $customer);
        $dining->placeRound($session, $customer);
        $session = $dining->generateFinalBill($session->fresh(), $customer);
        $dining->setPaymentMethod($session, 'manual_upi');

        return $session->fresh(['cafeTable', 'customer', 'paymentReceivedBy', 'orders.items', 'promotions']);
    }

    private function makeVariant(string $price): ProductVariant
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
            'serving_size_value' => '250.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);
    }

    private function enableDiningPayments(): void
    {
        $this->putSetting(WebsiteSettingKey::FulfilmentDineInEnabled, '1');
        $this->putSetting(WebsiteSettingKey::OrderingManualClosed, '0');
        $this->putSetting(WebsiteSettingKey::PaymentManualUpiEnabled, '1');
        $this->putSetting(WebsiteSettingKey::PaymentCashEnabled, '1');
        $this->putSetting(WebsiteSettingKey::PaymentUpiId, 'cafe@upi');
        $this->putSetting(WebsiteSettingKey::TaxEnabled, '0');
        $this->putSetting(WebsiteSettingKey::TaxPercent, '0.00');
    }

    private function putSetting(WebsiteSettingKey $key, ?string $value): void
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
}
