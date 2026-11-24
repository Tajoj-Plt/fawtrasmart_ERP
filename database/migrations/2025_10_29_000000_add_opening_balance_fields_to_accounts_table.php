<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOpeningBalanceFieldsToAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'opening_balance')) {
                $table->decimal('opening_balance', 15, 2)->default(0)->after('balance');
            }
            if (! Schema::hasColumn('accounts', 'opening_balance_type')) {
                $table->enum('opening_balance_type', ['debit', 'credit'])->nullable()->after('opening_balance');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'opening_balance_type']);
        });
    }
}