<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('email', 150)->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('club_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->boolean('active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamp('first_reservation_at')->nullable();
            $table->timestamp('last_reservation_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'customer_id']);
            $table->index(['club_id', 'active']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('club_customer_id')->nullable()->after('court_id')
                ->constrained('club_customers')->nullOnDelete();
            $table->index(['club_customer_id', 'starts_at']);
        });

        Schema::table('fixed_reservations', function (Blueprint $table) {
            $table->foreignId('club_customer_id')->nullable()->after('club_id')
                ->constrained('club_customers')->nullOnDelete();
        });

        $this->backfillRegisteredCustomers();
    }

    private function backfillRegisteredCustomers(): void
    {
        $links = DB::table('reservations')
            ->join('courts', 'courts.id', '=', 'reservations.court_id')
            ->join('branches', 'branches.id', '=', 'courts.branch_id')
            ->whereNotNull('reservations.customer_user_id')
            ->select('reservations.customer_user_id as user_id', 'branches.club_id')
            ->distinct()->get();

        $fixedLinks = DB::table('fixed_reservations')
            ->whereNotNull('customer_user_id')
            ->select('customer_user_id as user_id', 'club_id')->distinct()->get();

        foreach ($links->concat($fixedLinks)->unique(fn ($row) => $row->user_id.'-'.$row->club_id) as $link) {
            $user = DB::table('users')->find($link->user_id);
            if ($user === null) {
                continue;
            }

            $customerId = DB::table('customers')->where('user_id', $user->id)->value('id');
            if ($customerId === null) {
                $customerId = DB::table('customers')->insertGetId([
                    'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                    'active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::table('club_customers')->insertOrIgnore([
                'club_id' => $link->club_id, 'customer_id' => $customerId, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('reservations')->whereNotNull('customer_user_id')->orderBy('id')->each(function ($reservation) {
            $clubId = DB::table('courts')->join('branches', 'branches.id', '=', 'courts.branch_id')
                ->where('courts.id', $reservation->court_id)->value('branches.club_id');
            $customerId = DB::table('customers')->where('user_id', $reservation->customer_user_id)->value('id');
            $clubCustomerId = DB::table('club_customers')->where(['club_id' => $clubId, 'customer_id' => $customerId])->value('id');
            DB::table('reservations')->where('id', $reservation->id)->update(['club_customer_id' => $clubCustomerId]);
        });

        DB::table('fixed_reservations')->whereNotNull('customer_user_id')->orderBy('id')->each(function ($fixed) {
            $customerId = DB::table('customers')->where('user_id', $fixed->customer_user_id)->value('id');
            $clubCustomerId = DB::table('club_customers')->where(['club_id' => $fixed->club_id, 'customer_id' => $customerId])->value('id');
            DB::table('fixed_reservations')->where('id', $fixed->id)->update(['club_customer_id' => $clubCustomerId]);
        });
    }

    public function down(): void
    {
        Schema::table('fixed_reservations', fn (Blueprint $table) => $table->dropConstrainedForeignId('club_customer_id'));
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['club_customer_id', 'starts_at']);
            $table->dropConstrainedForeignId('club_customer_id');
        });
        Schema::dropIfExists('club_customers');
        Schema::dropIfExists('customers');
    }
};
