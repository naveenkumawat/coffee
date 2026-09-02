<?php

namespace App\Http\Controllers\Operator;

use App\Enums\DiningSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\DiningSession;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Invoice\DiningInvoiceServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DiningSessionController extends Controller
{
    public function __construct(
        protected DiningSessionServiceInterface $dining,
        protected DiningInvoiceServiceInterface $invoices,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DiningSession::class);

        $status = $request->string('status')->toString();

        $sessions = DiningSession::query()
            ->with(['cafeTable', 'customer', 'openedBy'])
            ->when(
                $status !== '' && DiningSessionStatus::tryFrom($status),
                fn ($query) => $query->where('status', $status),
            )
            ->latest('opened_at')
            ->paginate(20)
            ->withQueryString();

        return view('operator.dining-sessions.index', [
            'sessions' => $sessions,
            'status' => $status,
            'statusOptions' => collect(DiningSessionStatus::cases())
                ->mapWithKeys(fn (DiningSessionStatus $case): array => [$case->value => $case->label()])
                ->all(),
        ]);
    }

    public function show(DiningSession $diningSession): View
    {
        $this->authorize('view', $diningSession);

        $diningSession->load([
            'cafeTable',
            'customer',
            'openedBy',
            'paymentReceivedBy',
            'orders.items',
            'orders.preparations',
            'drafts.productVariant.product',
        ]);

        return view('operator.dining-sessions.show', [
            'session' => $diningSession,
            'bill' => $this->dining->displayBill($diningSession),
        ]);
    }

    public function close(Request $request, DiningSession $diningSession): RedirectResponse
    {
        $this->authorize('close', $diningSession);
        $this->dining->closeSession($diningSession, $request->user('admin'));

        return back()->with('status', 'Dining session closed.');
    }

    public function reopen(Request $request, DiningSession $diningSession): RedirectResponse
    {
        $this->authorize('reopen', $diningSession);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->dining->reopenSession(
            $diningSession,
            $request->user('admin'),
            $data['note'] ?? 'Reopened by operator.',
        );

        return back()->with('status', 'Dining session reopened.');
    }

    public function changePaymentMethod(Request $request, DiningSession $diningSession): RedirectResponse
    {
        $this->authorize('changePaymentMethod', $diningSession);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:cash,manual_upi'],
        ]);

        $this->dining->changePaymentMethod(
            $diningSession,
            $data['payment_method'],
            $request->user('admin'),
        );

        return back()->with('status', 'Payment method updated.');
    }

    public function confirmPayment(Request $request, DiningSession $diningSession): RedirectResponse
    {
        $this->authorize('confirmPayment', $diningSession);
        $this->dining->confirmPayment($diningSession, $request->user('admin'));

        return back()->with('status', 'Dining UPI payment confirmed.');
    }

    public function markCashReceived(Request $request, DiningSession $diningSession): RedirectResponse
    {
        $this->authorize('markCashReceived', $diningSession);
        $this->dining->markCashReceived($diningSession, $request->user('admin'));

        return back()->with('status', 'Dining cash marked as received.');
    }

    public function rejectPaymentProof(Request $request, DiningSession $diningSession): RedirectResponse
    {
        $this->authorize('rejectPaymentProof', $diningSession);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->dining->rejectPaymentProof(
            $diningSession,
            $request->user('admin'),
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Payment proof replacement requested.');
    }

    public function paymentProof(DiningSession $diningSession): Response
    {
        $this->authorize('viewPaymentProof', $diningSession);

        $disk = $diningSession->payment_proof_disk ?: 'local';
        $path = (string) $diningSession->payment_proof_path;

        return response()->file(Storage::disk($disk)->path($path), [
            'Content-Type' => $diningSession->payment_proof_mime ?: 'application/octet-stream',
        ]);
    }

    public function invoice(DiningSession $diningSession): Response
    {
        $this->authorize('view', $diningSession);

        return $this->invoices->downloadPdf($diningSession);
    }
}
