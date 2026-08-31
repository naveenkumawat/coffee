<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\Invoice\OrderInvoiceServiceInterface;

class OrderPolicy
{
    public function __construct(
        protected OrderInvoiceServiceInterface $invoices,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->canViewOrders() || $user->hasRole('customer');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->canViewOrders()
            || ($user->hasRole('customer') && (int) $order->customer_id === (int) $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->canManageOrders();
    }

    public function transition(User $user, Order $order): bool
    {
        return $user->canManageOrders() || $user->canOperateOrders();
    }

    public function uploadPaymentProof(User $user, Order $order): bool
    {
        return $user->hasRole('customer')
            && (int) $order->customer_id === (int) $user->getKey()
            && $order->canUploadPaymentProof();
    }

    public function rejectPaymentProof(User $user, Order $order): bool
    {
        return $user->canManageOrders();
    }

    public function markCashReceived(User $user, Order $order): bool
    {
        return ($user->canManageOrders() || $user->canOperateOrders())
            && $order->isCashPayment();
    }

    public function viewPaymentProof(User $user, Order $order): bool
    {
        return ($user->canManageOrders() && $order->hasPaymentProof())
            || ($user->hasRole('customer')
                && (int) $order->customer_id === (int) $user->getKey()
                && $order->hasPaymentProof());
    }

    public function downloadInvoice(User $user, Order $order): bool
    {
        return $user->hasRole('customer')
            && (int) $order->customer_id === (int) $user->getKey()
            && $this->invoices->isAvailable($order);
    }

    public function printInvoice(User $user, Order $order): bool
    {
        return $user->canManageOrders();
    }
}
