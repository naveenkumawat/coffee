<?php

namespace App\Services\Invoice;

use App\Models\Order;
use Symfony\Component\HttpFoundation\Response;

interface OrderInvoiceServiceInterface
{
    public function isAvailable(Order $order): bool;

    public function build(Order $order): OrderInvoiceData;

    public function downloadPdf(Order $order): Response;

    /**
     * @param  '80'|'58'  $widthMm
     * @return '80'|'58'
     */
    public function normalizeThermalWidth(string|int|null $widthMm): string;
}
