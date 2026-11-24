<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsOpeningBalanceToJournalEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'is_opening_balance')) {
                $table->boolean('is_opening_balance')->default(false)->after('status');
            }
            
            // Add index for better query performance
            if (! SchemaGuard::hasIndex('journal_entries', 'journal_entries_is_opening_balance_index')) {
                $table->index('is_opening_balance');
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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['is_opening_balance']);
            $table->dropColumn('is_opening_balance');
        });
    }
}