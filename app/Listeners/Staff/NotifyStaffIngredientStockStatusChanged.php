<?php

namespace App\Listeners\Staff;

use App\Enums\InventoryStockStatus;
use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Events\Inventory\IngredientStockStatusChanged;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;

class NotifyStaffIngredientStockStatusChanged
{
    public function __construct(
        protected StaffNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(IngredientStockStatusChanged $event): void
    {
        if ($event->fromStatus === $event->toStatus) {
            return;
        }

        $ingredient = $event->ingredient;
        $context = StaffNotificationContext::forIngredient($ingredient);
        $episodeKey = 'staff:stock:'.$ingredient->getKey().':'.$event->toStatus->value.':tx:'.$event->transaction->getKey();

        if ($event->toStatus === InventoryStockStatus::LowStock
            && $event->fromStatus === InventoryStockStatus::InStock) {
            $this->dispatcher->notify(
                StaffNotificationType::IngredientLowStock,
                $episodeKey,
                StaffNotificationAudience::Administrators,
                $context,
                sendEmail: false,
            );

            return;
        }

        if ($event->toStatus === InventoryStockStatus::OutOfStock) {
            $this->dispatcher->notify(
                StaffNotificationType::IngredientOutOfStock,
                $episodeKey,
                StaffNotificationAudience::Administrators,
                $context,
                sendEmail: true,
            );
            $this->dispatcher->notify(
                StaffNotificationType::IngredientOutOfStock,
                $episodeKey,
                StaffNotificationAudience::Baristas,
                $context,
                sendEmail: false,
            );

            return;
        }

        if ($event->toStatus === InventoryStockStatus::InStock
            && in_array($event->fromStatus, [InventoryStockStatus::LowStock, InventoryStockStatus::OutOfStock], true)) {
            $this->dispatcher->notify(
                StaffNotificationType::IngredientStockRestored,
                $episodeKey,
                StaffNotificationAudience::Administrators,
                $context,
                sendEmail: false,
            );
        }
    }
}
