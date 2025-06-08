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
            $table->string('nomor_induk_kependudukan', 16)->nullable()->after('bank_account_holder_name');
            $table->string('foto_ktp')->nullable()->after('nomor_induk_kependudukan');
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
