<?php

namespace App\Services\Invoice;

use App\Enums\DiningSessionStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningSession;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DiningInvoiceService implements DiningInvoiceServiceInterface
{
    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected DiningSessionServiceInterface $dining,
    ) {}

    public function isAvailable(DiningSession $session): bool
    {
        return in_array($session->status, [
            DiningSessionStatus::BillingRequested,
            DiningSessionStatus::AwaitingPayment,
            DiningSessionStatus::Paid,
            DiningSessionStatus::Closed,
        ], true) || $session->payment_status === PaymentStatus::Confirmed;
    }

    public function downloadPdf(DiningSession $session): Response
    {
        $session->loadMissing(['orders.items.addOns', 'cafeTable', 'customer', 'promotions']);

        if (! $session->hasFinalizedBill()) {
            abort(422, 'Generate the dining bill before downloading the invoice.');
        }

        $bill = $this->dining->finalizedBill($session);
        $content = $this->websiteSettings->customerContent();
        $business = $content['business'] ?? [];
        $paymentLabel = $session->payment_status === PaymentStatus::Confirmed
            ? 'PAID'
            : 'PAYMENT PENDING';

        $pdf = Pdf::loadView('invoices.dining-session', [
            'session' => $session,
            'bill' => $bill,
            'paymentLabel' => $paymentLabel,
            'cafeName' => $business['name'] ?? config('app.name'),
            'cafeAddress' => $business['address'] ?? null,
            'cafePhone' => $business['phone'] ?? null,
        ]);

        return $pdf->download('dining-'.$session->session_number.'.pdf');
    }
}
