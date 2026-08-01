<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Assign existing cars to the first user so nothing becomes orphaned.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId) {
            DB::table('cars')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        // Plates are only unique per owner, not globally.
        Schema::table('cars', function (Blueprint $table) {
            $table->dropUnique('cars_license_plate_unique');
            $table->unique(['user_id', 'license_plate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'license_plate']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique('license_plate');
        });
    }
};
