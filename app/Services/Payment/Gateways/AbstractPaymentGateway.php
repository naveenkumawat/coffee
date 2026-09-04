<?php

namespace App\Services\Payment\Gateways;

abstract class AbstractPaymentGateway implements PaymentGatewayContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config) {}

    public function mode(): string
    {
        $mode = strtolower(trim((string) ($this->config['mode'] ?? 'test')));

        return in_array($mode, ['live', 'production'], true) ? 'live' : 'test';
    }

    protected function filled(mixed ...$values): bool
    {
        foreach ($values as $value) {
            if (! filled($value)) {
                return false;
            }
        }

        return true;
    }

    protected function amountToPaise(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    protected function paiseToAmount(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }
}
