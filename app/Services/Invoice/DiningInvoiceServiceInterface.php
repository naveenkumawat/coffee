<?php

namespace App\Services\Invoice;

use App\Models\DiningSession;
use Symfony\Component\HttpFoundation\Response;

interface DiningInvoiceServiceInterface
{
    public function isAvailable(DiningSession $session): bool;

    public function downloadPdf(DiningSession $session): Response;
}
