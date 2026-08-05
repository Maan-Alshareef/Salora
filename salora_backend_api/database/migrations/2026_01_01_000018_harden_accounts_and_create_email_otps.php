<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing accounts were created before email verification was introduced.
        // Grandfather them so the migration does not unexpectedly lock out users.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('last_login_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('suspended_until')->nullable()->after('locked_until');
            $table->text('suspension_reason')->nullable()->after('suspended_until');
            $table->unsignedBigInteger('suspended_by')->nullable()->after('suspension_reason');
            $table->softDeletes();
            $table->index(['status', 'suspended_until']);
            $table->index('locked_until');
            $table->index('suspended_by');
        });

        // Replace the legacy password-reset-only OTP store with the unified OTP table.
        // OTP records are short-lived, so no historical business data is lost.
        Schema::dropIfExists('password_reset_otps');

        Schema::create('email_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('purpose', 40)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('resend_available_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();
            $table->index(['email', 'purpose', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otps');

        if (!Schema::hasTable('password_reset_otps')) {
            Schema::create('password_reset_otps', function (Blueprint $table) {
                $table->id();
                $table->string('email')->index();
                $table->string('code_hash');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'suspended_until']);
            $table->dropIndex(['locked_until']);
            $table->dropIndex(['suspended_by']);
            $table->dropColumn([
                'failed_login_attempts',
                'locked_until',
                'suspended_until',
                'suspension_reason',
                'suspended_by',
                'deleted_at',
            ]);
        });
    }
};
