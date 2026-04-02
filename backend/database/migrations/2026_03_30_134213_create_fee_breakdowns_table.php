<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_breakdowns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->decimal('base_notarial_fee', 10, 2)->nullable();
            $table->decimal('service_fee', 10, 2)->nullable();
            $table->decimal('travel_fee', 10, 2)->nullable();
            $table->decimal('convenience_fee', 10, 2)->nullable();
            $table->decimal('rush_fee', 10, 2)->nullable();
            $table->decimal('holiday_fee', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('notary_share', 10, 2)->nullable();
            $table->decimal('company_share', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_breakdowns');
    }
};
