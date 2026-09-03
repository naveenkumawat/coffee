<?php

namespace App\Listeners\Dining;

use App\Events\Dining\DiningBillReady;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Events\Dining\DiningPaymentProofReceived;
use App\Events\Dining\DiningPaymentProofRejected;
use App\Events\Dining\DiningRoundPlaced;
use App\Events\Dining\DiningSessionClosed;
use App\Events\Dining\DiningSessionOpened;
use App\Events\Dining\DiningSessionReopened;
use App\Events\Order\OrderPreparationStatusChanged;
use App\Events\Order\OrderStatusChanged;
use App\Services\Dining\DiningRealtimePublisher;

class WireDiningRealtimeSignals
{
    public function __construct(
        protected DiningRealtimePublisher $publisher,
    ) {}

    public function handleSessionOpened(DiningSessionOpened $event): void
    {
        $this->publisher->sessionOpened($event->session);
    }

    public function handleSessionClosed(DiningSessionClosed $event): void
    {
        $this->publisher->sessionClosed($event->session);
    }

    public function handleSessionReopened(DiningSessionReopened $event): void
    {
        $this->publisher->sessionReopened($event->session);
    }

    public function handleRoundPlaced(DiningRoundPlaced $event): void
    {
        $this->publisher->roundPlaced($event->order, $event->session);
    }

    public function handleBillReady(DiningBillReady $event): void
    {
        $this->publisher->billRequested($event->session);
    }

    public function handlePaymentConfirmed(DiningPaymentConfirmed $event): void
    {
        $this->publisher->paymentChanged($event->session, 'confirmed');
    }

    public function handlePaymentProofReceived(DiningPaymentProofReceived $event): void
    {
        $this->publisher->paymentChanged($event->session, 'proof_received');
    }

    public function handlePaymentProofRejected(DiningPaymentProofRejected $event): void
    {
        $this->publisher->paymentChanged($event->session, 'proof_rejected');
    }

    public function handlePreparationStatusChanged(OrderPreparationStatusChanged $event): void
    {
        $this->publisher->preparationChanged($event->ticket);
    }

    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        $this->publisher->roundStatusChanged($event->order);
    }
}
