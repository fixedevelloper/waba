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
        Schema::table('whatsapp_sessions',function (Blueprint $table){
            $table->string('sender_type')->nullable();
            $table->string('beneficiary_type')->nullable();
            $table->string('amount_send')->nullable();
            $table->string('operator_name')->nullable();
            $table->string('comment')->nullable();
            $table->string('swiftCode')->nullable();
            $table->string('accountNumber')->nullable();
            $table->json('beneficiaries')->nullable();
            $table->json('senders')->nullable();
            $table->json('relations')->nullable();
            $table->json('origins')->nullable();
            $table->json('motifs')->nullable();
            $table->json('operators')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
