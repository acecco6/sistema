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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('court_id')
                ->constrained('courts');

            /*
             * Cliente registrado al que pertenece la reserva.
             * NULL si es un invitado.
             */
            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Usuario que creó la reserva.
             *
             * Puede ser:
             * - el propio cliente
             * - Admin / Manager / Employee
             * - NULL si la reserva fue pública
             */
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Datos del invitado.
             */
            $table->string('guest_name', 100)
                ->nullable();

            $table->string('guest_email', 150)
                ->nullable();

            $table->string('guest_phone', 30)
                ->nullable();

            /*
             * Período reservado.
             */
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            /*
             * Precio final histórico.
             *
             * Este valor NO se recalcula si cambian
             * precios/promociones posteriormente.
             */
            $table->decimal('total_price', 12, 2);

            /*
             * pending
             * confirmed
             * cancelled
             * completed
             */
            $table->string('status', 30);

            /*
             * Token público para clientes sin autenticación.
             */
            $table->uuid('public_token')
                ->unique();

            $table->text('notes')
                ->nullable();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->timestamps();

            /*
             * Búsqueda de disponibilidad por Court.
             */
            $table->index([
                'court_id',
                'starts_at',
                'ends_at',
            ]);

            /*
             * Historial de reservas de un cliente registrado.
             */
            $table->index([
                'customer_user_id',
                'starts_at',
            ]);

            /*
             * También probablemente consultemos bastante
             * por Court + status.
             *
             * Más adelante veremos con EXPLAIN si hace
             * falta otro índice compuesto.
             */
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
