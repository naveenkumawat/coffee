<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\InventoryRefillRequestStatus;
use App\Enums\InventoryTransactionType;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Parsers\Inventory\InventoryRefillRequestParser;
use App\Parsers\Inventory\InventoryRefillRequestParserInterface;
use App\Repositories\Inventory\InventoryRefillRequestRepository;
use App\Repositories\Inventory\InventoryRefillRequestRepositoryInterface;
use App\Services\Inventory\InventoryRefillRequestService;
use App\Services\Inventory\InventoryRefillRequestServiceInterface;
use App\Transfers\Inventory\InventoryRefillRequestFilterTransfer;
use App\Transfers\Inventory\InventoryRefillRequestFilterTransferInterface;
use App\Transfers\Inventory\InventoryRefillRequestTransfer;
use App\Transfers\Inventory\InventoryRefillRequestTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryRefillRequestManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_refill_request_architecture_contracts_and_schema_are_bound(): void
    {
        $this->assertInstanceOf(InventoryRefillRequestRepository::class, $this->app->make(InventoryRefillRequestRepositoryInterface::class));
        $this->assertInstanceOf(InventoryRefillRequestService::class, $this->app->make(InventoryRefillRequestServiceInterface::class));
        $this->assertInstanceOf(InventoryRefillRequestParser::class, $this->app->make(InventoryRefillRequestParserInterface::class));
        $this->assertInstanceOf(InventoryRefillRequestTransfer::class, $this->app->make(InventoryRefillRequestTransferInterface::class));
        $this->assertInstanceOf(InventoryRefillRequestFilterTransfer::class, $this->app->make(InventoryRefillRequestFilterTransferInterface::class));
        $this->assertTrue(Schema::hasTable('inventory_refill_requests'));
        $this->assertTrue(Schema::hasColumn('inventory_refill_requests', 'base_quantity'));
        $this->assertTrue(Schema::hasColumn('inventory_refill_requests', 'requested_by'));
        $this->assertTrue(Schema::hasColumn('inventory_refill_requests', 'reviewed_at'));
    }

    public function test_barista_can_create_view_and_filter_own_refill_requests(): void
    {
        $barista = User::factory()->barista()->create(['name' => 'Shift Barista']);
        $otherBarista = User::factory()->barista()->create(['name' => 'Other Barista']);
        $ingredient = Ingredient::factory()->create([
            'name' => 'Full Fat Milk',
            'measurement_unit' => IngredientUnit::Liter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'current_stock' => '2000.000',
            'is_active' => true,
        ]);

        $otherRequest = InventoryRefillRequest::factory()->create([
            'ingredient_id' => Ingredient::factory()->create(['name' => 'Vanilla Syrup']),
            'requested_by' => $otherBarista->id,
        ]);

        $this->actingAs($barista, 'admin')->post(route('barista.refill-requests.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => '5.000',
            'measurement_unit' => IngredientUnit::Liter->value,
            'notes' => 'Morning rush stock running low.',
        ])->assertRedirect();

        $request = InventoryRefillRequest::query()->where('requested_by', $barista->id)->firstOrFail();

        $this->assertSame('5.000', $request->quantity);
        $this->assertSame('5000.000', $request->base_quantity);
        $this->assertSame(InventoryRefillRequestStatus::Pending, $request->status);

        $this->actingAs($barista, 'admin')
            ->get(route('barista.refill-requests.index', [
                'search' => 'Morning rush',
                'ingredient_id' => $ingredient->id,
                'status' => InventoryRefillRequestStatus::Pending->value,
            ]))
            ->assertOk()
            ->assertSee('Full Fat Milk')
            ->assertDontSee(route('barista.refill-requests.show', $otherRequest), false);

        $this->actingAs($barista, 'admin')
            ->get(route('barista.refill-requests.show', $request))
            ->assertOk()
            ->assertSee('Morning rush stock running low.')
            ->assertSee('Shift Barista');
    }

    public function test_barista_cannot_create_duplicate_active_refill_request_for_same_ingredient(): void
    {
        $barista = User::factory()->barista()->create();
        $ingredient = Ingredient::factory()->create([
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'is_active' => true,
        ]);

        InventoryRefillRequest::factory()->create([
            'ingredient_id' => $ingredient->id,
            'requested_by' => User::factory()->barista(),
            'status' => InventoryRefillRequestStatus::Pending,
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'quantity' => '200.000',
            'base_quantity' => '200.000',
        ]);

        $this->actingAs($barista, 'admin')->from(route('barista.refill-requests.create'))
            ->post(route('barista.refill-requests.store'), [
                'ingredient_id' => $ingredient->id,
                'quantity' => '100.000',
                'measurement_unit' => IngredientUnit::Gram->value,
                'notes' => 'Need more beans.',
            ])
            ->assertRedirect(route('barista.refill-requests.create'))
            ->assertSessionHasErrors('ingredient_id');
    }

    public function test_barista_refill_request_validation_rejects_inactive_ingredients_invalid_units_and_invalid_quantity(): void
    {
        $barista = User::factory()->barista()->create();
        $inactiveIngredient = Ingredient::factory()->create([
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'is_active' => false,
        ]);

        $this->actingAs($barista, 'admin')->from(route('barista.refill-requests.create'))
            ->post(route('barista.refill-requests.store'), [
                'ingredient_id' => $inactiveIngredient->id,
                'quantity' => '1.000',
                'measurement_unit' => IngredientUnit::Gram->value,
            ])
            ->assertRedirect(route('barista.refill-requests.create'))
            ->assertSessionHasErrors('ingredient_id');

        $activeIngredient = Ingredient::factory()->create([
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'is_active' => true,
        ]);

        $this->actingAs($barista, 'admin')->from(route('barista.refill-requests.create'))
            ->post(route('barista.refill-requests.store'), [
                'ingredient_id' => $activeIngredient->id,
                'quantity' => '0.000',
                'measurement_unit' => IngredientUnit::Gram->value,
            ])
            ->assertRedirect(route('barista.refill-requests.create'))
            ->assertSessionHasErrors('quantity');

        $this->actingAs($barista, 'admin')->from(route('barista.refill-requests.create'))
            ->post(route('barista.refill-requests.store'), [
                'ingredient_id' => $activeIngredient->id,
                'quantity' => '1.000',
                'measurement_unit' => IngredientUnit::Liter->value,
            ])
            ->assertRedirect(route('barista.refill-requests.create'))
            ->assertSessionHasErrors('measurement_unit');
    }

    public function test_administrator_can_approve_or_reject_refill_requests_without_mutating_stock(): void
    {
        $manager = User::factory()->manager()->create(['name' => 'Inventory Manager']);
        $barista = User::factory()->barista()->create();
        $ingredient = Ingredient::factory()->create([
            'current_stock' => '90.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $approveRequest = InventoryRefillRequest::factory()->create([
            'ingredient_id' => $ingredient->id,
            'requested_by' => $barista->id,
            'status' => InventoryRefillRequestStatus::Pending,
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'quantity' => '150.000',
            'base_quantity' => '150.000',
        ]);

        $rejectRequest = InventoryRefillRequest::factory()->create([
            'ingredient_id' => Ingredient::factory()->create([
                'measurement_unit' => IngredientUnit::Bottle,
                'base_measurement_unit' => IngredientUnit::Bottle,
            ])->id,
            'requested_by' => $barista->id,
            'status' => InventoryRefillRequestStatus::Pending,
            'measurement_unit' => IngredientUnit::Bottle,
            'base_measurement_unit' => IngredientUnit::Bottle,
            'quantity' => '2.000',
            'base_quantity' => '2.000',
        ]);

        $this->actingAs($manager, 'admin')->patch(route('administrator.inventory.refill-requests.approve', $approveRequest), [
            'status' => InventoryRefillRequestStatus::Approved->value,
            'review_notes' => 'Approved for next supplier run.',
        ])->assertRedirect(route('administrator.inventory.refill-requests.show', $approveRequest));

        $this->actingAs($manager, 'admin')->patch(route('administrator.inventory.refill-requests.reject', $rejectRequest), [
            'status' => InventoryRefillRequestStatus::Rejected->value,
            'review_notes' => 'Use existing backup stock first.',
        ])->assertRedirect(route('administrator.inventory.refill-requests.show', $rejectRequest));

        $approveRequest->refresh();
        $rejectRequest->refresh();

        $this->assertSame(InventoryRefillRequestStatus::Approved, $approveRequest->status);
        $this->assertSame($manager->id, $approveRequest->reviewed_by);
        $this->assertSame(InventoryRefillRequestStatus::Rejected, $rejectRequest->status);
        $this->assertSame('90.000', $ingredient->fresh()->current_stock);
    }

    public function test_approved_refill_request_can_be_completed_by_linked_inventory_transaction(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $ingredient = Ingredient::factory()->create([
            'current_stock' => '1000.000',
            'measurement_unit' => IngredientUnit::Milliliter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'is_active' => true,
        ]);

        $request = InventoryRefillRequest::factory()->create([
            'ingredient_id' => $ingredient->id,
            'requested_by' => $barista->id,
            'status' => InventoryRefillRequestStatus::Pending,
            'measurement_unit' => IngredientUnit::Milliliter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'quantity' => '500.000',
            'base_quantity' => '500.000',
        ]);

        $this->actingAs($manager, 'admin')->patch(route('administrator.inventory.refill-requests.approve', $request), [
            'status' => InventoryRefillRequestStatus::Approved->value,
            'review_notes' => 'Approved.',
        ])->assertRedirect();

        $this->assertSame('1000.000', $ingredient->fresh()->current_stock);

        $this->actingAs($manager, 'admin')->post(route('administrator.inventory.movements.store'), [
            'ingredient_id' => $ingredient->id,
            'inventory_refill_request_id' => $request->id,
            'transaction_type' => InventoryTransactionType::StockAdded->value,
            'quantity' => '500.000',
            'measurement_unit' => IngredientUnit::Milliliter->value,
            'notes' => 'Received and stocked.',
        ])->assertRedirect(route('administrator.inventory.history', ['ingredient_id' => $ingredient->id]));

        $request->refresh();
        $transaction = InventoryTransaction::query()->latest('id')->firstOrFail();

        $this->assertSame(InventoryRefillRequestStatus::Completed, $request->status);
        $this->assertSame('inventory_refill_request', $transaction->reference_type);
        $this->assertSame($request->id, $transaction->reference_id);
        $this->assertSame('1500.000', $ingredient->fresh()->current_stock);
    }

    public function test_refill_request_authorization_limits_barista_and_administrator_access_correctly(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $otherBarista = User::factory()->barista()->create();
        $request = InventoryRefillRequest::factory()->create([
            'requested_by' => $barista->id,
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('barista.refill-requests.index'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.inventory.refill-requests.index'))
            ->assertForbidden();

        $this->actingAs($otherBarista, 'admin')
            ->get(route('barista.refill-requests.show', $request))
            ->assertForbidden();
    }

    public function test_administrator_can_filter_refill_requests_by_status_requester_and_ingredient(): void
    {
        $manager = User::factory()->manager()->create();
        $baristaA = User::factory()->barista()->create(['name' => 'Asha']);
        $baristaB = User::factory()->barista()->create(['name' => 'Bina']);
        $ingredientA = Ingredient::factory()->create(['name' => 'Vanilla Ice Cream']);
        $ingredientB = Ingredient::factory()->create(['name' => 'Hazelnut Syrup']);

        $matchingRequest = InventoryRefillRequest::factory()->create([
            'ingredient_id' => $ingredientA->id,
            'requested_by' => $baristaA->id,
            'status' => InventoryRefillRequestStatus::Pending,
            'notes' => 'Freezer stock almost empty.',
            'measurement_unit' => IngredientUnit::Kilogram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'quantity' => '3.000',
            'base_quantity' => '3000.000',
        ]);

        $otherRequest = InventoryRefillRequest::factory()->create([
            'ingredient_id' => $ingredientB->id,
            'requested_by' => $baristaB->id,
            'status' => InventoryRefillRequestStatus::Rejected,
            'notes' => 'Already reordered.',
            'measurement_unit' => IngredientUnit::Bottle,
            'base_measurement_unit' => IngredientUnit::Bottle,
            'quantity' => '2.000',
            'base_quantity' => '2.000',
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.inventory.refill-requests.index', [
                'search' => 'Freezer',
                'ingredient_id' => $ingredientA->id,
                'status' => InventoryRefillRequestStatus::Pending->value,
                'requested_by' => $baristaA->id,
            ]))
            ->assertOk()
            ->assertSee('Vanilla Ice Cream')
            ->assertSee('Asha')
            ->assertSee(route('administrator.inventory.refill-requests.show', $matchingRequest), false)
            ->assertDontSee(route('administrator.inventory.refill-requests.show', $otherRequest), false);
    }

    public function test_inventory_and_refill_request_pages_use_shared_internal_ui_components(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $request = InventoryRefillRequest::factory()->create([
            'requested_by' => $barista->id,
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.inventory.refill-requests.index'))
            ->assertOk()
            ->assertSee('internal-action-dropdown-trigger', false)
            ->assertSee('internal-button-group', false);

        $this->actingAs($barista, 'admin')
            ->get(route('barista.refill-requests.show', $request))
            ->assertOk()
            ->assertSee('internal-button-group', false);
    }
}
