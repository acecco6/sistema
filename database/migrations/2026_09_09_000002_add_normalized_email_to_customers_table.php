<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email_normalized', 150)->nullable()->after('email');
        });

        $duplicates = DB::table('customers')
            ->whereNotNull('email')
            ->selectRaw('LOWER(TRIM(email)) as normalized_email, COUNT(*) as total')
            ->groupByRaw('LOWER(TRIM(email))')
            ->having('total', '>', 1)
            ->pluck('normalized_email');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede activar email único de clientes. Hay emails duplicados: '.$duplicates->implode(', ')
            );
        }

        DB::table('customers')->whereNotNull('email')->orderBy('id')->each(function ($customer) {
            DB::table('customers')->where('id', $customer->id)->update([
                'email_normalized' => strtolower(trim($customer->email)),
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('email_normalized', 'customers_email_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_email_normalized_unique');
            $table->dropColumn('email_normalized');
        });
    }
};
