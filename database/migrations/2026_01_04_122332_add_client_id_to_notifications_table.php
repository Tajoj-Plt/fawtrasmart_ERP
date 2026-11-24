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
        Schema::table('notifications', function (Blueprint $table) {
            // إضافة حقل العميل للإشعارات المرتبطة بعميل معين
            if (! Schema::hasColumn('notifications', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('receiver_id');
            }
            // إضافة فهرس للبحث السريع
            if (! SchemaGuard::hasIndex('notifications', 'notifications_client_id_index')) {
                $table->index('client_id');
            }
            // إضافة علاقة مع جدول العملاء
            if (! SchemaGuard::hasForeignKey('notifications', 'notifications_client_id_foreign')) {
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropIndex(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
