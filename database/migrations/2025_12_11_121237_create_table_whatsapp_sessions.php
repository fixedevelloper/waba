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
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();

            // Identifiant WhatsApp (ex: 237690000000)
            $table->string('wa_id')->unique();

            // ID du user venant de votre API de transfert
            $table->unsignedBigInteger('user_id')->nullable();

            // Étape courante du chatbot
            $table->string('step')->nullable();

            // Numéro du client (optionnel car parfois wa_id = phone)
            $table->string('phone')->nullable();

            // Champs liés à l'opération
            $table->string('origin_fond')->nullable(); // origine des fonds
            $table->string('relaction')->nullable();   // relation bénéficiaire
            $table->string('motif')->nullable();       // motif du transfert

            // Login
            $table->string('password')->nullable();
            $table->longText('token')->nullable();     // token API transfert

            // Infos transfert
            $table->string('transfer_mode')->nullable();  // mobile / bank
            $table->string('amount')->nullable();

            $table->string('beneficiary')->nullable();
            $table->unsignedBigInteger('beneficiaryId')->nullable();

            $table->string('sender')->nullable();
            $table->unsignedBigInteger('senderId')->nullable();

            // Sélection pays + ville (API transfert)
            $table->string('country')->nullable();
            $table->unsignedBigInteger('countryId')->nullable();

            $table->string('city')->nullable();
            $table->unsignedBigInteger('cityId')->nullable();

            // Opérateur Mobile Money (OM/MTN/Orange…)
            $table->unsignedBigInteger('operator_id')->nullable();

            // Expiration session WhatsApp
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_whatsapp_sessions');
    }
};
