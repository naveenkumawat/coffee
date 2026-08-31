<?php

namespace App\Notifications;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\StaffNotificationChannel;
use App\Enums\StaffNotificationType;
use App\Models\User;
use App\Notifications\Concerns\BuildsCustomerMail;
use App\Services\Notification\StaffNotificationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class StaffOperationalNotification extends Notification implements ShouldQueue
{
    use BuildsCustomerMail;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public StaffNotificationType $type,
        public StaffNotificationContext $context,
        public array $channels = ['database'],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_intersect(
            $this->channels,
            [
                StaffNotificationChannel::Database->value,
                StaffNotificationChannel::Email->value,
            ],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdministratorRole();
        $content = $this->content($isAdmin);
        $severity = $this->type->severity();

        return [
            'type' => $this->type->value,
            'title' => $content['title'],
            'message' => $content['message'],
            'severity' => $severity->value,
            'url' => $this->actionUrl($notifiable),
            'audience' => $isAdmin ? 'administrator' : 'barista',
            'order_id' => $this->context->order?->getKey(),
            'order_number' => $this->context->order?->order_number,
            'ingredient_id' => $this->context->ingredient?->getKey(),
            'ingredient_name' => $this->context->ingredient?->name,
            'inventory_refill_request_id' => $this->context->refillRequest?->getKey(),
            'fulfilment_method' => $this->context->order?->fulfilment_method?->value,
            'customer_name' => $isAdmin ? ($this->context->order?->customer_name ?: null) : null,
            'total_amount' => $isAdmin && $this->context->order
                ? number_format((float) $this->context->order->total_amount, 2, '.', '')
                : null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdministratorRole();
        $content = $this->content($isAdmin);
        $name = $notifiable instanceof User ? $notifiable->name : null;
        $severity = $this->type->severity();

        return $this->customerMail(
            subject: '[Staff] '.$content['title'],
            greeting: $this->greetingFor(is_string($name) ? $name : null),
            introLines: $this->mailIntroLines($isAdmin, $content['message']),
            actionText: $content['actionText'],
            actionUrl: $this->actionUrl($notifiable),
            outroLines: [
                'This is an internal The88Coffees operations alert.',
            ],
            extra: [
                'statusLabel' => $this->type->label(),
                'statusTone' => $severity->tone(),
            ],
        );
    }

    /**
     * @return list<string>
     */
    protected function mailIntroLines(bool $forAdministrator, string $message): array
    {
        $order = $this->context->order;

        if ($order) {
            return array_values(array_filter([
                $message,
                $forAdministrator && filled($order->customer_name)
                    ? 'Customer: '.$order->customer_name
                    : null,
                $forAdministrator
                    ? 'Total: '.number_format((float) $order->total_amount, 2, '.', '')
                    : 'Fulfilment: '.$this->fulfilmentLabel(),
            ]));
        }

        return array_values(array_filter([
            $message,
            $this->context->ingredient
                ? 'Ingredient: '.$this->context->ingredient->name
                : null,
        ]));
    }

    /**
     * @return array{title: string, message: string, actionText: string}
     */
    protected function content(bool $forAdministrator): array
    {
        if ($this->context->order) {
            return $this->orderContent($forAdministrator);
        }

        return $this->inventoryContent();
    }

    /**
     * @return array{title: string, message: string, actionText: string}
     */
    protected function orderContent(bool $forAdministrator): array
    {
        $order = $this->context->order;
        $number = (string) $order->order_number;

        return match ($this->type) {
            StaffNotificationType::OrderPlaced => [
                'title' => 'New order #'.$number.' received',
                'message' => 'A new order needs payment follow-up.',
                'actionText' => 'Open order',
            ],
            StaffNotificationType::PaymentProofReceived => [
                'title' => 'Payment proof for #'.$number.' needs review',
                'message' => 'A customer submitted payment confirmation for review.',
                'actionText' => 'Open order',
            ],
            StaffNotificationType::PaymentProofResubmitted => [
                'title' => 'Payment proof for #'.$number.' was resubmitted',
                'message' => 'A replacement payment screenshot is awaiting review.',
                'actionText' => 'Open order',
            ],
            StaffNotificationType::PaymentConfirmed => [
                'title' => 'Order #'.$number.' is ready to prepare',
                'message' => 'Payment is confirmed. Accept the order when ready to start.',
                'actionText' => 'Open order',
            ],
            StaffNotificationType::OrderAccepted => [
                'title' => 'Order #'.$number.' accepted',
                'message' => 'The order was accepted and can move to preparing.',
                'actionText' => 'Open order',
            ],
            StaffNotificationType::OrderCancelled => [
                'title' => 'Order #'.$number.' cancelled',
                'message' => $forAdministrator
                    ? 'An order was cancelled.'
                    : 'An accepted/operational order was cancelled.',
                'actionText' => 'Open order',
            ],
            StaffNotificationType::OrderRejected => [
                'title' => 'Order #'.$number.' rejected',
                'message' => $forAdministrator
                    ? 'An order was rejected.'
                    : 'An accepted/operational order was rejected.',
                'actionText' => 'Open order',
            ],
            default => [
                'title' => 'Order #'.$number.' update',
                'message' => 'There is an operational update for this order.',
                'actionText' => 'Open order',
            ],
        };
    }

    /**
     * @return array{title: string, message: string, actionText: string}
     */
    protected function inventoryContent(): array
    {
        $name = (string) ($this->context->ingredient?->name ?? 'Ingredient');

        return match ($this->type) {
            StaffNotificationType::IngredientLowStock => [
                'title' => $name.' is running low',
                'message' => $name.' is running low.',
                'actionText' => 'View inventory',
            ],
            StaffNotificationType::IngredientOutOfStock => [
                'title' => $name.' is out of stock',
                'message' => $name.' is out of stock.',
                'actionText' => 'View inventory',
            ],
            StaffNotificationType::IngredientStockRestored => [
                'title' => $name.' stock restored',
                'message' => $name.' stock is back above minimum.',
                'actionText' => 'View inventory',
            ],
            StaffNotificationType::RefillRequestCreated => [
                'title' => 'Refill requested for '.$name,
                'message' => 'Refill requested for '.$name.'.',
                'actionText' => 'Review refill',
            ],
            StaffNotificationType::RefillRequestApproved => [
                'title' => 'Refill approved for '.$name,
                'message' => 'Refill request for '.$name.' was approved.',
                'actionText' => 'Open refill',
            ],
            StaffNotificationType::RefillRequestRejected => [
                'title' => 'Refill rejected for '.$name,
                'message' => 'Refill request for '.$name.' was rejected.',
                'actionText' => 'Open refill',
            ],
            StaffNotificationType::RefillRequestCompleted => [
                'title' => 'Refill completed for '.$name,
                'message' => 'Refill request for '.$name.' was completed.',
                'actionText' => 'Open refill',
            ],
            default => [
                'title' => 'Inventory update',
                'message' => 'There is an inventory operations update.',
                'actionText' => 'Open inventory',
            ],
        };
    }

    protected function actionUrl(object $notifiable): string
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdministratorRole();

        if ($this->context->order) {
            return $isAdmin
                ? route('administrator.orders.show', $this->context->order)
                : route('barista.orders.show', $this->context->order);
        }

        if ($this->context->refillRequest) {
            return $isAdmin
                ? route('administrator.inventory.refill-requests.show', $this->context->refillRequest)
                : route('barista.refill-requests.show', $this->context->refillRequest);
        }

        $status = match ($this->type) {
            StaffNotificationType::IngredientLowStock => 'low_stock',
            StaffNotificationType::IngredientOutOfStock => 'out_of_stock',
            default => null,
        };

        $params = array_filter(['stock_status' => $status]);

        return $isAdmin
            ? route('administrator.inventory.index', $params)
            : route('barista.inventory.index', $params);
    }

    protected function fulfilmentLabel(): string
    {
        return $this->context->order?->fulfilment_method === OrderFulfilmentMethod::Delivery
            ? 'Delivery'
            : 'Takeaway';
    }
}
