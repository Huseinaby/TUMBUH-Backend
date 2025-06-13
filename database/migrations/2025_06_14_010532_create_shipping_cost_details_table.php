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
        Schema::create('shipping_cost_details', function (Blueprint $table) {
            $table->id();
            $table->string('origin_id');
            $table->string('destination_id');
            $table->integer('weight');
            $table->string('code');
            $table->string('name');
            $table->string('service');
            $table->string('description')->nullable();
            $table->integer('cost');
            $table->string('etd')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_cost_details');
    }
};
