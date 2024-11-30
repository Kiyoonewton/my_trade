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
        Schema::create('odd_or_evens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->unsignedInteger('matchday_id');
            $table->string('home');
            $table->string('away');
            $table->decimal('odd', 5, 2);
            $table->decimal('even', 5, 2);
            $table->unsignedTinyInteger('result');
            $table->unsignedBigInteger('match_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odd_or_evens');
    }
};
