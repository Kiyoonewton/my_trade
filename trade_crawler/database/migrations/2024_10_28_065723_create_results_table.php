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
            $table->string('outcome0');
            $table->string('outcome1');
            $table->string('outcome2');
            $table->string('outcome3');
            $table->string('outcome4');
            $table->string('final_outcome1');
            $table->string('final_outcome2');
            $table->string('odd');
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
