<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('location');
            $table->decimal('cost', 8, 2);
            // A parking ticket often covers a stretch of time rather than a
            // moment; both ends are optional because a slip may show neither.
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_tickets');
    }
};
