<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->dateTime('timestamp')->nullable();
            $table->string('initiator_name')->nullable();
            $table->string('action')->nullable();
            $table->string('resource_id')->nullable();
            $table->text('change_details_before')->nullable();
            $table->text('change_details_after')->nullable();
            $table->string('flags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
