<?php

use App\Support\SchemaGuard;
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
        Schema::table('shipping_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_methods', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('sort_order');
            }
            if (! SchemaGuard::hasForeignKey('shipping_methods', 'shipping_methods_account_id_foreign')) {
                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
