<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->morphs('otpable');
            $table->string('code', 6);
            $table->string('type'); // email, phone
            $table->string('identifier'); // email address or phone number
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Indexes for faster lookups
            $table->index(['otpable_type', 'otpable_id', 'type']);
            $table->index(['identifier', 'type']);
            $table->index('expires_at');
            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
