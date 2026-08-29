<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Parsers\User\UserParser;
use App\Parsers\User\UserParserInterface;
use App\Repositories\User\UserRepository;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\User\UserService;
use App\Services\User\UserServiceInterface;
use App\Transfers\User\UserFilterTransfer;
use App\Transfers\User\UserFilterTransferInterface;
use App\Transfers\User\UserTransfer;
use App\Transfers\User\UserTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_contracts_are_bound(): void
    {
        $this->assertInstanceOf(UserRepository::class, $this->app->make(UserRepositoryInterface::class));
        $this->assertInstanceOf(UserService::class, $this->app->make(UserServiceInterface::class));
        $this->assertInstanceOf(UserParser::class, $this->app->make(UserParserInterface::class));
        $this->assertInstanceOf(UserTransfer::class, $this->app->make(UserTransferInterface::class));
        $this->assertInstanceOf(UserFilterTransfer::class, $this->app->make(UserFilterTransferInterface::class));
    }

    public function test_manager_can_view_user_index(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.users.index'))
            ->assertOk()
            ->assertSee('Users');
    }

    public function test_barista_cannot_access_user_management(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.users.index'))
            ->assertForbidden();
    }

    public function test_manager_can_create_user_with_hashed_password_and_role(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.users.store'), [
            'name' => 'Front Counter User',
            'email' => 'front.counter@example.com',
            'phone' => '9999999999',
            'role' => UserRole::Barista->value,
            'is_active' => '1',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $user = User::query()->where('email', 'front.counter@example.com')->firstOrFail();

        $response->assertRedirect(route('administrator.users.edit', $user));
        $this->assertSame(UserRole::Barista, $user->role);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_manager_can_view_user_details_page(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create([
            'role' => UserRole::Customer,
            'last_login_at' => now()->subDay(),
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.users.show', $customer))
            ->assertOk()
            ->assertSee('internal-button-group', false)
            ->assertSee($customer->email)
            ->assertSee('Pending order module');
    }

    public function test_user_filters_support_search_role_and_status(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->create([
            'name' => 'Brew Captain',
            'email' => 'brew@example.com',
            'phone' => '1234567890',
            'role' => UserRole::Barista,
            'is_active' => true,
        ]);
        User::factory()->inactive()->create([
            'name' => 'Guest Archive',
            'email' => 'guest@example.com',
            'phone' => '8888888888',
            'role' => UserRole::Customer,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.users.index', [
                'search' => '1234567890',
                'role' => 'barista',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertSee('Brew Captain')
            ->assertDontSee('Guest Archive');
    }

    public function test_updating_user_without_password_preserves_existing_password(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create([
            'password' => Hash::make('keepme123'),
        ]);
        $originalHash = $customer->password;

        $response = $this->actingAs($manager, 'admin')->put(route('administrator.users.update', $customer), [
            'name' => 'Updated Customer',
            'email' => $customer->email,
            'phone' => $customer->phone,
            'role' => UserRole::Customer->value,
            'is_active' => '1',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect(route('administrator.users.edit', $customer));
        $customer->refresh();

        $this->assertSame($originalHash, $customer->password);
        $this->assertTrue(Hash::check('keepme123', $customer->password));
    }

    public function test_updating_user_can_reset_password_securely(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create([
            'password' => Hash::make('oldsecret'),
        ]);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.users.update', $customer), [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'role' => UserRole::Customer->value,
                'is_active' => '1',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertRedirect(route('administrator.users.edit', $customer));

        $customer->refresh();

        $this->assertTrue(Hash::check('newsecret123', $customer->password));
        $this->assertFalse(Hash::check('oldsecret', $customer->password));
    }

    public function test_manager_can_change_user_role_and_status(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create([
            'role' => UserRole::Customer,
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.users.update', $customer), [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'role' => UserRole::Barista->value,
                'is_active' => '0',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('administrator.users.edit', $customer));

        $customer->refresh();

        $this->assertSame(UserRole::Barista, $customer->role);
        $this->assertFalse($customer->is_active);
    }

    public function test_manager_can_archive_user_safely(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create([
            'role' => UserRole::Customer,
        ]);

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.users.destroy', $customer))
            ->assertRedirect(route('administrator.users.index'));

        $this->assertSoftDeleted('users', ['id' => $customer->id]);
    }

    public function test_user_cannot_deactivate_their_own_administrator_account(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner, 'admin')->from(route('administrator.users.edit', $owner))
            ->put(route('administrator.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'role' => UserRole::Owner->value,
                'is_active' => '0',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response
            ->assertRedirect(route('administrator.users.edit', $owner))
            ->assertSessionHasErrors('is_active');
    }

    public function test_user_cannot_remove_their_own_administrator_role(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner, 'admin')->from(route('administrator.users.edit', $owner))
            ->put(route('administrator.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'role' => UserRole::Customer->value,
                'is_active' => '1',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response
            ->assertRedirect(route('administrator.users.edit', $owner))
            ->assertSessionHasErrors('role');
    }

    public function test_cannot_deactivate_last_active_administrator(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner, 'admin')->from(route('administrator.users.edit', $owner))
            ->put(route('administrator.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'role' => UserRole::Owner->value,
                'is_active' => '0',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response
            ->assertRedirect(route('administrator.users.edit', $owner))
            ->assertSessionHasErrors('is_active');
    }

    public function test_cannot_archive_last_active_administrator(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner, 'admin')->from(route('administrator.users.index'))
            ->delete(route('administrator.users.destroy', $owner));

        $response
            ->assertRedirect(route('administrator.users.index'))
            ->assertSessionHasErrors('user');
    }

    public function test_existing_administrator_and_barista_auth_flows_remain_valid(): void
    {
        $administrator = User::factory()->owner()->create([
            'email' => 'admin-auth@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $barista = User::factory()->barista()->create([
            'email' => 'barista-auth@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post(route('administrator.login.store'), [
            'email' => $administrator->email,
            'password' => 'secret123',
        ])->assertRedirect(route('administrator.dashboard'));

        auth('admin')->logout();

        $this->post(route('barista.login.store'), [
            'email' => $barista->email,
            'password' => 'secret123',
        ])->assertRedirect(route('barista.dashboard'));
    }
}
