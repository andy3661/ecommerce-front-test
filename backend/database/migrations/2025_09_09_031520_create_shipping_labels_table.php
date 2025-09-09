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
        Schema::create('shipping_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('tracking_number')->unique();
            $table->string('carrier');
            $table->string('service_type');
            $table->string('service_name');
            $table->decimal('cost', 10, 2);
            $table->string('currency', 3)->default('COP');
            $table->enum('status', [
                'created',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'returned',
                'cancelled',
                'exception'
            ])->default('created');
            $table->string('label_url')->nullable();
            $table->text('label_data')->nullable(); // Base64 encoded label
            $table->json('sender_info');
            $table->json('recipient_info');
            $table->json('package_info');
            $table->decimal('weight', 8, 3); // kg
            $table->json('dimensions'); // length, width, height in cm
            $table->decimal('declared_value', 10, 2);
            $table->boolean('insurance')->default(false);
            $table->decimal('insurance_cost', 8, 2)->nullable();
            $table->boolean('signature_required')->default(false);
            $table->date('estimated_delivery_date')->nullable();
            $table->datetime('actual_delivery_date')->nullable();
            $table->datetime('pickup_date')->nullable();
            $table->json('tracking_events')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_response')->nullable(); // Store carrier's raw response
            $table->string('external_id')->nullable(); // Carrier's internal ID
            $table->boolean('is_return')->default(false);
            $table->foreignId('return_of_label_id')->nullable()->constrained('shipping_labels');
            $table->timestamps();

            // Indexes
            $table->index(['carrier', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['estimated_delivery_date']);
            $table->index(['actual_delivery_date']);
            $table->index(['pickup_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_labels');
    }
};