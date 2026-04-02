<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notary_id');
            $table->string('venue_state')->nullable();
            $table->string('venue_county')->nullable();
            $table->dateTime('execution_date')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('notarial_fee', 10, 2)->nullable();
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_name')->nullable();
            $table->string('holiday_type')->nullable();
            $table->text('document_description')->nullable();
            $table->string('risk_flag')->nullable();
            $table->string('verification_method')->nullable();
            $table->boolean('thumbprint_waived')->default(false);
            $table->string('signed_digital_act_id', 100)->nullable();
            $table->string('act_type', 100)->nullable();
            $table->string('document_title', 255)->nullable();
            $table->integer('number_of_pages')->nullable();
            $table->string('document_type', 100)->nullable();
            $table->timestamps();

            $table->foreign('notary_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
