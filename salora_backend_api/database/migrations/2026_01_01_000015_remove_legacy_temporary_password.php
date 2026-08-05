<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('owner_requests', 'temporary_password')) {
            Schema::table('owner_requests', function (Blueprint $table) {
                $table->dropColumn('temporary_password');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('owner_requests', 'temporary_password')) {
            Schema::table('owner_requests', function (Blueprint $table) {
                $table->string('temporary_password')->nullable();
            });
        }
    }
};
