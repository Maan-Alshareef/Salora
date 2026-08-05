<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salora_email_change_requests')) {
            Schema::create('salora_email_change_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('new_email');
                $table->string('code_hash');
                $table->timestamp('expires_at');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
                $table->index(['new_email', 'used_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salora_email_change_requests');
    }
};