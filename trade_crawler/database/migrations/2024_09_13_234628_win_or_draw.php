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
        Schema::create('win_or_draws', function (Blueprint $table): void {
            $table->id();
            $table->string('season_id');
            $table->unsignedInteger('matchday_id');
            $table->string('home');
            $table->string('away');
            $table->decimal('over', 5, 2);
            $table->decimal('under', 5, 2);
            $table->unsignedTinyInteger('result');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('win_or_draw');
    }
};
