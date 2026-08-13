<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_invitations')) {
            return;
        }

        Schema::create('generated_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitation_template_id')->nullable()->constrained('invitation_templates')->nullOnDelete();
            $table->string('style', 30)->default('classic');
            $table->string('host_name', 180)->nullable();
            $table->string('location', 500)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_invitations');
    }
};
