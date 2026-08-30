<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('fulfilment_method', 32)->default('takeaway')->after('pickup_notes');
            $table->text('delivery_address')->nullable()->after('fulfilment_method');
            $table->string('delivery_phone', 50)->nullable()->after('delivery_address');
            $table->string('delivery_contact_name')->nullable()->after('delivery_phone');
            $table->text('delivery_notes')->nullable()->after('delivery_contact_name');
            $table->string('delivery_provider')->nullable()->after('delivery_notes');
            $table->decimal('delivery_fee_amount', 12, 2)->nullable()->after('delivery_provider');
            $table->string('delivery_tracking_reference')->nullable()->after('delivery_fee_amount');

            $table->string('payment_method', 32)->default('manual')->after('total_amount');
            $table->string('payment_status', 32)->default('pending')->after('payment_method');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->string('payment_proof_path')->nullable()->after('payment_reference');
            $table->string('payment_proof_disk', 32)->nullable()->after('payment_proof_path');
            $table->string('payment_proof_mime', 100)->nullable()->after('payment_proof_disk');
            $table->unsignedInteger('payment_proof_size')->nullable()->after('payment_proof_mime');
            $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_size');
            $table->text('payment_proof_rejection_notes')->nullable()->after('payment_proof_uploaded_at');

            $table->index('fulfilment_method');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['fulfilment_method']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn([
                'fulfilment_method',
                'delivery_address',
                'delivery_phone',
                'delivery_contact_name',
                'delivery_notes',
                'delivery_provider',
                'delivery_fee_amount',
                'delivery_tracking_reference',
                'payment_method',
                'payment_status',
                'payment_reference',
                'payment_proof_path',
                'payment_proof_disk',
                'payment_proof_mime',
                'payment_proof_size',
                'payment_proof_uploaded_at',
                'payment_proof_rejection_notes',
            ]);
        });
    }
};
