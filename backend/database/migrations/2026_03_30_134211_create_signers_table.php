<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();
            $table->string('id_issuing_authority')->nullable();
            $table->date('id_expiration_date')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signers');
    }
};
