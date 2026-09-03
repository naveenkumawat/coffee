<?php

namespace App\Providers;

use App\Events\Customer\CustomerPasswordChanged;
use App\Events\Customer\CustomerRegistered;
use App\Events\Dining\DiningBillReady;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Events\Dining\DiningPaymentProofReceived;
use App\Events\Dining\DiningPaymentProofRejected;
use App\Events\Dining\DiningRoundPlaced;
use App\Events\Dining\DiningSessionClosed;
use App\Events\Dining\DiningSessionOpened;
use App\Events\Dining\DiningSessionReopened;
use App\Events\Inventory\IngredientStockStatusChanged;
use App\Events\Inventory\InventoryRefillRequestCreated;
use App\Events\Inventory\InventoryRefillRequestStatusChanged;
use App\Events\Menu\MenuCategorySaved;
use App\Events\Menu\MenuItemSaved;
use App\Events\Order\OrderCashReceived;
use App\Events\Order\OrderPaymentProofReceived;
use App\Events\Order\OrderPaymentProofRejected;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderPreparationStatusChanged;
use App\Events\Order\OrderStatusChanged;
use App\Listeners\Customer\SendCustomerPasswordChangedNotification;
use App\Listeners\Customer\SendCustomerWelcomeNotification;
use App\Listeners\Dining\QualifyReferralOnDiningPaymentConfirmed;
use App\Listeners\Dining\SendDiningBillReadyNotification;
use App\Listeners\Dining\SendDiningPaymentConfirmedNotification;
use App\Listeners\Dining\WireDiningRealtimeSignals;
use App\Listeners\Menu\FlushMenuCatalogCache;
use App\Listeners\OperationalNotification\WireOperationalDiningBillReady;
use App\Listeners\OperationalNotification\WireOperationalDiningPaymentConfirmed;
use App\Listeners\OperationalNotification\WireOperationalDiningPaymentProofReceived;
use App\Listeners\OperationalNotification\WireOperationalDiningPaymentProofRejected;
use App\Listeners\OperationalNotification\WireOperationalDiningRoundPlaced;
use App\Listeners\OperationalNotification\WireOperationalIngredientStockStatusChanged;
use App\Listeners\OperationalNotification\WireOperationalInventoryRefillRequest;
use App\Listeners\OperationalNotification\WireOperationalOrderPlaced;
use App\Listeners\OperationalNotification\WireOperationalOrderPreparationStatusChanged;
use App\Listeners\OperationalNotification\WireOperationalOrderStatusChanged;
use App\Listeners\OperationalNotification\WireOperationalPaymentProofReceived;
use App\Listeners\OperationalNotification\WireOperationalPaymentProofRejected;
use App\Listeners\Order\QualifyReferralOnCashReceived;
use App\Listeners\Order\QualifyReferralOnPaymentConfirmed;
use App\Listeners\Order\SendOrderCashReceivedNotification;
use App\Listeners\Order\SendOrderPaymentProofReceivedNotification;
use App\Listeners\Order\SendOrderPaymentProofRejectedNotification;
use App\Listeners\Order\SendOrderPlacedNotification;
use App\Listeners\Order\SendOrderStatusChangedNotification;
use App\Listeners\Staff\NotifyStaffIngredientStockStatusChanged;
use App\Listeners\Staff\NotifyStaffInventoryRefillRequest;
use App\Listeners\Staff\NotifyStaffOrderPlaced;
use App\Listeners\Staff\NotifyStaffOrderPreparationStatusChanged;
use App\Listeners\Staff\NotifyStaffOrderStatusChanged;
use App\Listeners\Staff\NotifyStaffPaymentProofReceived;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Listeners are registered explicitly in $listen. Framework discovery is
     * disabled via Application::configure()->withEvents(discover: false) so
     * WireOperational* / WireDiningRealtimeSignals handlers do not double-fire.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

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
            WireOperationalOrderPlaced::class,
        ],
        OrderPaymentProofReceived::class => [
            SendOrderPaymentProofReceivedNotification::class,
            NotifyStaffPaymentProofReceived::class,
            WireOperationalPaymentProofReceived::class,
        ],
        OrderPaymentProofRejected::class => [
            SendOrderPaymentProofRejectedNotification::class,
            WireOperationalPaymentProofRejected::class,
        ],
        OrderStatusChanged::class => [
            SendOrderStatusChangedNotification::class,
            NotifyStaffOrderStatusChanged::class,
            QualifyReferralOnPaymentConfirmed::class,
            WireOperationalOrderStatusChanged::class,
            [WireDiningRealtimeSignals::class, 'handleOrderStatusChanged'],
        ],
        OrderPreparationStatusChanged::class => [
            NotifyStaffOrderPreparationStatusChanged::class,
            WireOperationalOrderPreparationStatusChanged::class,
            [WireDiningRealtimeSignals::class, 'handlePreparationStatusChanged'],
        ],
        DiningRoundPlaced::class => [
            WireOperationalDiningRoundPlaced::class,
            [WireDiningRealtimeSignals::class, 'handleRoundPlaced'],
        ],
        DiningSessionOpened::class => [
            [WireDiningRealtimeSignals::class, 'handleSessionOpened'],
        ],
        DiningSessionClosed::class => [
            [WireDiningRealtimeSignals::class, 'handleSessionClosed'],
        ],
        DiningSessionReopened::class => [
            [WireDiningRealtimeSignals::class, 'handleSessionReopened'],
        ],
        DiningBillReady::class => [
            SendDiningBillReadyNotification::class,
            WireOperationalDiningBillReady::class,
            [WireDiningRealtimeSignals::class, 'handleBillReady'],
        ],
        DiningPaymentConfirmed::class => [
            SendDiningPaymentConfirmedNotification::class,
            QualifyReferralOnDiningPaymentConfirmed::class,
            WireOperationalDiningPaymentConfirmed::class,
            [WireDiningRealtimeSignals::class, 'handlePaymentConfirmed'],
        ],
        DiningPaymentProofReceived::class => [
            WireOperationalDiningPaymentProofReceived::class,
            [WireDiningRealtimeSignals::class, 'handlePaymentProofReceived'],
        ],
        DiningPaymentProofRejected::class => [
            WireOperationalDiningPaymentProofRejected::class,
            [WireDiningRealtimeSignals::class, 'handlePaymentProofRejected'],
        ],
        OrderCashReceived::class => [
            SendOrderCashReceivedNotification::class,
            QualifyReferralOnCashReceived::class,
        ],
        IngredientStockStatusChanged::class => [
            NotifyStaffIngredientStockStatusChanged::class,
            WireOperationalIngredientStockStatusChanged::class,
        ],
        InventoryRefillRequestCreated::class => [
            [NotifyStaffInventoryRefillRequest::class, 'handleCreated'],
            [WireOperationalInventoryRefillRequest::class, 'handleCreated'],
        ],
        InventoryRefillRequestStatusChanged::class => [
            [NotifyStaffInventoryRefillRequest::class, 'handleStatusChanged'],
            [WireOperationalInventoryRefillRequest::class, 'handleStatusChanged'],
        ],
    ];
}
