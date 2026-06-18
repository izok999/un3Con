<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_academic_unit_scopes', function (Blueprint $table): void {
            $table->foreignId('assigned_by')
                ->nullable()
                ->after('sed_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')
                ->nullable()
                ->after('assigned_by');
        });
    }

    public function down(): void
    {
        Schema::table('user_academic_unit_scopes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('assigned_at');
        });
    }
};
