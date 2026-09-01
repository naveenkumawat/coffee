<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CustomerReferral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_referrals_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Owner]);
        CustomerReferral::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.referrals.index'))
            ->assertOk();
    }

    public function test_barista_cannot_view_referrals_index(): void
    {
        $barista = User::factory()->create(['role' => UserRole::Barista]);

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.referrals.index'))
            ->assertForbidden();
    }
}
