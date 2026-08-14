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
        Schema::table('volumes', function (Blueprint $table) {
            $table->unsignedInteger('lower_limit')->nullable(); // feet
            $table->unsignedInteger('upper_limit')->nullable(); // feet
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volumes', function (Blueprint $table) {
            $table->dropColumn(['lower_limit', 'upper_limit']);
        });
    }
};
