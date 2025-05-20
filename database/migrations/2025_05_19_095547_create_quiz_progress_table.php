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
        Schema::create('quiz_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('modul_id')->constrained()->onDelete('cascade');

            $table->enum('level', ['easy', 'medium', 'hard']);
            $table->boolean('isLocked')->default(true);
            $table->boolean('isCompleted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'modul_id', 'level'], 'user_quiz_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_progress');
    }
};
