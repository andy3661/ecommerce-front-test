<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('status')->default('pending'); // pending, processing, shipped, delivered, cancelled, refunded
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded, partially_refunded
            $table->string('fulfillment_status')->default('unfulfilled'); // unfulfilled, partial, fulfilled
            
            // Customer Information
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->json('billing_address');
            $table->json('shipping_address');
            
            // Pricing
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            
            // Shipping
            $table->string('shipping_method')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            // Payment
            $table->string('payment_method')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Additional
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['order_number']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_status']);
            $table->index(['customer_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};