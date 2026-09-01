<?php

namespace App\Http\Controllers\Barista;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderSecurity\OrderSecurityServiceInterface;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderSecurityServiceInterface $orderSecurity,
    ) {}

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load([
            'customer',
            'assignedBarista',
            'paymentReceivedBy',
            'items.recipe.lines.ingredient.brand',
            'promotions',
            'rewardRedemptions',
            'statusHistory.changedBy',
            'preparations',
            'diningSession.cafeTable',
        ]);

        $openUnpaidOrders = $order->customer
            ? $this->orderSecurity->countOpenUnpaidOrders($order->customer)
            : 0;

        return view('barista.orders.show', [
            'order' => $order,
            'availableTransitions' => [],
            'openUnpaidOrders' => $openUnpaidOrders,
        ]);
    }
}
