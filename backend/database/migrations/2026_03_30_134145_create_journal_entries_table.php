<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("journal_entries", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignId("notary_id")->constrained("users");
            $table->string("venue_state")->nullable();
            $table->dateTime("execution_date")->nullable();
            $table->string("status")->default("pending");
            $table->boolean("thumbprint_waived")->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("journal_entries");
    }
};
