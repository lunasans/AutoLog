<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historical receipts rarely come with the distance driven, so a fueling
     * may now be recorded without one - matching what repairs already allow.
     */
    public function up(): void
    {
        Schema::table('fuelings', function (Blueprint $table) {
            $table->integer('odometer_reading')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fuelings', function (Blueprint $table) {
            $table->integer('odometer_reading')->nullable(false)->change();
        });
    }
};
