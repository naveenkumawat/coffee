<?php

namespace App\Parsers\Checkout;

use App\Transfers\Checkout\CheckoutTransferInterface;

interface CheckoutParserInterface
{
    public function getTransferFromArrayData(array $checkoutData): CheckoutTransferInterface;
}
