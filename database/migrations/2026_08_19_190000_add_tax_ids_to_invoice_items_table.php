<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * معرّفا الضريبة على بند المستند.
 *
 * `invoice_items` كان يحفظ النسبة وحدها (`tax_1`, `tax_2`) فيضيع **نوع**
 * الضريبة المضبوط في «إعدادات الضريبة» (`tax_sittings.type`):
 *
 *   • `excluded` — السعر بلا ضريبة، فتُضاف فوقه.
 *   • `included` — السعر شاملها، فتُستخرَج منه ولا تزيد الإجمالي.
 *
 * بلا المعرّف لا يستطيع أي عرض أو طباعة أو تقرير أن يعرف بأيّ الطريقتين
 * حُسب البند، وكانت الشاشات ترسل `tax_1_id` فعلاً بينما لا يخزّنه أحد.
 *
 * الأعمدة اختيارية: البنود القديمة تبقى `null` وتُعامَل معاملة `excluded`
 * تماماً كما كانت تُحسب يوم حُفظت، فلا تتغيّر أرقام مستند قائم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'tax_1_id')) {
                $table->unsignedBigInteger('tax_1_id')->nullable()->after('tax_1');
                if (! SchemaGuard::hasIndex('invoice_items', 'invoice_items_tax_1_id_index')) {
                    $table->index('tax_1_id', 'invoice_items_tax_1_id_index');
                }
            }

            if (! Schema::hasColumn('invoice_items', 'tax_2_id')) {
                $table->unsignedBigInteger('tax_2_id')->nullable()->after('tax_2');
                if (! SchemaGuard::hasIndex('invoice_items', 'invoice_items_tax_2_id_index')) {
                    $table->index('tax_2_id', 'invoice_items_tax_2_id_index');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            foreach (['tax_1_id', 'tax_2_id'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropIndex('invoice_items_'.$column.'_index');
                    $table->dropColumn($column);
                }
            }
        });
    }
};
