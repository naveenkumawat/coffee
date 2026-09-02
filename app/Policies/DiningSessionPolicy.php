<?php

namespace App\Policies;

use App\Models\DiningSession;
use App\Models\User;

class DiningSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canOperateDining() || $user->canManageOrders() || $user->hasRole('customer');
    }

    public function view(User $user, DiningSession $session): bool
    {
        if ($user->canOperateDining() || $user->canManageOrders()) {
            return true;
        }

        return $user->hasRole('customer')
            && $session->customer_id !== null
            && (int) $session->customer_id === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->canOperateDining() || $user->canManageOrders() || $user->hasRole('customer');
    }

    public function update(User $user, DiningSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function placeRound(User $user, DiningSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function requestBill(User $user, DiningSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function pay(User $user, DiningSession $session): bool
    {
        return $this->view($user, $session);
    }

    /**
     * Confirm dining UPI (or staff payment confirmation) — Admin/Operator only.
     */
    public function confirmPayment(User $user, DiningSession $session): bool
    {
        return $user->canManageOrders() || $user->canOperateOrders();
    }

    /**
     * Dining cash receive — Waiter / Operator / Admin.
     */
    public function markCashReceived(User $user, DiningSession $session): bool
    {
        return $user->canOperateDining() || $user->canManageOrders() || $user->canOperateOrders();
    }

    public function changePaymentMethod(User $user, DiningSession $session): bool
    {
        return $user->canOperateDining() || $user->canManageOrders() || $user->canOperateOrders();
    }

    public function viewPaymentProof(User $user, DiningSession $session): bool
    {
        return ($user->canManageOrders() || $user->canOperateOrders()) && $session->hasPaymentProof();
    }

    public function rejectPaymentProof(User $user, DiningSession $session): bool
    {
        return $user->canManageOrders() || $user->canOperateOrders();
    }

    public function close(User $user, DiningSession $session): bool
    {
        return $user->canOperateDining() || $user->canManageOrders();
    }

    public function reopen(User $user, DiningSession $session): bool
    {
        return $user->canOperateDining() || $user->canManageOrders();
    }
}
