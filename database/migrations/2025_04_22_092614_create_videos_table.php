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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modul_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('creator');
            $table->string('duration');
            $table->string('link');
            $table->string('thumbnail')->nullable();
            $table->string('category');
            $table->string('keyword')->nullable();
            $table->string('nextPageToken')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
