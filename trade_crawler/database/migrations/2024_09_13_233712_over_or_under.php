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
        Schema::create('over_or_unders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->unsignedInteger('matchday_id');
            $table->string('home');
            $table->string('away');
            $table->decimal('over', 5, 2);
            $table->decimal('under', 5, 2);
            $table->unsignedTinyInteger('result');
            $table->unsignedTinyInteger('booker_prediction');
            $table->unsignedBigInteger('match_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('over_or_under');
    }
};
