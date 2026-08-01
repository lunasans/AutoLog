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
        Schema::table('fuelings', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('odometer_reading');
            $table->string('receipt_name')->nullable()->after('receipt_path');
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('odometer_reading');
            $table->string('receipt_name')->nullable()->after('receipt_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuelings', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'receipt_name']);
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'receipt_name']);
        });
    }
};
