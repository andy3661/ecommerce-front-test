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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable(); // For guest users
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->json('product_variant')->nullable(); // Variant selection (size, color, etc.)
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2); // Current price when added
            $table->timestamps();
            
            $table->index(['user_id']);
            $table->index(['session_id']);
            $table->index(['product_id']);
            // Note: Unique constraint on JSON columns requires generated columns in MySQL
            // For now, we'll handle uniqueness at application level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};