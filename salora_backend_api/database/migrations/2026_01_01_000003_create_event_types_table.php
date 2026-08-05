<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('event_types', function(Blueprint $table){ $table->id(); $table->string('name_ar')->nullable(); $table->string('name_en'); $table->string('emoji',16)->nullable(); $table->boolean('is_active')->default(true); $table->integer('sort_order')->default(0); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('event_types'); } };
