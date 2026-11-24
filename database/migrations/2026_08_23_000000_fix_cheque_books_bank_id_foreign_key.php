<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصحيح المرجع الخاطئ لعمود `cheque_books.bank_id`.
 *
 * كان يشير إلى `treasuries` — جدولٌ منفصل تماماً — بينما `bank_id` في كل
 * مكانٍ آخر (نموذج `ChequeBook::bank()`، ونموذج إنشاء دفتر الشيكات الذي
 * يملأ القائمة من حسابات البنوك في شجرة الحسابات، و`ChequeService` الذي
 * يقرأه بـ`Account::find()`) معرّف حسابٍ من جدول `accounts`. فكل إنشاء
 * دفتر شيكات كان يفشل بخطأ قيد مرجعي: معرّف الحساب المُختار من الشاشة
 * غالباً لا يقابله صفٌّ بنفس الرقم في `treasuries`.
 *
 * لا صفوف حالية في `cheque_books` (تحقّقتُ قبل الكتابة)، فلا حاجة لترحيل بيانات.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cheque_books')) {
            return;
        }

        // SQLite لا يدعم إسقاط قيدٍ مرجعيٍّ بالاسم، وإعادةُ توجيه القيد لا أثر
        // لها في قاعدة الاختبارات: تُبنى من هجرة الإنشاء مباشرةً وفارغة.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('cheque_books', function (Blueprint $table) {
            $table->dropForeign('cheque_books_bank_id_foreign');
        });

        Schema::table('cheque_books', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('cheque_books', 'cheque_books_bank_id_foreign')) {
                $table->foreign('bank_id', 'cheque_books_bank_id_foreign')
                    ->references('id')->on('accounts')
                    ->onUpdate('restrict')->onDelete('restrict');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cheque_books')) {
            return;
        }

        // SQLite لا يدعم إسقاط قيدٍ مرجعيٍّ بالاسم، وإعادةُ توجيه القيد لا أثر
        // لها في قاعدة الاختبارات: تُبنى من هجرة الإنشاء مباشرةً وفارغة.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('cheque_books', function (Blueprint $table) {
            $table->dropForeign('cheque_books_bank_id_foreign');
        });

        Schema::table('cheque_books', function (Blueprint $table) {
            $table->foreign('bank_id', 'cheque_books_bank_id_foreign')
                ->references('id')->on('treasuries')
                ->onUpdate('restrict')->onDelete('cascade');
        });
    }
};
