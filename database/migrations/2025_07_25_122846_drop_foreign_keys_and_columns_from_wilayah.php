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
        Schema::table('kecamatans', function (Blueprint $table) {
            if(Schema::hasColumn('kecamatan', 'kabupaten_id')) {
                // Drop foreign key constraint if it exists
                $table->dropForeign(['kabupaten_id']);
                $table->dropColumn('kabupaten_id');
            }
        });

        Schema::table('kabupatens', function (Blueprint $table) {
            if(Schema::hasColumn('kabupaten', 'province_id')) {
                // Drop foreign key constraint if it exists
                $table->dropForeign(['province_id']);
                $table->dropColumn('province_id');
            }
        });

        Schema::table('user_addresses', function (Blueprint $table) {
            if(Schema::hasColumn('user_addresses', 'province_id')) {
                // Drop foreign key constraint if it exists
                $table->dropForeign(['province_id']);
                $table->dropColumn('province_id');
            }
            if(Schema::hasColumn('user_addresses', 'kabupaten_id')) {
                // Drop foreign key constraint if it exists
                $table->dropForeign(['kabupaten_id']);
                $table->dropColumn('kabupaten_id');
            }
            if(Schema::hasColumn('user_addresses', 'kecamatan_id')) {
                // Drop foreign key constraint if it exists
                $table->dropForeign(['kecamatan_id']);
                $table->dropColumn('kecamatan_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wilayah', function (Blueprint $table) {
            //
        });
    }
};
