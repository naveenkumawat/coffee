<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dining\DiningRoundCancelRequest;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Invoice\DiningInvoiceServiceInterface;
use App\Services\Reporting\OperationalPerformanceReportingServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DiningSessionController extends Controller
{
    public function __construct(
        protected DiningSessionServiceInterface $dining,
        protected DiningInvoiceServiceInterface $invoices,
        protected OperationalPerformanceReportingServiceInterface $operationalReporting,
    ) {}

    public function index(): View
    {
        $sessions = DiningSession::query()
            ->with(['cafeTable', 'customer', 'orders'])
            ->latest('id')
            ->paginate(20);

        return view('waiter.sessions.index', compact('sessions'));
    }

    public function show(DiningSession $session): View
    {
        $this->authorize('view', $session);
        $session->load([
            'cafeTable',
            'customer',
            'drafts.productVariant.product',
            'orders.items',
            'orders.preparations',
        ]);
        $bill = $this->dining->displayBill($session);
        $variants = ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->where('is_available', true)
            ->orderBy('id')
            ->limit(100)
            ->get();
        $diningTiming = $this->operationalReporting->buildWaiterSessionTiming((int) $session->id);

        return view('waiter.sessions.show', compact('session', 'bill', 'variants', 'diningTiming'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DiningSession::class);

        $data = $request->validate([
            'cafe_table_id' => ['required', 'integer', 'exists:cafe_tables,id'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $table = CafeTable::query()->findOrFail($data['cafe_table_id']);
        $session = $this->dining->startSession(
            $table,
            null,
            $request->user('admin'),
            ['guest_count' => $data['guest_count'] ?? null],
        );

        return redirect()
            ->route('waiter.sessions.show', $session)
            ->with('status', 'Dining session started.');
    }

    public function placeRound(Request $request, DiningSession $session): RedirectResponse
    {
        $this->authorize('placeRound', $session);

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'add_ons' => ['sometimes', 'array'],
            'add_ons.*.add_on_id' => ['required', 'integer', 'distinct', 'exists:add_ons,id'],
            'add_ons.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->dining->addDraftItem(
            $session,
            (int) $data['product_variant_id'],
            (int) $data['quantity'],
            $request->user('admin'),
            $data['add_ons'] ?? [],
        );
        $this->dining->placeRound($session, $request->user('admin'), $data['customer_notes'] ?? null);

        return back()->with('status', 'Round sent to kitchen/bar.');
    }

    public function requestBill(Request $request, DiningSession $session): RedirectResponse
    {
        $this->authorize('requestBill', $session);
        $this->dining->requestBill($session, $request->user('admin'));

        return back()->with('status', 'Bill requested.');
    }

    public function markCashReceived(Request $request, DiningSession $session): RedirectResponse
    {
        $this->authorize('markCashReceived', $session);
        $this->dining->markCashReceived($session, $request->user('admin'));

        return back()->with('status', 'Cash marked as received.');
    }

    public function markRoundServed(Request $request, DiningSession $session, Order $order): RedirectResponse
    {
        $this->authorize('markServed', $session);
        $this->dining->markRoundServed($session, $order, $request->user('admin'));

        return back()->with('status', 'Round marked as served.');
    }

    public function cancelRound(
        DiningRoundCancelRequest $request,
        DiningSession $session,
        Order $order,
    ): RedirectResponse {
        $data = $request->validated();
        $this->dining->cancelRound(
            $session,
            $order,
            $request->user('admin'),
            $data['reason'] ?? null,
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Round cancelled.');
    }

    public function changePaymentMethod(Request $request, DiningSession $session): RedirectResponse
    {
        $this->authorize('changePaymentMethod', $session);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:cash,manual_upi'],
        ]);

        $this->dining->changePaymentMethod(
            $session,
            $data['payment_method'],
            $request->user('admin'),
        );

        return back()->with('status', 'Payment method updated.');
    }

    public function close(Request $request, DiningSession $session): RedirectResponse
    {
        $this->authorize('close', $session);
        $this->dining->closeSession($session, $request->user('admin'));

        return redirect()->route('waiter.tables.index')->with('status', 'Session closed.');
    }

    public function reopen(Request $request, DiningSession $session): RedirectResponse
    {
        $this->authorize('reopen', $session);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:240']]);
        $this->dining->reopenSession($session, $request->user('admin'), $data['note'] ?? null);

        return back()->with('status', 'Session reopened for more rounds.');
    }

    public function invoice(DiningSession $session): Response
    {
        $this->authorize('view', $session);

        return $this->invoices->downloadPdf($session);
    }
}
