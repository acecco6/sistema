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
        Schema::create('fixed_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('club_id')
                ->constrained('clubs');

            /*
             * Cliente registrado.
             * NULL si la reserva fija pertenece a un invitado.
             */
            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Snapshot del invitado.
             */
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_email', 150)->nullable();
            $table->string('guest_phone', 30)->nullable();

            /*
             * Usuario administrativo que creó la reserva fija.
             */
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Vigencia de la serie.
             *
             * ends_on NULL = sin fecha final.
             */
            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            $table->boolean('active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'club_id',
                'active',
            ]);

            $table->index([
                'customer_user_id',
                'active',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_reservations');
    }
};
