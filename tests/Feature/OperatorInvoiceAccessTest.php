<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorInvoiceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_print_and_download_customer_facing_invoices(): void
    {
        $operator = User::factory()->operator()->create();
        $order = $this->makePaidOrder();

        $this->actingAs($operator, 'admin')
            ->get(route('operator.orders.invoice.print', $order))
            ->assertOk()
            ->assertSee($order->order_number, false)
            ->assertDontSee('Production Cost', false)
            ->assertDontSee('Margin', false);

        $this->actingAs($operator, 'admin')
            ->get(route('operator.orders.invoice.receipt', ['order' => $order, 'width' => 80]))
            ->assertOk()
            ->assertSee('80mm', false);

        $this->actingAs($operator, 'admin')
            ->get(route('operator.orders.invoice.receipt', ['order' => $order, 'width' => 58]))
            ->assertOk()
            ->assertSee('58mm', false);

        $this->actingAs($operator, 'admin')
            ->get(route('operator.orders.invoice.pdf', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_order_show_exposes_invoice_dropdown_without_admin_financial_controls(): void
    {
        $operator = User::factory()->operator()->create();
        $order = $this->makePaidOrder();

        $this->actingAs($operator, 'admin')
            ->get(route('operator.orders.show', $order))
            ->assertOk()
            ->assertSee('Invoice', false)
            ->assertSee(route('operator.orders.invoice.print', $order), false)
            ->assertSee(route('operator.orders.invoice.pdf', $order), false)
            ->assertDontSee('Ask customer to re-upload', false)
            ->assertDontSee(route('administrator.orders.payment-proof.reject', $order), false);
    }

    public function test_barista_cannot_print_invoices_and_cannot_use_administrator_routes(): void
    {
        $barista = User::factory()->barista()->create();
        $order = $this->makePaidOrder()->fresh();
        $order->forceFill([
            'payment_proof_path' => 'payment-proofs/demo.jpg',
            'payment_status' => PaymentStatus::AwaitingReview,
            'status' => OrderStatus::PendingPayment,
        ])->save();

        $this->actingAs($barista, 'admin')
            ->get(route('operator.orders.invoice.print', $order))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.orders.invoice.print', $order))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.orders.invoice.pdf', $order))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->post(route('administrator.orders.payment-proof.reject', $order), [
                'notes' => 'Please reupload',
            ])
            ->assertForbidden();
    }

    protected function makePaidOrder(): Order
    {
        $order = Order::factory()->paymentConfirmed()->create([
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal' => '90.00',
            'total_amount' => '90.00',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Operator Latte',
            'line_subtotal' => '90.00',
            'unit_price' => '90.00',
            'quantity' => 1,
        ]);

        return $order->fresh(['items']);
    }
}
