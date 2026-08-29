<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Repositories\Order\OrderRepositoryInterface;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orders,
    ) {}

    public function index(): View
    {
        return view('customer.orders.index', [
            'orders' => $this->orders->paginateForCustomer(request()->user()),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        return view('customer.orders.show', [
            'order' => $order->load(['items', 'statusHistory']),
        ]);
    }
}
