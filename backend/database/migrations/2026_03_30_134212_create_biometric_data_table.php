<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_data', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('signer_id');
            $table->text('signature_image')->nullable();
            $table->text('thumbprint_image')->nullable();
            $table->string('biometric_match_hash')->nullable();
            $table->string('capture_device_id')->nullable();
            $table->string('capture_location')->nullable();
            $table->timestamps();

            $table->foreign('signer_id')->references('id')->on('signers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_data');
    }
};
