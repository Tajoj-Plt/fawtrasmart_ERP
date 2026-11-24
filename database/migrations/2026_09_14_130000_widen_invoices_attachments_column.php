<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `invoices.attachments` من `varchar(255)` إلى `text`.
 *
 * شاشتا الفاتورة والمرتجع ترسلان `attachments[]` (أكثر من ملف)، و
 * `App\Support\DocumentAttachments` تحفظ القائمة مصفوفةَ JSON من مساراتٍ
 * مثل `sales/invoices/<ulid>.pdf` — وعشرةٌ منها تتجاوز ٢٥٥ حرفاً فتُقصّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'attachments')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('attachments')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // لا رجوع إلى ٢٥٥: قائمةُ مرفقاتٍ أطول كانت ستُقصّ بصمت.
    }
};
