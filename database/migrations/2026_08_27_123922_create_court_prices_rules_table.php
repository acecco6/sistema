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
        Schema::create('court_price_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('court_price_id')
                ->constrained('court_prices')
                ->cascadeOnDelete();

            $table->string('name', 100);

            $table->decimal('price', 12, 2);

            $table->unsignedTinyInteger('day_of_week')
                ->nullable();

            $table->date('specific_date')
                ->nullable();

            $table->time('start_time')
                ->nullable();

            $table->time('end_time')
                ->nullable();

            $table->unsignedInteger('priority')
                ->default(0);

            $table->dateTime('starts_at')
                ->nullable();

            $table->dateTime('ends_at')
                ->nullable();

            $table->boolean('active')
                ->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_prices_rules');
    }
};
