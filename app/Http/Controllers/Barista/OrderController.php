<?php

namespace App\Http\Controllers\Barista;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderIndexRequest;
use App\Http\Requests\Order\OrderStatusUpdateRequest;
use App\Models\Order;
use App\Parsers\Order\OrderParserInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use App\Services\Order\OrderServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderParserInterface $parser,
        protected OrderRepositoryInterface $orders,
        protected OrderServiceInterface $service,
        protected OrderInvoiceServiceInterface $invoices,
    ) {}

    public function index(OrderIndexRequest $request): View
    {
        $this->authorize('viewAny', Order::class);

        $filters = $this->parser->getFilterTransferFromArrayData($request->validated());

        return view('barista.orders.index', [
            'orders' => $this->orders->paginateForBarista($filters),
            'statusOptions' => OrderStatus::options(),
            'baristaOptions' => $this->orders->baristaOptions(),
            'statusCounts' => $this->orders->statusCounts(),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        return view('barista.orders.show', [
            'order' => $order->load(['customer', 'assignedBarista', 'paymentReceivedBy', 'items.recipe.lines.ingredient.brand', 'statusHistory.changedBy']),
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
            ->route('barista.orders.show', $order)
            ->with('status', 'Order status updated successfully.');
    }

    public function markCashReceived(Order $order): RedirectResponse
    {
        $this->authorize('markCashReceived', $order);

        $this->service->markCashReceived($order, request()->user('admin'));

        return redirect()
            ->route('barista.orders.show', $order)
            ->with('status', 'Cash marked as received.');
    }

    public function downloadInvoice(Order $order): Response
    {
        $this->authorize('printInvoice', $order);

        return $this->invoices->downloadPdf($order);
    }

    public function printInvoice(Order $order): View
    {
        $this->authorize('printInvoice', $order);

        return view('invoices.print-a4', [
            'invoice' => $this->invoices->build($order),
            'autoPrint' => false,
        ]);
    }

    public function printReceipt(Request $request, Order $order): View
    {
        $this->authorize('printInvoice', $order);

        $widthMm = $this->invoices->normalizeThermalWidth($request->query('width'));

        return view('invoices.print-thermal', [
            'invoice' => $this->invoices->build($order),
            'widthMm' => $widthMm,
            'autoPrint' => false,
        ]);
    }
}
