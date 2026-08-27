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
        Schema::create('reservation_price_segments', function (Blueprint $table) {

            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();

            /*
             * Tramo temporal al que corresponde
             * este precio.
             */

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            /*
             * Precio equivalente a 60 minutos
             * para este segmento.
             */
            $table->decimal('hourly_price', 12, 2);

            /*
             * Precio calculado realmente para
             * la duración del segmento.
             */
            $table->decimal('subtotal', 12, 2);

            /*
             * Si el segmento provino de una promoción,
             * guardamos referencia.
             *
             * nullable porque puede usar precio base.
             */
            $table->foreignId('court_price_rule_id')->nullable()->constrained('court_price_rules')->nullOnDelete();

            /*
             * Nombre histórico de la promoción.
             *
             * Aunque la regla sea eliminada/cambie,
             * sabemos qué promoción se aplicó.
             */
            $table->string('rule_name', 100)->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'starts_at',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_price_segments');
    }
};
