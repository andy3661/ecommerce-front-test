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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null'); // Verified purchase
            $table->integer('rating'); // 1-5 stars
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->json('images')->nullable(); // Review images
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'is_approved']);
            $table->index(['user_id']);
            $table->index(['rating']);
            $table->unique(['product_id', 'user_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};