<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقول إدارة الشيكات مع القيود المحاسبية
     */
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════
        // تحديث جدول الشيكات المستلمة
        // ═══════════════════════════════════════════════════════════════
        Schema::table('received_cheques', function (Blueprint $table) {
            // حالة الشيك
            if (!Schema::hasColumn('received_cheques', 'status')) {
                $table->string('status', 30)->default('pending')->after('attachment')
                    ->comment('pending, under_collection, collected, rejected, endorsed, deferred');
            }
            
            // ربط قيد الاستلام
            if (!Schema::hasColumn('received_cheques', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('status');
            }
            
            // ربط قيد التحصيل
            if (!Schema::hasColumn('received_cheques', 'collection_journal_entry_id')) {
                $table->unsignedBigInteger('collection_journal_entry_id')->nullable()->after('journal_entry_id');
            }
            
            // حساب البنك للإيداع
            if (!Schema::hasColumn('received_cheques', 'bank_account_id')) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('collection_account_id');
            }
            
            // ربط العميل
            if (!Schema::hasColumn('received_cheques', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('bank_account_id');
            }
            
            // بنك الساحب
            if (!Schema::hasColumn('received_cheques', 'drawer_bank')) {
                $table->string('drawer_bank', 100)->nullable()->after('payee_name');
            }
            
            // فرع بنك الساحب
            if (!Schema::hasColumn('received_cheques', 'drawer_branch')) {
                $table->string('drawer_branch', 100)->nullable()->after('drawer_bank');
            }
            
            // تاريخ التحصيل
            if (!Schema::hasColumn('received_cheques', 'collected_at')) {
                $table->timestamp('collected_at')->nullable()->after('status');
            }
            
            // تاريخ الرفض
            if (!Schema::hasColumn('received_cheques', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('collected_at');
            }
            
            // تاريخ التظهير
            if (!Schema::hasColumn('received_cheques', 'endorsed_at')) {
                $table->timestamp('endorsed_at')->nullable()->after('rejected_at');
            }
            
            // حساب المظهر إليه
            if (!Schema::hasColumn('received_cheques', 'endorsed_to_account_id')) {
                $table->unsignedBigInteger('endorsed_to_account_id')->nullable()->after('endorsed_at');
            }
            
            // إضافة الفهارس
            if (! SchemaGuard::hasIndex('received_cheques', 'idx_received_cheques_status')) {
                $table->index('status', 'idx_received_cheques_status');
            }
            if (! SchemaGuard::hasIndex('received_cheques', 'idx_received_cheques_due_date')) {
                $table->index('due_date', 'idx_received_cheques_due_date');
            }
            if (! SchemaGuard::hasIndex('received_cheques', 'idx_received_cheques_client_id')) {
                $table->index('client_id', 'idx_received_cheques_client_id');
            }
        });

        // ═══════════════════════════════════════════════════════════════
        // تحديث جدول الشيكات الصادرة
        // ═══════════════════════════════════════════════════════════════
        Schema::table('payable_cheques', function (Blueprint $table) {
            // حالة الشيك
            if (!Schema::hasColumn('payable_cheques', 'status')) {
                $table->string('status', 30)->default('issued')->after('attachment')
                    ->comment('issued, cashed, rejected, cancelled');
            }
            
            // ربط قيد الإصدار
            if (!Schema::hasColumn('payable_cheques', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('status');
            }
            
            // ربط قيد الصرف
            if (!Schema::hasColumn('payable_cheques', 'cash_journal_entry_id')) {
                $table->unsignedBigInteger('cash_journal_entry_id')->nullable()->after('journal_entry_id');
            }
            
            // ربط المورد
            if (!Schema::hasColumn('payable_cheques', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('recipient_account_id');
            }
            
            // تاريخ الصرف
            if (!Schema::hasColumn('payable_cheques', 'cashed_at')) {
                $table->timestamp('cashed_at')->nullable()->after('status');
            }
            
            // تاريخ الرفض
            if (!Schema::hasColumn('payable_cheques', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('cashed_at');
            }
            
            // تاريخ الإلغاء
            if (!Schema::hasColumn('payable_cheques', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('rejected_at');
            }
            
            // إضافة الفهارس
            if (! SchemaGuard::hasIndex('payable_cheques', 'idx_payable_cheques_status')) {
                $table->index('status', 'idx_payable_cheques_status');
            }
            if (! SchemaGuard::hasIndex('payable_cheques', 'idx_payable_cheques_due_date')) {
                $table->index('due_date', 'idx_payable_cheques_due_date');
            }
            if (! SchemaGuard::hasIndex('payable_cheques', 'idx_payable_cheques_supplier_id')) {
                $table->index('supplier_id', 'idx_payable_cheques_supplier_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('received_cheques', function (Blueprint $table) {
            $table->dropIndex('idx_received_cheques_status');
            $table->dropIndex('idx_received_cheques_due_date');
            $table->dropIndex('idx_received_cheques_client_id');
            
            $table->dropColumn([
                'status', 'journal_entry_id', 'collection_journal_entry_id',
                'bank_account_id', 'client_id', 'drawer_bank', 'drawer_branch',
                'collected_at', 'rejected_at', 'endorsed_at', 'endorsed_to_account_id'
            ]);
        });

        Schema::table('payable_cheques', function (Blueprint $table) {
            $table->dropIndex('idx_payable_cheques_status');
            $table->dropIndex('idx_payable_cheques_due_date');
            $table->dropIndex('idx_payable_cheques_supplier_id');
            
            $table->dropColumn([
                'status', 'journal_entry_id', 'cash_journal_entry_id',
                'supplier_id', 'cashed_at', 'rejected_at', 'cancelled_at'
            ]);
        });
    }
};
