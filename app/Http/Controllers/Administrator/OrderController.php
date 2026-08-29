<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderCreateRequest;
use App\Http\Requests\Order\OrderIndexRequest;
use App\Http\Requests\Order\OrderStatusUpdateRequest;
use App\Models\Order;
use App\Parsers\Order\OrderParserInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\Order\OrderServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderParserInterface $parser,
        protected OrderRepositoryInterface $orders,
        protected OrderServiceInterface $service,
    ) {}

    public function index(OrderIndexRequest $request): View
    {
        $this->authorize('viewAny', Order::class);

        $filters = $this->parser->getFilterTransferFromArrayData($request->validated());

        return view('administrator.orders.index', [
            'orders' => $this->orders->paginateForAdministrator($filters),
            'statusOptions' => OrderStatus::options(),
            'customerOptions' => $this->orders->customerOptions(),
            'baristaOptions' => $this->orders->baristaOptions(),
            'statusCounts' => $this->orders->statusCounts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Order::class);

        return view('administrator.orders.create', [
            'order' => new Order,
            'customerOptions' => $this->orders->customerOptions(),
            'variantOptions' => $this->orders->variantOptions(),
            'lineItems' => $this->defaultItems(),
        ]);
    }

    public function store(OrderCreateRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->service->store(
            $request->user('admin'),
            $this->parser->getTransferFromArrayData($request->validated()),
        );

        return redirect()
            ->route('administrator.orders.show', $order)
            ->with('status', 'Order created successfully.');
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        return view('administrator.orders.show', [
            'order' => $order->load(['customer', 'assignedBarista', 'items.recipe.lines.ingredient.brand', 'statusHistory.changedBy']),
            'availableTransitions' => $this->service->availableTransitions($order, request()->user('admin')),
        ]);
    }

    public function updateStatus(OrderStatusUpdateRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('transition', $order);

        $this->service->transition(
            $order,
            $request->user('admin'),
            $this->parser->getStatusTransitionTransferFromArrayData($request->validated()),
        );

        return redirect()
            ->route('administrator.orders.show', $order)
            ->with('status', 'Order status updated successfully.');
    }

    protected function defaultItems(): array
    {
        return [
            ['product_variant_id' => null, 'quantity' => null],
            ['product_variant_id' => null, 'quantity' => null],
            ['product_variant_id' => null, 'quantity' => null],
        ];
    }
}
