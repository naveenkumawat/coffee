<?php

namespace App\Parsers\Cart;

use App\Transfers\Cart\CartItemTransferInterface;

interface CartParserInterface
{
    public function getTransferFromArrayData(array $cartItemData): CartItemTransferInterface;
}
