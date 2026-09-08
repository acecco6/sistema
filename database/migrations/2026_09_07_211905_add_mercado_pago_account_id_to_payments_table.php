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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('mercado_pago_account_id')
                ->nullable()
                ->after('reservation_id')
                ->constrained('mercado_pago_accounts')
                ->restrictOnDelete();

            $table->index(
                [
                    'mercado_pago_account_id',
                    'provider_payment_id',
                ],
                'payments_mp_account_provider_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(
                'payments_mp_account_provider_idx'
            );

            $table->dropConstrainedForeignId(
                'mercado_pago_account_id'
            );
        });
    }
};
