<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderCreateRequest;
use App\Http\Requests\Order\OrderIndexRequest;
use App\Http\Requests\Order\OrderPaymentProofRejectRequest;
use App\Http\Requests\Order\OrderStatusUpdateRequest;
use App\Models\Order;
use App\Parsers\Order\OrderParserInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use App\Services\Order\OrderServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->route('administrator.orders.show', $order)
            ->with('status', 'Order status updated successfully.');
    }

    public function markCashReceived(Order $order): RedirectResponse
    {
        $this->authorize('markCashReceived', $order);

        $this->service->markCashReceived($order, request()->user('admin'));

        return redirect()
            ->route('administrator.orders.show', $order)
            ->with('status', 'Cash marked as received.');
    }

    public function rejectPaymentProof(OrderPaymentProofRejectRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('rejectPaymentProof', $order);

        $this->service->rejectPaymentProof(
            $order,
            $request->user('admin'),
            $request->validated('notes'),
        );

        return redirect()
            ->route('administrator.orders.show', $order)
            ->with('status', 'Payment proof replacement requested.');
    }

    public function paymentProof(Order $order): StreamedResponse
    {
        $this->authorize('viewPaymentProof', $order);

        abort_unless($order->hasPaymentProof(), 404);

        $disk = $order->payment_proof_disk ?: 'local';
        $path = (string) $order->payment_proof_path;

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response(
            $path,
            basename($path),
            [
                'Content-Type' => $order->payment_proof_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.basename($path).'"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
        );
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

    protected function defaultItems(): array
    {
        return [
            ['product_variant_id' => null, 'quantity' => null],
            ['product_variant_id' => null, 'quantity' => null],
            ['product_variant_id' => null, 'quantity' => null],
        ];
    }
}
