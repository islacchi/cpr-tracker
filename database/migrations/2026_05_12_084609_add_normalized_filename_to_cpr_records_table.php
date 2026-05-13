<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpr_records', function (Blueprint $table) {
            $table->string('normalized_filename')->nullable()->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('cpr_records', function (Blueprint $table) {
            $table->dropColumn('normalized_filename');
        });
    }
};