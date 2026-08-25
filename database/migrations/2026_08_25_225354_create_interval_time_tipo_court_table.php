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
        Schema::create('interval_time_tipo_court', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('tipo_court_id')->constrained('tipos_court')->cascadeOnDelete();
            $table->integer('interval_minutes')->default(30)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'tipo_court_id']);
            $table->unique(['branch_id', 'tipo_court_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interval_time_tipo_court');
    }
};
