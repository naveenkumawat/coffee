<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
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

class DiningCloseReopenConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_cannot_manually_close_before_payment_but_admin_can_with_reason(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $operator = User::factory()->operator()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant();

        $dining = app(DiningSessionServiceInterface::class);
        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 2]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);

        try {
            $dining->closeSession($session->fresh(), $waiter);
            $this->fail('Waiters must not close unpaid sessions.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }

        try {
            $dining->closeSession($session->fresh(), $operator);
            $this->fail('Manual close before payment requires a reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $closed = $dining->closeSession($session->fresh(), $operator, 'Guest left without ordering more');
        $this->assertSame(DiningSessionStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertStringContainsString('Manual close:', (string) $closed->payment_proof_rejection_notes);

        $tables = $dining->tableOperationalStates();
        $row = $tables->first(fn (array $entry): bool => (int) $entry['table']->id === (int) $table->id);
        $this->assertSame('available', $row['state']);
    }

    public function test_paid_closed_session_cannot_be_reopened_and_unpaid_closed_can(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $operator = User::factory()->operator()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant();
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 2]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);
        $session = $dining->generateFinalBill($session->fresh(), $waiter);
        $session = $dining->markCashReceived($session, $waiter);

        $this->assertSame(DiningSessionStatus::Closed, $session->fresh()->status);
        $this->assertSame(PaymentStatus::Confirmed, $session->fresh()->payment_status);

        try {
            $dining->reopenSession($session->fresh(), $operator, 'Attempt paid reopen');
            $this->fail('Paid closed sessions must stay closed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }

        $abandoned = $dining->startSession(
            CafeTable::factory()->create(['is_active' => true]),
            null,
            $waiter,
            ['guest_count' => 1],
        );
        $abandoned = $dining->closeSession($abandoned, $operator, 'Walkaway');

        try {
            $dining->reopenSession($abandoned->fresh(), $waiter, 'Waiter reopen');
            $this->fail('Waiters cannot reopen closed sessions.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }

        $reopened = $dining->reopenSession($abandoned->fresh(), $operator, 'Guest returned');
        $this->assertSame(DiningSessionStatus::Open, $reopened->status);
        $this->assertNull($reopened->closed_at);
    }

    public function test_resume_ordering_requires_reason_and_blocks_conflicting_table_reopen(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $operator = User::factory()->operator()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant();
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 2]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);
        $session = $dining->generateFinalBill($session->fresh(), $waiter);

        Sanctum::actingAs($waiter);
        $this->postJson(route('api.v1.waiter.sessions.reopen', $session->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->postJson(route('api.v1.waiter.sessions.reopen', $session->id), [
            'note' => 'Guest wants dessert',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::Open->value);

        $closed = $dining->closeSession($session->fresh(), $operator, 'Ended early');
        $other = $dining->startSession($table->fresh(), null, $waiter, ['guest_count' => 2]);
        $this->assertSame(DiningSessionStatus::Open, $other->status);

        try {
            $dining->reopenSession($closed->fresh(), $operator, 'Conflict reopen');
            $this->fail('Conflicting active table session must block reopen.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }
    }

    public function test_admin_dining_actions_use_designed_confirm_attributes(): void
    {
        $this->enableDining();

        $admin = User::factory()->manager()->create();
        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true, 'code' => 'T9']);
        $variant = $this->makePurchasableVariant();
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 2]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);
        $session = $dining->generateFinalBill($session->fresh(), $waiter);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('administrator.dining-sessions.show', $session))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('internalConfirmModal', $html);
        $this->assertStringContainsString('data-confirm-title="Close dining session?"', $html);
        $this->assertStringContainsString('data-confirm-title="Resume ordering?"', $html);
        $this->assertStringContainsString('confirm-modal.js', $html);
        $this->assertStringNotContainsString('onsubmit="return confirm(', $html);
    }

    protected function enableDining(): void
    {
        foreach ([
            [WebsiteSettingKey::FulfilmentDineInEnabled, '1'],
            [WebsiteSettingKey::OrderingManualClosed, '0'],
            [WebsiteSettingKey::TaxEnabled, '0'],
        ] as [$key, $value]) {
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

    protected function makePurchasableVariant(): ProductVariant
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '40.00',
            'serving_size_value' => '1',
            'serving_size_unit' => ProductServingUnit::Piece,
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
