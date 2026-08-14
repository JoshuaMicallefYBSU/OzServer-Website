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
        Schema::table('flight_data_records', function (Blueprint $table) {
            $table->decimal('lat', 10, 6)->nullable();
            $table->decimal('lon', 10, 6)->nullable();
            $table->integer('altitude')->nullable(); // feet
            $table->integer('ground_speed')->nullable(); // knots
            $table->unsignedSmallInteger('heading')->nullable(); // degrees
            $table->integer('vertical_rate')->nullable(); // feet per minute
            $table->boolean('on_ground')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_data_records', function (Blueprint $table) {
            $table->dropColumn([
                'lat',
                'lon',
                'altitude',
                'ground_speed',
                'heading',
                'vertical_rate',
                'on_ground',
            ]);
        });
    }
};
