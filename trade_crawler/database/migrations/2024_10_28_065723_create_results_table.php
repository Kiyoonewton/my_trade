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
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->unsignedInteger('num');
            $table->unsignedInteger('team_num');
            $table->string('team1');
            $table->string('team2');
            $table->string('team3');
            $table->string('type');
            $table->string('matchday');
            $table->string('same_outcome');
            $table->string('same_outcome2');
            $table->string('same_outcome3');
            $table->string('against_outcome');
            $table->string('against_outcome2');
            $table->string('against_outcome3');
            $table->string('same_odd');
            $table->string('against_odd');
            $table->string('result');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
