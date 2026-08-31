<?php

namespace App\Notifications;

use App\Enums\CustomerNotificationType;
use App\Enums\OrderFulfilmentMethod;
use App\Models\Order;
use App\Notifications\Concerns\BuildsCustomerMail;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use App\Support\CustomerAppUrl;
use App\Support\CustomerEmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrderCustomerNotification extends Notification implements ShouldQueue
{
    use BuildsCustomerMail;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public CustomerNotificationType $type,
        public ?string $customerFacingReason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing('items');
        $brand = CustomerEmailBrand::snapshot();
        $name = $this->order->customer_name ?: ($notifiable->name ?? null);
        $orderUrl = CustomerAppUrl::order($this->order->getKey());
        $content = $this->content($brand);

        $mail = $this->customerMail(
            subject: $content['subject'],
            greeting: $this->greetingFor(is_string($name) ? $name : null),
            introLines: $content['intro'],
            actionText: $content['actionText'],
            actionUrl: $orderUrl,
            outroLines: $content['outro'],
            extra: [
                'order' => $this->order,
                'statusLabel' => $content['statusLabel'],
                'statusTone' => $content['statusTone'],
            ],
        );

        if ($this->type === CustomerNotificationType::PaymentConfirmed) {
            $this->attachPaymentConfirmedFiles($mail);
        }

        return $mail;
    }

    protected function attachPaymentConfirmedFiles(MailMessage $mail): void
    {
        try {
            $invoices = app(OrderInvoiceServiceInterface::class);
            $invoice = $invoices->build($this->order);
            $mail->attachData(
                $invoices->pdfBinary($this->order),
                $invoice->downloadBasename.'.pdf',
                ['mime' => 'application/pdf'],
            );
        } catch (Throwable $exception) {
            Log::warning('Payment confirmation email could not attach invoice PDF.', [
                'order_id' => $this->order->getKey(),
                'order_number' => $this->order->order_number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        if (! ($this->order->payment_method?->requiresPaymentProof() ?? false)) {
            return;
        }

        if (! $this->order->hasPaymentProof()) {
            Log::info('Payment confirmation email skipped proof attachment; proof file missing.', [
                'order_id' => $this->order->getKey(),
                'order_number' => $this->order->order_number,
            ]);

            return;
        }

        try {
            $disk = $this->order->payment_proof_disk ?: 'local';
            $path = (string) $this->order->payment_proof_path;

            if (! Storage::disk($disk)->exists($path)) {
                Log::warning('Payment confirmation email skipped proof attachment; storage path missing.', [
                    'order_id' => $this->order->getKey(),
                    'disk' => $disk,
                    'path' => $path,
                ]);

                return;
            }

            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $mime = filled($this->order->payment_proof_mime)
                ? (string) $this->order->payment_proof_mime
                : (Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream');

            $mail->attachData(
                Storage::disk($disk)->get($path),
                'payment-proof.'.$extension,
                ['mime' => $mime],
            );
        } catch (Throwable $exception) {
            Log::warning('Payment confirmation email could not attach payment proof.', [
                'order_id' => $this->order->getKey(),
                'order_number' => $this->order->order_number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $brand
     * @return array{subject: string, intro: list<string>, outro: list<string>, actionText: string, statusLabel: string, statusTone: string}
     */
    protected function content(array $brand): array
    {
        $number = (string) $this->order->order_number;
        $method = $this->order->fulfilment_method instanceof OrderFulfilmentMethod
            ? $this->order->fulfilment_method
            : OrderFulfilmentMethod::Takeaway;
        $readyLabel = $method->readyLabel();
        $tableLabel = $this->order->tableDisplayLabel();
        $business = (string) $brand['business_name'];

        return match ($this->type) {
            CustomerNotificationType::OrderPlaced => $this->order->isCashPayment()
                ? [
                    'subject' => 'Order received — #'.$number,
                    'statusLabel' => $method === OrderFulfilmentMethod::Takeaway ? 'Cash at Pickup' : 'Cash',
                    'statusTone' => 'warning',
                    'intro' => array_values(array_filter([
                        'We received your order at '.$business.'.',
                        $method === OrderFulfilmentMethod::DineIn && filled($tableLabel)
                            ? 'Table: '.$tableLabel
                            : null,
                        $method === OrderFulfilmentMethod::Takeaway
                            ? 'Pay ₹'.number_format((float) $this->order->total_amount, 2).' in cash when you collect your order.'
                            : 'Pay ₹'.number_format((float) $this->order->total_amount, 2).' in cash at the cafe.',
                    ])),
                    'actionText' => 'Track Order',
                    'outro' => [
                        'No payment screenshot is needed for cash orders.',
                    ],
                ]
                : [
                    'subject' => 'Order received — #'.$number,
                    'statusLabel' => 'Pending Payment',
                    'statusTone' => 'warning',
                    'intro' => array_values(array_filter([
                        'We received your order at '.$business.'.',
                        $method === OrderFulfilmentMethod::DineIn && filled($tableLabel)
                            ? 'Table: '.$tableLabel
                            : null,
                        'Please complete payment and upload your payment screenshot from the order page.',
                    ])),
                    'actionText' => 'View Order & Pay',
                    'outro' => [
                        'Your order stays in Pending Payment until the café confirms your transfer.',
                    ],
                ],
            CustomerNotificationType::PaymentProofReceived => [
                'subject' => 'Payment confirmation received — #'.$number,
                'statusLabel' => 'Pending Payment',
                'statusTone' => 'warning',
                'intro' => [
                    'We received your payment confirmation for order #'.$number.'.',
                    'Your order remains Pending Payment while the café reviews your screenshot.',
                ],
                'actionText' => 'View Order',
                'outro' => [
                    'You will get another email when payment is confirmed.',
                ],
            ],
            CustomerNotificationType::PaymentConfirmed => [
                'subject' => $this->order->isCashPayment()
                    ? 'Cash received — #'.$number
                    : 'Payment confirmed — #'.$number,
                'statusLabel' => $this->order->isCashPayment() ? 'Cash Received' : 'Payment Confirmed',
                'statusTone' => 'success',
                'intro' => array_values(array_filter([
                    $this->order->isCashPayment()
                        ? 'Cash payment received for order #'.$number.'.'
                        : 'Payment for order #'.$number.' has been confirmed.',
                    $method === OrderFulfilmentMethod::DineIn && filled($tableLabel)
                        ? 'Table: '.$tableLabel
                        : null,
                    $this->order->isCashPayment()
                        ? 'Your invoice is attached.'
                        : 'Your invoice'.($this->order->hasPaymentProof() ? ' and payment screenshot are' : ' is').' attached.',
                    $this->order->isCashPayment()
                        ? null
                        : 'The café will accept and prepare your order next.',
                ])),
                'actionText' => 'Track Order',
                'outro' => [],
            ],
            CustomerNotificationType::PaymentProofRejected => [
                'subject' => 'Please re-upload payment proof — #'.$number,
                'statusLabel' => 'Payment proof needs replacement',
                'statusTone' => 'danger',
                'intro' => array_values(array_filter([
                    'We need a clearer payment screenshot for order #'.$number.'.',
                    filled($this->customerFacingReason) ? $this->customerFacingReason : null,
                    'Please upload a replacement proof from your order page.',
                ])),
                'actionText' => 'Upload Payment Proof',
                'outro' => [],
            ],
            CustomerNotificationType::OrderAccepted => [
                'subject' => 'Order accepted — #'.$number,
                'statusLabel' => 'Accepted',
                'statusTone' => 'success',
                'intro' => [
                    $business.' has accepted order #'.$number.'.',
                    'Next up: your order will move to Preparing.',
                ],
                'actionText' => 'Track Order',
                'outro' => [],
            ],
            CustomerNotificationType::OrderPreparing => [
                'subject' => 'Your order is being prepared — #'.$number,
                'statusLabel' => 'Preparing',
                'statusTone' => 'neutral',
                'intro' => [
                    'Your order #'.$number.' is being prepared now.',
                ],
                'actionText' => 'Track Order',
                'outro' => [],
            ],
            CustomerNotificationType::OrderReady => [
                'subject' => match ($method) {
                    OrderFulfilmentMethod::Delivery => 'Your order is ready for delivery — #'.$number,
                    OrderFulfilmentMethod::DineIn => 'Your order is ready to serve — #'.$number,
                    default => 'Your order is ready for pickup — #'.$number,
                },
                'statusLabel' => $readyLabel,
                'statusTone' => 'success',
                'intro' => match ($method) {
                    OrderFulfilmentMethod::Delivery => array_values(array_filter([
                        'Order #'.$number.' is ready for delivery.',
                        filled($brand['delivery_disclaimer']) ? (string) $brand['delivery_disclaimer'] : null,
                    ])),
                    OrderFulfilmentMethod::DineIn => array_values(array_filter([
                        'Your order is ready to serve.',
                        filled($tableLabel) ? 'Table: '.$tableLabel : null,
                    ])),
                    default => array_values(array_filter([
                        'Order #'.$number.' is ready for pickup.',
                        filled($brand['address']) ? 'Pickup address: '.$brand['address'] : null,
                    ])),
                },
                'actionText' => 'View Order',
                'outro' => array_values(array_filter([
                    filled($brand['whatsapp']) ? 'WhatsApp: '.$brand['whatsapp'] : null,
                    filled($brand['phone']) ? 'Phone: '.$brand['phone'] : null,
                ])),
            ],
            CustomerNotificationType::OrderCompleted => [
                'subject' => 'Thank you — order #'.$number.' completed',
                'statusLabel' => 'Completed',
                'statusTone' => 'success',
                'intro' => [
                    'Order #'.$number.' is complete. Thank you for choosing '.$business.'.',
                    'You can open the order to review your items and leave a rating when available.',
                ],
                'actionText' => 'View Order',
                'outro' => [],
            ],
            CustomerNotificationType::OrderCancelled => [
                'subject' => 'Order cancelled — #'.$number,
                'statusLabel' => 'Cancelled',
                'statusTone' => 'danger',
                'intro' => array_values(array_filter([
                    'Order #'.$number.' has been cancelled.',
                    filled($this->customerFacingReason) ? $this->customerFacingReason : null,
                ])),
                'actionText' => 'View Order',
                'outro' => [
                    'If you have questions, contact the café using the details below.',
                ],
            ],
            CustomerNotificationType::OrderRejected => [
                'subject' => 'Order rejected — #'.$number,
                'statusLabel' => 'Rejected',
                'statusTone' => 'danger',
                'intro' => array_values(array_filter([
                    'Order #'.$number.' could not be fulfilled and was rejected.',
                    filled($this->customerFacingReason) ? $this->customerFacingReason : null,
                ])),
                'actionText' => 'View Order',
                'outro' => [
                    'If you have questions, contact the café using the details below.',
                ],
            ],
            default => [
                'subject' => $business.' order update — #'.$number,
                'statusLabel' => $this->order->customerStatusLabel(),
                'statusTone' => 'neutral',
                'intro' => ['There is an update for order #'.$number.'.'],
                'actionText' => 'View Order',
                'outro' => [],
            ],
        };
    }
}
