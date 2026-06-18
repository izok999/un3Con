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
        Schema::create('academic_units', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->string('address')->nullable();
            $table->json('office_hours')->nullable();
            $table->json('phone_numbers')->nullable();
            $table->json('email_addresses')->nullable();
            $table->string('website_url')->nullable();
            $table->string('image_url')->nullable();
            $table->json('legacy_sede_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_units');
    }
};
