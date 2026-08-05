<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('payment_proofs') && !Schema::hasColumn('payment_proofs', 'owner_id')) {
            Schema::table('payment_proofs', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('admin_id')->constrained('users')->nullOnDelete();
            });
        }
    }
    public function down(): void {
        if (Schema::hasTable('payment_proofs') && Schema::hasColumn('payment_proofs', 'owner_id')) {
            Schema::table('payment_proofs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('owner_id');
            });
        }
    }
};
