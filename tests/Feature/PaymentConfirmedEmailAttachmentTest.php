<?php

namespace Tests\Feature;

use App\Enums\CustomerNotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderCustomerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentConfirmedEmailAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_upi_payment_confirmed_email_attaches_invoice_and_proof(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create(['email' => 'upi@example.test']);
        $order = $this->makeConfirmedOrder($customer, PaymentMethod::Manual, withProof: true);

        $mail = (new OrderCustomerNotification($order, CustomerNotificationType::PaymentConfirmed))
            ->toMail($customer);

        $this->assertStringContainsString('Payment confirmed', $mail->subject);
        $html = $mail->render();
        $this->assertStringContainsString('Payment for order', $html);
        $this->assertStringContainsString('confirmed', strtolower($html));

        $names = array_column($mail->rawAttachments, 'name');
        $this->assertTrue(collect($names)->contains(fn (string $name): bool => str_ends_with($name, '.pdf')));
        $this->assertTrue(collect($names)->contains(fn (string $name): bool => str_starts_with($name, 'payment-proof.')));

        $pdf = collect($mail->rawAttachments)->first(
            fn (array $attachment): bool => str_ends_with((string) $attachment['name'], '.pdf'),
        );
        $this->assertNotNull($pdf);
        $this->assertStringStartsWith('%PDF', (string) $pdf['data']);
    }

    public function test_cash_received_email_attaches_invoice_only(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create(['email' => 'cash@example.test']);
        $order = $this->makeConfirmedOrder($customer, PaymentMethod::Cash, withProof: false);

        $mail = (new OrderCustomerNotification($order, CustomerNotificationType::PaymentConfirmed))
            ->toMail($customer);

        $this->assertStringContainsString('Cash received', $mail->subject);
        $html = $mail->render();
        $this->assertStringContainsString('Cash payment received', $html);
        $this->assertStringNotContainsString('UPI', $html);
        $this->assertStringNotContainsString('payment screenshot', strtolower($html));

        $names = array_column($mail->rawAttachments, 'name');
        $this->assertCount(1, $names);
        $this->assertTrue(str_ends_with($names[0], '.pdf'));
        $this->assertFalse(collect($names)->contains(fn (string $name): bool => str_contains($name, 'payment-proof')));
    }

    public function test_missing_proof_still_sends_invoice_attachment(): void
    {
        Storage::fake('local');
        Log::spy();

        $customer = User::factory()->customer()->create(['email' => 'missing-proof@example.test']);
        $order = $this->makeConfirmedOrder($customer, PaymentMethod::Manual, withProof: false);
        $order->forceFill([
            'payment_proof_path' => 'payment-proofs/missing.jpg',
            'payment_proof_disk' => 'local',
            'payment_proof_mime' => 'image/jpeg',
        ])->save();

        $mail = (new OrderCustomerNotification($order->fresh(), CustomerNotificationType::PaymentConfirmed))
            ->toMail($customer);

        $names = array_column($mail->rawAttachments, 'name');
        $this->assertCount(1, $names);
        $this->assertTrue(str_ends_with($names[0], '.pdf'));
    }

    protected function makeConfirmedOrder(User $customer, PaymentMethod $method, bool $withProof): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'status' => OrderStatus::PaymentConfirmed,
            'payment_method' => $method,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_confirmed_at' => now(),
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'tax_enabled_snapshot' => false,
            'taxable_amount' => '100.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'House Latte',
            'variant_name' => 'Regular',
            'unit_price' => '100.00',
            'quantity' => 1,
            'line_subtotal' => '100.00',
        ]);

        if ($withProof) {
            $path = 'payment-proofs/'.$order->id.'/proof.jpg';
            Storage::disk('local')->put($path, 'fake-proof-bytes');
            $order->forceFill([
                'payment_proof_path' => $path,
                'payment_proof_disk' => 'local',
                'payment_proof_mime' => 'image/jpeg',
                'payment_proof_size' => 16,
                'payment_proof_uploaded_at' => now(),
            ])->save();
        }

        return $order->fresh(['items']);
    }
}
