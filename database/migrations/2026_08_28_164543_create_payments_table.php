<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained('reservations')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('method', 30);
            $table->string('status', 30);

            $table->string('provider', 30)->nullable();

            $table->string('provider_preference_id')
                ->nullable()
                ->index();

            $table->string('provider_payment_id')
                ->nullable()
                ->index();

            $table->string('external_reference')
                ->unique();

            $table->text('checkout_url')
                ->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index([
                'reservation_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
