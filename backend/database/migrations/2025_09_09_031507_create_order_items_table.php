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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('product_name'); // Snapshot of product name at time of order
            $table->string('product_sku'); // Snapshot of product SKU at time of order
            $table->json('product_variant')->nullable(); // Variant details (size, color, etc.)
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2); // Price at time of order
            $table->decimal('total_price', 10, 2); // quantity * unit_price
            $table->json('product_snapshot')->nullable(); // Full product data at time of order
            $table->timestamps();
            
            $table->index(['order_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};