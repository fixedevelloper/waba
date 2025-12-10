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
        Schema::create('senders', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique();
            $table->string('name')->nullable();
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('senders')->nullOnDelete();
            $table->string('phone');
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->text('message')->nullable();
            $table->string('whatsapp_message_id')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
        });




        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->unsignedInteger('quota')->default(1000);
            $table->unsignedInteger('used')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('message_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->cascadeOnDelete();
        $table->string('phone');
        $table->enum('type', ['text','template']);
        $table->enum('status', ['sent','failed'])->default('sent');
        $table->json('response')->nullable();
        $table->text('error')->nullable();
        $table->timestamps();
    });
        Schema::create('whatsapp_tokens', function (Blueprint $table) {
            $table->id();
            $table->text('access_token');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('senders');
        Schema::dropIfExists('messages');
    }
};
