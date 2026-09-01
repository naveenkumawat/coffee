<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PreparationStation;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderPreparation;
use App\Models\User;
use App\Policies\OrderPreparationPolicy;
use App\Services\Order\OrderServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorChefAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_permissions_and_panel_access(): void
    {
        $operator = User::factory()->operator()->create();

        $this->assertTrue($operator->canAccessAdminPanel());
        $this->assertFalse($operator->isAdministratorRole());
        $this->assertTrue($operator->canOperateOrders());
        $this->assertTrue($operator->canViewOrders());
        $this->assertTrue($operator->canViewProducts());
        $this->assertTrue($operator->canViewInventory());
        $this->assertTrue($operator->canOperateDining());
        $this->assertTrue($operator->canAccessOperatorPanel());
        $this->assertTrue($operator->canAccessInternalPanel('operator'));
        $this->assertFalse($operator->canAccessBaristaPanel());
        $this->assertFalse($operator->canAccessChefPanel());
        $this->assertFalse($operator->canManageOrders());
        $this->assertFalse($operator->canManageUsers());
        $this->assertFalse($operator->canPrepareStation(PreparationStation::Bar));
        $this->assertFalse($operator->canPrepareStation(PreparationStation::Kitchen));
        $this->assertSame('Operator', $operator->managementRoleLabel());
    }

    public function test_chef_permissions_and_panel_access(): void
    {
        $chef = User::factory()->chef()->create();

        $this->assertTrue($chef->canAccessAdminPanel());
        $this->assertFalse($chef->isAdministratorRole());
        $this->assertTrue($chef->canViewOrders());
        $this->assertTrue($chef->canViewProducts());
        $this->assertFalse($chef->canViewInventory());
        $this->assertFalse($chef->canOperateOrders());
        $this->assertFalse($chef->canOperateDining());
        $this->assertTrue($chef->canAccessChefPanel());
        $this->assertTrue($chef->canAccessInternalPanel('chef'));
        $this->assertFalse($chef->canAccessBaristaPanel());
        $this->assertFalse($chef->canAccessOperatorPanel());
        $this->assertTrue($chef->canPrepareStation(PreparationStation::Kitchen));
        $this->assertFalse($chef->canPrepareStation(PreparationStation::Bar));
        $this->assertSame('Chef', $chef->managementRoleLabel());
    }

    public function test_barista_no_longer_operates_orders_but_prepares_bar(): void
    {
        $barista = User::factory()->barista()->create();

        $this->assertFalse($barista->canOperateOrders());
        $this->assertTrue($barista->canPrepareStation(PreparationStation::Bar));
        $this->assertFalse($barista->canPrepareStation(PreparationStation::Kitchen));
        $this->assertTrue($barista->canAccessBaristaPanel());
        $this->assertTrue($barista->canViewInventory());
    }

    public function test_order_level_transitions_are_operator_or_admin_only(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PaymentConfirmed,
        ]);

        $orders = app(OrderServiceInterface::class);

        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();
        $admin = User::factory()->owner()->create();

        $this->assertArrayHasKey(OrderStatus::Accepted->value, $orders->availableTransitions($order, $operator));
        $this->assertArrayHasKey(OrderStatus::Accepted->value, $orders->availableTransitions($order, $admin));
        $this->assertSame([], $orders->availableTransitions($order, $barista));
        $this->assertSame([], $orders->availableTransitions($order, $chef));
    }

    public function test_preparation_policy_is_station_scoped(): void
    {
        $policy = app(OrderPreparationPolicy::class);

        $barTicket = new OrderPreparation([
            'station' => PreparationStation::Bar,
            'status' => 'pending',
        ]);
        $kitchenTicket = new OrderPreparation([
            'station' => PreparationStation::Kitchen,
            'status' => 'pending',
        ]);

        $barista = User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();
        $operator = User::factory()->operator()->create();
        $waiter = User::factory()->waiter()->create();

        $this->assertTrue($policy->view($barista, $barTicket));
        $this->assertTrue($policy->transition($barista, $barTicket));
        $this->assertFalse($policy->view($barista, $kitchenTicket));
        $this->assertFalse($policy->transition($barista, $kitchenTicket));

        $this->assertTrue($policy->view($chef, $kitchenTicket));
        $this->assertTrue($policy->transition($chef, $kitchenTicket));
        $this->assertFalse($policy->transition($chef, $barTicket));

        $this->assertTrue($policy->view($operator, $barTicket));
        $this->assertTrue($policy->view($operator, $kitchenTicket));
        $this->assertFalse($policy->transition($operator, $barTicket));
        $this->assertFalse($policy->transition($operator, $kitchenTicket));

        $this->assertFalse($policy->viewAny($waiter));
        $this->assertFalse($policy->transition($waiter, $barTicket));
    }

    public function test_user_role_enum_includes_operator_and_chef_exhaustively(): void
    {
        $roles = collect(UserRole::cases())->map(fn (UserRole $role): string => $role->value)->all();

        $this->assertContains(UserRole::Operator->value, $roles);
        $this->assertContains(UserRole::Chef->value, $roles);

        foreach (UserRole::cases() as $role) {
            $role->label();
            $role->canAccessAdmin();
            $role->isAdministratorRole();
            $role->canViewProducts();
            $role->canViewInventory();
            $role->canViewOrders();
            $role->canOperateOrders();
            $role->canOperateDining();
            $role->canPrepareStation(PreparationStation::Bar);
            $role->canPrepareStation(PreparationStation::Kitchen);
            $role->canAccessOperatorPanel();
            $role->canAccessBaristaPanel();
            $role->canAccessChefPanel();
            $role->canAccessWaiterPanel();
            $role->canAccessAdministratorPanel();
            $role->managementLabel();
        }

        $this->assertTrue(true);
    }
}
