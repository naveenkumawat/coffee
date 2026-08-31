<?php

namespace App\Providers;

use App\Events\Customer\CustomerPasswordChanged;
use App\Events\Customer\CustomerRegistered;
use App\Events\Inventory\IngredientStockStatusChanged;
use App\Events\Inventory\InventoryRefillRequestCreated;
use App\Events\Inventory\InventoryRefillRequestStatusChanged;
use App\Events\Menu\MenuCategorySaved;
use App\Events\Menu\MenuItemSaved;
use App\Events\Order\OrderPaymentProofReceived;
use App\Events\Order\OrderPaymentProofRejected;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusChanged;
use App\Listeners\Customer\SendCustomerPasswordChangedNotification;
use App\Listeners\Customer\SendCustomerWelcomeNotification;
use App\Listeners\Menu\FlushMenuCatalogCache;
use App\Listeners\Order\SendOrderPaymentProofReceivedNotification;
use App\Listeners\Order\SendOrderPaymentProofRejectedNotification;
use App\Listeners\Order\SendOrderPlacedNotification;
use App\Listeners\Order\SendOrderStatusChangedNotification;
use App\Listeners\Staff\NotifyStaffIngredientStockStatusChanged;
use App\Listeners\Staff\NotifyStaffInventoryRefillRequest;
use App\Listeners\Staff\NotifyStaffOrderPlaced;
use App\Listeners\Staff\NotifyStaffOrderStatusChanged;
use App\Listeners\Staff\NotifyStaffPaymentProofReceived;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MenuCategorySaved::class => [
            FlushMenuCatalogCache::class,
        ],
        MenuItemSaved::class => [
            FlushMenuCatalogCache::class,
        ],
        CustomerRegistered::class => [
            SendCustomerWelcomeNotification::class,
        ],
        CustomerPasswordChanged::class => [
            SendCustomerPasswordChangedNotification::class,
        ],
        OrderPlaced::class => [
            SendOrderPlacedNotification::class,
            NotifyStaffOrderPlaced::class,
        ],
        OrderPaymentProofReceived::class => [
            SendOrderPaymentProofReceivedNotification::class,
            NotifyStaffPaymentProofReceived::class,
        ],
        OrderPaymentProofRejected::class => [
            SendOrderPaymentProofRejectedNotification::class,
        ],
        OrderStatusChanged::class => [
            SendOrderStatusChangedNotification::class,
            NotifyStaffOrderStatusChanged::class,
        ],
        IngredientStockStatusChanged::class => [
            NotifyStaffIngredientStockStatusChanged::class,
        ],
        InventoryRefillRequestCreated::class => [
            [NotifyStaffInventoryRefillRequest::class, 'handleCreated'],
        ],
        InventoryRefillRequestStatusChanged::class => [
            [NotifyStaffInventoryRefillRequest::class, 'handleStatusChanged'],
        ],
    ];
}
