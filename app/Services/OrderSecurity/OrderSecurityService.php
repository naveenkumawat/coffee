<?php

namespace App\Services\OrderSecurity;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WebsiteSettingKey;
use App\Exceptions\OrderSecurityException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Repositories\WebsiteSetting\WebsiteSettingRepositoryInterface;
use App\Transfers\Checkout\CheckoutTransferInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class OrderSecurityService implements OrderSecurityServiceInterface
{
    public function __construct(
        protected WebsiteSettingRepositoryInterface $settings,
    ) {}

    public function config(): array
    {
        $values = $this->settings->keyedValues();

        return [
            'enabled' => $this->toBool($values->get(WebsiteSettingKey::OrderSecurityEnabled->value), true),
            'max_open_unpaid_orders' => $this->toInt($values->get(WebsiteSettingKey::OrderSecurityMaxOpenUnpaidOrders->value), 2, 1, 20),
            'max_orders_per_hour' => $this->toInt($values->get(WebsiteSettingKey::OrderSecurityMaxOrdersPerHour->value), 5, 1, 60),
            'checkout_attempts_per_10_minutes' => $this->toInt($values->get(WebsiteSettingKey::OrderSecurityCheckoutAttemptsPer10Minutes->value), 5, 1, 60),
            'payment_proof_attempts_per_15_minutes' => $this->toInt($values->get(WebsiteSettingKey::OrderSecurityPaymentProofAttemptsPer15Minutes->value), 5, 1, 60),
            'duplicate_order_window_minutes' => $this->toInt($values->get(WebsiteSettingKey::OrderSecurityDuplicateOrderWindowMinutes->value), 3, 1, 30),
        ];
    }

    public function assertCustomerMayOrder(User $customer): void
    {
        if (! $customer->ordering_blocked) {
            return;
        }

        $this->logRejection('ordering_blocked', $customer);

        throw new OrderSecurityException(
            'ordering_blocked',
            'Ordering is currently unavailable for this account. Please contact the cafe.',
        );
    }

    public function assertCheckoutAttemptAllowed(User $customer): void
    {
        $config = $this->config();

        if (! $config['enabled']) {
            return;
        }

        $key = $this->checkoutAttemptKey($customer);
        $max = $config['checkout_attempts_per_10_minutes'];

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->logRejection('rate_limit', $customer);

            throw new OrderSecurityException(
                'rate_limit',
                'Too many order attempts. Please try again shortly.',
                Response::HTTP_TOO_MANY_REQUESTS,
                'checkout',
            );
        }

        RateLimiter::hit($key, 600);
    }

    public function assertOpenUnpaidLimit(User $customer): void
    {
        $config = $this->config();

        if (! $config['enabled']) {
            return;
        }

        $max = $config['max_open_unpaid_orders'];
        $count = $this->countOpenUnpaidOrders($customer);

        if ($count < $max) {
            return;
        }

        $this->logRejection('pending_limit', $customer, ['open_unpaid' => $count, 'max' => $max]);

        throw new OrderSecurityException(
            'pending_limit',
            'You already have '.$max.' orders awaiting payment. Complete or cancel one before placing another order.',
        );
    }

    public function assertOrderCreateRateLimit(User $customer): void
    {
        $config = $this->config();

        if (! $config['enabled']) {
            return;
        }

        $key = $this->orderCreateKey($customer);
        $max = $config['max_orders_per_hour'];

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->logRejection('rate_limit', $customer);

            throw new OrderSecurityException(
                'rate_limit',
                'Too many order attempts. Please try again shortly.',
                Response::HTTP_TOO_MANY_REQUESTS,
                'checkout',
            );
        }
    }

    public function countOpenUnpaidOrders(User $customer): int
    {
        return (int) Order::query()
            ->where('customer_id', $customer->getKey())
            ->where('payment_status', '!=', PaymentStatus::Confirmed->value)
            ->whereNotIn('status', [
                OrderStatus::Cancelled->value,
                OrderStatus::Rejected->value,
                OrderStatus::Completed->value,
            ])
            ->count();
    }

    public function findRecentDuplicate(User $customer, CheckoutTransferInterface $data, array $context): ?Order
    {
        $config = $this->config();

        if (! $config['enabled']) {
            return null;
        }

        $fingerprint = $this->fingerprint($customer, $data, $context);
        $orderId = Cache::get($this->fingerprintCacheKey($customer, $fingerprint));

        if (! is_numeric($orderId)) {
            return null;
        }

        $order = Order::query()
            ->whereKey((int) $orderId)
            ->where('customer_id', $customer->getKey())
            ->first();

        if ($order === null) {
            return null;
        }

        $this->logRejection('duplicate', $customer, ['existing_order_id' => $order->getKey()]);

        return $order;
    }

    public function rememberOrderFingerprint(User $customer, CheckoutTransferInterface $data, array $context, Order $order): void
    {
        $config = $this->config();

        if (! $config['enabled']) {
            return;
        }

        $fingerprint = $this->fingerprint($customer, $data, $context);

        Cache::put(
            $this->fingerprintCacheKey($customer, $fingerprint),
            $order->getKey(),
            now()->addMinutes($config['duplicate_order_window_minutes']),
        );
    }

    public function hitSuccessfulOrderCreate(User $customer): void
    {
        $config = $this->config();

        if (! $config['enabled']) {
            return;
        }

        RateLimiter::hit($this->orderCreateKey($customer), 3600);
    }

    public function assertPaymentProofUploadAllowed(User $customer, Order $order): void
    {
        $config = $this->config();

        if (! $config['enabled']) {
            return;
        }

        $key = 'payment-proof-upload:'.$customer->getKey().':'.$order->getKey();
        $max = $config['payment_proof_attempts_per_15_minutes'];

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->logRejection('rate_limit', $customer, ['order_id' => $order->getKey(), 'scope' => 'payment_proof']);

            throw new OrderSecurityException(
                'rate_limit',
                'Too many payment proof uploads. Please try again shortly.',
                Response::HTTP_TOO_MANY_REQUESTS,
                'payment_proof',
            );
        }

        RateLimiter::hit($key, 900);
    }

    /**
     * @param  array{cart: Cart, summary: array<string, mixed>}  $context
     */
    protected function fingerprint(User $customer, CheckoutTransferInterface $data, array $context): string
    {
        /** @var Cart $cart */
        $cart = $context['cart'];
        $summary = $context['summary'];

        $items = $cart->items
            ->map(fn (CartItem $item): array => [
                'variant' => (int) $item->product_variant_id,
                'qty' => (int) $item->quantity,
            ])
            ->sortBy('variant')
            ->values()
            ->all();

        $payload = [
            'customer' => (int) $customer->getKey(),
            'items' => $items,
            'fulfilment' => (string) ($data->getFulfilmentMethod() ?? ''),
            'payment' => (string) ($data->getPaymentMethod() ?? ''),
            'table' => $data->getCafeTableId(),
            'delivery' => trim((string) ($data->getDeliveryAddress() ?? '')),
            'total' => (string) ($summary['total'] ?? ''),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    protected function fingerprintCacheKey(User $customer, string $fingerprint): string
    {
        return 'order_security:fingerprint:'.$customer->getKey().':'.$fingerprint;
    }

    protected function checkoutAttemptKey(User $customer): string
    {
        return 'order_security:checkout_attempt:'.$customer->getKey();
    }

    protected function orderCreateKey(User $customer): string
    {
        return 'order_security:order_create:'.$customer->getKey();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logRejection(string $code, User $customer, array $context = []): void
    {
        Log::info('Order security rejection.', array_merge([
            'code' => $code,
            'customer_id' => $customer->getKey(),
        ], $context));
    }

    protected function toBool(mixed $raw, bool $default): bool
    {
        if ($raw === null || $raw === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    protected function toInt(mixed $raw, int $default, int $min, int $max): int
    {
        if (! is_numeric($raw)) {
            return $default;
        }

        $value = (int) $raw;

        return max($min, min($max, $value));
    }
}
