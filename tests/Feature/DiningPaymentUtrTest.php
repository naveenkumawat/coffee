<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiningPaymentUtrTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_submits_dining_utr_not_screenshot(): void
    {
        $this->enableDiningPayments();

        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);

        Sanctum::actingAs($customer);

        $response = $this->postJson(route('api.v1.dining.sessions.payment-proof', $session), [
            'transaction_id' => 'UTRDining123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::AwaitingReview->value)
            ->assertJsonPath('data.payment_transaction_id', 'UTRDining123456')
            ->assertJsonPath('data.payment_proof.transaction_id', 'UTRDining123456')
            ->assertJsonPath('data.payment_proof.has_screenshot', false);

        $this->assertDatabaseHas('dining_sessions', [
            'id' => $session->id,
            'payment_reference' => 'UTRDining123456',
            'payment_status' => PaymentStatus::AwaitingReview->value,
        ]);
    }

    public function test_customer_dining_payment_methods_come_from_catalog_meta(): void
    {
        $this->enableDiningPayments();

        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);

        Sanctum::actingAs($customer);

        $response = $this->getJson(route('api.v1.dining.sessions.show', $session));

        $response->assertOk();
        $methods = collect($response->json('meta.payment_methods'));
        $this->assertTrue($methods->contains(fn (array $row): bool => ($row['key'] ?? null) === 'manual_upi'));
        $this->assertTrue($methods->contains(fn (array $row): bool => ($row['key'] ?? null) === 'cash'));
        $this->assertNotEmpty($response->json('meta.payment'));
    }

    public function test_customer_cannot_omit_transaction_id_on_payment_proof_endpoint(): void
    {
        $this->enableDiningPayments();

        $customer = User::factory()->customer()->create();
        $session = $this->billReadySession($customer);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.dining.sessions.payment-proof', $session), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_id']);
    }

    public function test_duplicate_utr_rejected_across_dining_sessions(): void
    {
        $this->enableDiningPayments();

        $customer = User::factory()->customer()->create();
        $first = $this->billReadySession($customer);
        app(DiningSessionServiceInterface::class)->submitPaymentTransactionId(
            $first,
            $customer,
            'SHAREDUTR999',
        );

        $secondCustomer = User::factory()->customer()->create();
        $second = $this->billReadySession($secondCustomer);

        Sanctum::actingAs($secondCustomer);

        $this->postJson(route('api.v1.dining.sessions.payment-proof', $second), [
            'transaction_id' => 'SHAREDUTR999',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_id']);
    }

    private function billReadySession(User $customer): DiningSession
    {
        $dining = app(DiningSessionServiceInterface::class);
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('120.00');

        $session = $dining->startSession($table, $customer, $customer, ['guest_count' => 2]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $customer);
        $dining->placeRound($session, $customer);
        $session = $dining->generateFinalBill($session->fresh(), $customer);
        $dining->setPaymentMethod($session, 'manual_upi');

        return $session->fresh();
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
            'serving_size_value' => '300.000',
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
