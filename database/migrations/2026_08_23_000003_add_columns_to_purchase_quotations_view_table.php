<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة ناقصة في `purchase_quotations_view` كان الكنترولر يكتب فيها فعلاً
 * بلا وجودها في الجدول — كل ضغطة موافقة/رفض/تراجع/تحويل كانت تفشل بخطأ SQL
 * "Unknown column" (تأكّدتُ عبر DESCRIBE على قاعدة البيانات الحيّة).
 *
 * لا cancelled_by/cancelled_at: زرّ إلغاء الموافقة أُزيل نهائياً من هذا
 * القسم بنفس قاعدة «الاعتماد نهائي» المطبَّقة على أوامر الشراء وطلبات
 * عروض الأسعار.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_quotations_view', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_quotations_view', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('created_by');
            }
            if (! SchemaGuard::hasForeignKey('purchase_quotations_view', 'purchase_quotations_view_branch_id_foreign')) {
                $table->foreign('branch_id')->references('id')->on('branches')
                    ->nullOnDelete()->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('purchase_quotations_view', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('branch_id');
            }

            if (! Schema::hasColumn('purchase_quotations_view', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('updated_by');
            }
            if (! Schema::hasColumn('purchase_quotations_view', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('purchase_quotations_view', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('purchase_quotations_view', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approval_note');
            }
            if (! Schema::hasColumn('purchase_quotations_view', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }

            if (! Schema::hasColumn('purchase_quotations_view', 'restored_by')) {
                $table->unsignedBigInteger('restored_by')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('purchase_quotations_view', 'restored_at')) {
                $table->timestamp('restored_at')->nullable()->after('restored_by');
            }

            if (! Schema::hasColumn('purchase_quotations_view', 'converted_to_po')) {
                $table->boolean('converted_to_po')->default(false)->after('restored_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_quotations_view', function (Blueprint $table) {
            $table->dropForeign('purchase_quotations_view_branch_id_foreign');
            $table->dropColumn([
                'branch_id', 'updated_by',
                'approved_by', 'approved_at', 'approval_note',
                'rejected_by', 'rejected_at',
                'restored_by', 'restored_at',
                'converted_to_po',
            ]);
        });
    }
};
