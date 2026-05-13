<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpr_records', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('folder_path');
            $table->string('registration_number')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('generic_name')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('days_remaining')->nullable();
            $table->enum('status', ['Valid', 'Expiring Soon', 'Expired', 'Parse Error', 'Unknown'])
                  ->default('Unknown');
            $table->timestamps();

            $table->index('status');
            $table->index('expiry_date');
            $table->unique(['filename', 'folder_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpr_records');
    }
};