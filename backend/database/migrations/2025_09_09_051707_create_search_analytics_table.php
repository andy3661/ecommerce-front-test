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
        Schema::create('search_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('query', 255)->index();
            $table->date('date')->index();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('last_searched_at');
            $table->timestamps();
            
            // Unique constraint to prevent duplicate entries for same query on same date
            $table->unique(['query', 'date']);
            
            // Index for popular queries
            $table->index(['count', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_analytics');
    }
};
