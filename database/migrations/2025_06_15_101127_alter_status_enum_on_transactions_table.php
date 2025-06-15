<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlterStatusEnumOnTransactionsTable extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE transactions MODIFY status ENUM('pending', 'paid', 'cancelled', 'expired', 'completed') DEFAULT 'pending'");
    }

    public function down()
    {
        // Balik ke enum sebelumnya (jika perlu rollback)
        DB::statement("ALTER TABLE transactions MODIFY status ENUM('pending', 'paid', 'cancelled', 'expired') DEFAULT 'pending'");
    }
}
