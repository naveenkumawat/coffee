<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class DemoDiningSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        // Seed dining regardless of wall-clock cafe hours.
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-01 10:30:00', 'Asia/Kolkata'));

        try {
            $this->seedDiningDemo();
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function seedDiningDemo(): void
    {
        $key = WebsiteSettingKey::FulfilmentDineInEnabled;
        WebsiteSetting::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => '1',
            ],
        );

        $waiter = User::query()->updateOrCreate(
            ['email' => 'waiter@coffee.local'],
            [
                'name' => 'Coffee Waiter',
                'phone' => '9000000090',
                'role' => UserRole::Waiter,
                'is_active' => true,
                'password' => Hash::make(DemoUserSeeder::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $operator = User::query()->where('email', 'operator@coffee.local')->first()
            ?? User::query()->where('role', UserRole::Operator)->first()
            ?? User::query()->where('role', UserRole::Owner)->first()
            ?? $waiter;

        $customer = User::query()->where('email', 'customer@coffee.local')->first();
        $altCustomer = User::query()->where('email', 'priya@coffee.local')->first()
            ?? User::query()->where('role', UserRole::Customer)->whereKeyNot($customer?->getKey())->first();
        $variant = ProductVariant::query()->where('is_active', true)->where('is_available', true)->first();
        $tables = CafeTable::query()->where('is_active', true)->orderBy('sort_order')->take(5)->get();

        if (! $variant || $tables->count() < 4) {
            return;
        }

        $dining = app(DiningSessionServiceInterface::class);

        // Open multi-round session (authenticated customer)
        $open = $dining->startSession($tables[0], $customer, $waiter, ['guest_count' => 2]);
        $dining->addDraftItem($open, (int) $variant->getKey(), 1, $customer);
        $dining->placeRound($open, $waiter);
        $dining->addDraftItem($open, (int) $variant->getKey(), 1, $customer);
        $dining->placeRound($open, $waiter);

        // Bill requested (different customer or walk-in)
        $billing = $dining->startSession($tables[1], $altCustomer, $waiter, ['guest_count' => 3]);
        $dining->addDraftItem($billing, (int) $variant->getKey(), 2, $altCustomer ?? $waiter);
        $dining->placeRound($billing, $waiter);
        $dining->requestBill($billing, $waiter);

        // Cash paid + closed (walk-in)
        $cash = $dining->startSession($tables[2], null, $waiter, ['guest_count' => 1]);
        $dining->addDraftItem($cash, (int) $variant->getKey(), 1, $waiter);
        $dining->placeRound($cash, $waiter);
        $cash = $dining->requestBill($cash, $waiter);
        $cash = $dining->setPaymentMethod($cash, 'cash');
        $cash = $dining->markCashReceived($cash, $waiter);
        $dining->closeSession($cash, $waiter);

        // UPI paid (walk-in) — Operator confirms after proof
        $upi = $dining->startSession($tables[3], null, $waiter, ['guest_count' => 2]);
        $dining->addDraftItem($upi, (int) $variant->getKey(), 1, $waiter);
        $dining->placeRound($upi, $waiter);
        $upi = $dining->requestBill($upi, $waiter);
        $upi = $dining->setPaymentMethod($upi, 'manual_upi');
        $upi = $dining->uploadPaymentProof($upi, $waiter, UploadedFile::fake()->image('dining-upi.jpg'));
        $dining->confirmPayment($upi, $operator);

        if ($tables->count() > 4) {
            $walkIn = $dining->startSession($tables[4], null, $waiter, ['guest_count' => 4]);
            $dining->addDraftItem($walkIn, (int) $variant->getKey(), 1, $waiter);
            $dining->placeRound($walkIn, $waiter);
        }
    }
}
