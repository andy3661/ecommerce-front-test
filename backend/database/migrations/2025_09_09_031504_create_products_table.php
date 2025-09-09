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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->integer('inventory_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('weight_unit')->default('kg');
            $table->json('dimensions')->nullable(); // {length, width, height}
            $table->string('status')->default('active'); // active, inactive, draft
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_digital')->default(false);
            $table->boolean('requires_shipping')->default(true);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->json('images')->nullable();
            $table->json('gallery')->nullable();
            $table->json('attributes')->nullable(); // Custom attributes
            $table->json('variants')->nullable(); // Product variants
            $table->integer('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'is_featured']);
            $table->index(['sku', 'status']);
            $table->index('slug');
            $table->index('published_at');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};