<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpr_records', function (Blueprint $table) {
            // Drop composite index only if it exists
            $indexes = collect(DB::select("SHOW INDEX FROM cpr_records"))
                ->pluck('Key_name')->toArray();

            if (in_array('cpr_records_filename_folder_path_unique', $indexes)) {
                $table->dropUnique(['filename', 'folder_path']);
            }

            // Add unique on filename only if not already there
            if (!in_array('cpr_records_filename_unique', $indexes)) {
                // Remove duplicate rows first — keep lowest id per filename
                DB::statement('
                    DELETE r1 FROM cpr_records r1
                    INNER JOIN cpr_records r2
                    WHERE r1.id > r2.id AND r1.filename = r2.filename
                ');
                $table->unique('filename');
            }
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