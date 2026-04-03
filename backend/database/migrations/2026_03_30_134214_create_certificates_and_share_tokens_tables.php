<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->string('file_name', 255)->nullable();
            $table->string('file_type', 50)->nullable();
            $table->dateTime('upload_date')->nullable();
            $table->string('file_size', 50)->nullable();
            $table->string('hash', 255)->nullable();
            $table->string('preview_url', 500)->nullable();
            $table->string('download_url', 500)->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
        });

        Schema::create('share_tokens', function (Blueprint $table) {
            $table->string('token', 64)->primary();
            $table->uuid('journal_entry_id');
            $table->uuid('created_by');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_tokens');
        Schema::dropIfExists('journal_certificates');
    }
};
