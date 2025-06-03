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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->integer('total_price');
            $table->enum('status', ['pending', 'paid', 'cancelled', 'expired'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->string('shipping_service')->nullable();
            $table->integer('shipping_cost')->default(0);
            $table->text('invoice_url')->nullable();
            $table->string('midtrans_order_id')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('resi_number')->nullable();
            $table->enum('shipping_status', ['pending', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
