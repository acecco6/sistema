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
        Schema::create('court_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId("branch_id")->constrained("branches")->cascadeOnDelete();
            $table->foreignId("tipo_court_id")->constrained("tipos_court")->cascadeOnDelete();
            $table->decimal("price", 10, 2);
            $table->boolean("active")->default(true);
            $table->timestamps();

            $table->unique(["branch_id", "tipo_court_id"]);
            $table->index("branch_id");
            $table->index("tipo_court_id");
            $table->index("active");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_prices');
    }
};
