<?php

namespace Database\Seeders;

use App\Enums\StaffNotificationType;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\Order;
use App\Models\User;
use App\Notifications\StaffOperationalNotification;
use App\Services\Notification\StaffNotificationContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed in-app staff bell rows only (database channel). No mail/WhatsApp/HTTP.
 */
class DemoStaffNotificationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $admin = User::query()->where('email', 'admin@coffee.local')->first();
        $manager = User::query()->where('email', 'manager@coffee.local')->first();
        $barista = User::query()->where('email', 'barista@coffee.local')->first();
        $barista2 = User::query()->where('email', 'barista2@coffee.local')->first();

        $staff = array_values(array_filter([$admin, $manager, $barista, $barista2]));

        if ($staff === []) {
            return;
        }

        DB::table('notifications')
            ->whereIn('notifiable_id', collect($staff)->pluck('id'))
            ->where('notifiable_type', User::class)
            ->delete();

        $orderPlaced = Order::query()->where('order_number', 'CC-SEED-0001')->first()
            ?? Order::query()->where('status', 'pending_payment')->latest('id')->first();
        $orderProof = Order::query()->where('order_number', 'CC-SEED-0002')->first();
        $orderPaid = Order::query()->where('order_number', 'CC-SEED-0009')->first()
            ?? Order::query()->where('status', 'payment_confirmed')->latest('id')->first();
        $dineInPreparing = Order::query()->where('order_number', 'CC-DINE-0004')->first();

        $low = Ingredient::query()->where('name', 'Oat Milk')->first();
        $oos = Ingredient::query()->where('name', 'White Sugar')->first();
        $refill = InventoryRefillRequest::query()->latest('id')->first();

        $definitions = array_values(array_filter([
            $orderPlaced ? [StaffNotificationType::OrderPlaced, StaffNotificationContext::forOrder($orderPlaced), false, 5] : null,
            $orderProof ? [StaffNotificationType::PaymentProofReceived, StaffNotificationContext::forOrder($orderProof), false, 15] : null,
            $orderPaid ? [StaffNotificationType::PaymentConfirmed, StaffNotificationContext::forOrder($orderPaid), false, 25] : null,
            $dineInPreparing ? [StaffNotificationType::PaymentConfirmed, StaffNotificationContext::forOrder($dineInPreparing), true, 40] : null,
            $low ? [StaffNotificationType::IngredientLowStock, StaffNotificationContext::forIngredient($low), false, 60] : null,
            $oos ? [StaffNotificationType::IngredientOutOfStock, StaffNotificationContext::forIngredient($oos), false, 90] : null,
            $refill ? [StaffNotificationType::RefillRequestCreated, StaffNotificationContext::forRefillRequest($refill), true, 120] : null,
        ]));

        foreach ($staff as $user) {
            foreach ($definitions as [$type, $context, $read, $minutesAgo]) {
                $notification = new StaffOperationalNotification($type, $context, ['database']);
                $data = $notification->toDatabase($user);

                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => StaffOperationalNotification::class,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode($data),
                    'read_at' => $read ? Carbon::now()->subMinutes(max(1, $minutesAgo - 2)) : null,
                    'created_at' => Carbon::now()->subMinutes($minutesAgo),
                    'updated_at' => Carbon::now()->subMinutes($minutesAgo),
                ]);
            }
        }
    }
}
