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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // percentage, fixed_amount, free_shipping
            $table->decimal('value', 10, 2); // Percentage or fixed amount
            $table->decimal('minimum_amount', 10, 2)->nullable(); // Minimum order amount
            $table->decimal('maximum_discount', 10, 2)->nullable(); // Maximum discount amount
            $table->integer('usage_limit')->nullable(); // Total usage limit
            $table->integer('usage_limit_per_customer')->nullable();
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('applicable_products')->nullable(); // Specific product IDs
            $table->json('applicable_categories')->nullable(); // Specific category IDs
            $table->json('excluded_products')->nullable();
            $table->json('excluded_categories')->nullable();
            $table->timestamps();
            
            $table->index(['code', 'is_active']);
            $table->index(['is_active', 'starts_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};