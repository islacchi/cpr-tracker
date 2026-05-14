<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpr_records', function (Blueprint $table) {
            // Remove old composite unique constraint
            $table->dropUnique(['filename', 'folder_path']);

            // Add new unique constraint on filename only
            $table->unique('filename');
        });
    }

    public function down(): void
    {
        Schema::table('cpr_records', function (Blueprint $table) {
            $table->dropUnique(['filename']);
            $table->unique(['filename', 'folder_path']);
        });
    }
};