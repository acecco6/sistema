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
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained('reservations')
                ->cascadeOnDelete();

            /*
             * Opcional porque una devolución puede representar
             * dinero cobrado de la reserva en general y no estar
             * atada a un Payment puntual.
             */
            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('status');

            /*
             * Motivo por el cual se generó la obligación
             * de devolución.
             */
            $table->string('reason')->nullable();

            /*
             * Método utilizado cuando el admin confirma
             * que efectivamente devolvió el dinero.
             *
             * Ej:
             * CASH
             * TRANSFER
             * CARD
             * OTHER
             *
             * Mientras esté PENDING puede ser null.
             */
            $table->string('method')->nullable();

            $table->text('notes')->nullable();

            /*
             * Usuario que originó/generó la devolución.
             */
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Usuario que confirmó que el dinero
             * efectivamente fue devuelto.
             */
            $table->foreignId('completed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            /*
             * Consultas frecuentes:
             *
             * - refunds pendientes
             * - refunds de una reserva
             */
            $table->index('status');
            $table->index([
                'reservation_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
