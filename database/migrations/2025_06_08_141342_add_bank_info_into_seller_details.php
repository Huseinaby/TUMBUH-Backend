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
        Schema::table('seller_details', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('store_banner');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_account_holder_name')->nullable()->after('bank_account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
