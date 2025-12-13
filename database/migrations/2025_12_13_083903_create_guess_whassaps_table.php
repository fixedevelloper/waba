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
        Schema::create('guess_whassaps', function (Blueprint $table) {
            $table->id();
            $table->json('sender')->nullable();
            $table->json('beneficiary')->nullable();
            $table->foreignId('whatsapp_session_id')->nullable()->constrained('whatsapp_sessions')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guess_whassaps');
    }
};
