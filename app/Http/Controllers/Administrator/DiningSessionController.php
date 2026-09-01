<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\DiningSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\DiningSession;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Invoice\DiningInvoiceServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('administrator.dining-sessions.index', [
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
            'drafts.productVariant.product',
        ]);

        return view('administrator.dining-sessions.show', [
            'session' => $diningSession,
            'bill' => $this->dining->runningBill($diningSession),
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
            $data['note'] ?? 'Reopened by administrator.',
        );

        return back()->with('status', 'Dining session reopened.');
    }

    public function invoice(DiningSession $diningSession): Response
    {
        $this->authorize('view', $diningSession);

        return $this->invoices->downloadPdf($diningSession);
    }
}
