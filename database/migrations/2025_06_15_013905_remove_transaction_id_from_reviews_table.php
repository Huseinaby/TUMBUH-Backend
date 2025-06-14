<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTransactionIdFromReviewsTable extends Migration
{
    public function up()
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']); // jika ada foreign key
            $table->dropColumn('transaction_id');
        });
    }

    public function down()
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable();

            // Optional: tambahkan kembali foreign key jika sebelumnya ada
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
        });
    }
}
