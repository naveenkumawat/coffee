<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

final class OrderCustomerMailRecipient
{
    /**
     * Resolve customer email and/or phone for transactional notifications.
     *
     * @return array{email: string|null, phone: string|null, customer: User|null}|null
     */
    public static function resolve(Order $order): ?array
    {
        $customer = $order->customer;
        $email = strtolower(trim((string) ($order->customer_email ?: $customer?->email)));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null;
        }

        $phone = null;
        foreach ([
            $order->customer_phone,
            $order->pickup_phone,
            $order->delivery_phone,
            $customer?->phone,
        ] as $candidate) {
            if (is_string($candidate) && PhoneNumber::toWhatsappDestination($candidate) !== null) {
                $phone = $candidate;
                break;
            }
        }

        if ($email === null && $phone === null) {
            return null;
        }

        if ($customer instanceof User && ! $customer->hasRole(UserRole::Customer)) {
            $customer = null;
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'customer' => $customer instanceof User ? $customer : null,
        ];
    }
}
