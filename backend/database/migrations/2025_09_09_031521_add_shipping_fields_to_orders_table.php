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
        Schema::table('orders', function (Blueprint $table) {
            // Shipping information - these columns already exist in orders table
            // shipping_carrier, shipping_service_type, shipping_service_name, 
            // shipping_cost, shipping_currency, shipping_status already exist
            // Shipping status and tracking
            $table->string('shipping_status')->default('pending')->after('tracking_number'); // pending, processing, shipped, in_transit, delivered, failed
            $table->string('shipping_carrier')->nullable()->after('shipping_status');
            $table->string('shipping_service_type')->nullable()->after('shipping_carrier');
            $table->string('shipping_service_name')->nullable()->after('shipping_service_type');
            $table->decimal('shipping_cost', 8, 2)->nullable()->after('shipping_service_name');
            $table->string('shipping_currency', 3)->default('USD')->after('shipping_cost');
            
            // Delivery dates
            $table->date('estimated_delivery_date')->nullable()->after('shipping_currency');
            $table->datetime('actual_delivery_date')->nullable()->after('estimated_delivery_date');
            
            // Package information
            $table->decimal('package_weight', 8, 3)->nullable()->after('actual_delivery_date'); // kg
            $table->json('package_dimensions')->nullable()->after('package_weight'); // length, width, height in cm
            $table->decimal('declared_value', 10, 2)->nullable()->after('package_dimensions');
            $table->boolean('requires_signature')->default(false)->after('declared_value');
            $table->boolean('shipping_insurance')->default(false)->after('requires_signature');
            $table->decimal('insurance_cost', 8, 2)->nullable()->after('shipping_insurance');
            
            // Shipping zone and method
            $table->string('shipping_zone')->nullable()->after('insurance_cost');
            $table->boolean('free_shipping')->default(false)->after('shipping_zone');
            $table->decimal('free_shipping_threshold', 10, 2)->nullable()->after('free_shipping');
            
            // Special instructions
            $table->text('shipping_notes')->nullable()->after('free_shipping_threshold');
            $table->text('delivery_instructions')->nullable()->after('shipping_notes');
            
            // Indexes for better performance
            $table->index(['shipping_status', 'created_at']);
            $table->index(['shipping_carrier', 'shipping_status']);
            $table->index(['tracking_number']);
            $table->index(['estimated_delivery_date']);
            $table->index(['actual_delivery_date']);
            $table->index(['shipped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipping_status', 'created_at']);
            $table->dropIndex(['shipping_carrier', 'shipping_status']);
            $table->dropIndex(['tracking_number']);
            $table->dropIndex(['estimated_delivery_date']);
            $table->dropIndex(['actual_delivery_date']);
            $table->dropIndex(['shipped_at']);
            
            $table->dropColumn([
                'shipping_carrier',
                'shipping_service_type',
                'shipping_service_name',
                'shipping_cost',
                'shipping_currency',
                'shipping_status',
                'tracking_number',
                'estimated_delivery_date',
                'actual_delivery_date',
                'shipped_at',
                'shipping_address',
                'package_weight',
                'package_dimensions',
                'declared_value',
                'requires_signature',
                'shipping_insurance',
                'insurance_cost',
                'shipping_zone',
                'free_shipping',
                'free_shipping_threshold',
                'shipping_notes',
                'delivery_instructions'
            ]);
        });
    }
};