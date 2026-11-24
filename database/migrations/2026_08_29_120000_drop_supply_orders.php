<?php

use App\Support\SchemaRetire;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إزالة «أوامر التوريد» من النظام.
 *
 * القسم كلُّه أُزيل بطلب المستخدم: متحكّماته وشاشاته ومساراته وتقاريره
 * وإعداداته وصلاحياته. وهذه الهجرة تُتمّ ما لا يبلغه حذفُ الملفّات —
 * الجدول وأعمدةُ الصلاحيات وصفوفُ الحالات والترقيم.
 *
 * الجدول كان فارغاً يوم الإزالة (صفر سجلّات)، فلا بيانات تضيع. ولا مفاتيح
 * أجنبية تشير إليه ولا يشير هو إلى غيره، فيسقط وحده بلا أثر.
 *
 * **التراجع يعيد البنية لا البيانات.** `down()` تعيد بناء الجدول
 * والأعمدة كما كانت، ولا تُعيد ما كان فيها — وهو لا شيء هنا. أمّا
 * الشاشات والمتحكّمات فتُستعاد من git لا من هجرة.
 */
return new class extends Migration
{
    /** أعمدة الصلاحيات السبعة في `job_roles` */
    private const ROLE_COLUMNS = [
        'supply_orders_view_all',
        'supply_orders_add',
        'supply_orders_edit_delete_all',
        'supply_orders_edit_delete_own',
        'supply_orders_update_status',
        'supply_orders_view_own',
        'supply_orders_manage_settings',
    ];

    public function up(): void
    {
        // حالات أوامر التوريد — `statuses.section = 'supply-order'`
        if (Schema::hasTable('statuses') && Schema::hasColumn('statuses', 'section')) {
            DB::table('statuses')->where('section', 'supply-order')->delete();
        }

        // تسلسل ترقيم المستند، إن كان مسجَّلاً
        if (Schema::hasTable('serial_settings')) {
            DB::table('serial_settings')->where('section', 'supply-order')->delete();
        }

        /* صلاحيات Spatie الخاصة بالقسم — بالاسمين معاً:
           `sales.supply_orders.*` كتالوجُ الصلاحيات الحديث، و
           `supply_orders_*` الأسماءُ القديمة المشتقّة من أعمدة `job_roles`
           (`config/job_permissions.php`). ونمطان لا نمطٌ واحد، وإلّا بقيت
           صلاحياتٌ يتيمةٌ في الجدول لقسمٍ لا وجود له. */
        if (Schema::hasTable('permissions')) {
            $names = DB::table('permissions')
                ->where('name', 'like', 'sales.supply_orders.%')
                ->orWhere('name', 'like', 'supply_orders\_%')
                ->pluck('id');

            if ($names->isNotEmpty()) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->whereIn('permission_id', $names)->delete();
                }

                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->whereIn('permission_id', $names)->delete();
                }

                DB::table('permissions')->whereIn('id', $names)->delete();
            }
        }

        /* أعمدةُ `job_roles` السبعة تبقى حيث هي.
           كان هنا `dropColumn($present)`. وعمودٌ لم يعد يُقرأ لا يكلّف
           شيئاً — أمّا حذفُه فيمحو ما فيه، ويجعل هذا الملفّ «متلفاً» فتقف
           عنده شاشةُ التحديث ولا ينزل ما بعده من ترحيلات. */

        /* والجدول نفسه: تقاعدٌ لا حذف — انظر [\App\Support\SchemaRetire].
           أمرُ توريدٍ صدر مستندٌ في سجلّ العميل، وإلغاءُ الشاشة لا يبيح
           محوَه. الفارغُ يُسقَط، وما فيه صفوفٌ يصير `retired_supply_orders`. */
        SchemaRetire::table('supply_orders');
    }

    public function down(): void
    {
        if (! Schema::hasTable('supply_orders')) {
            Schema::create('supply_orders', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('order_number')->nullable()->unique();
                $table->date('start_date')->nullable()->index();
                $table->date('end_date')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->unsignedBigInteger('employee_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->text('product_details')->nullable();
                $table->text('shipping_address')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('shipping_policy_file')->nullable();
                $table->string('tag')->nullable();
                $table->decimal('budget', 15, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->unsignedBigInteger('payment_method')->nullable();
                $table->unsignedBigInteger('document_template_id')->nullable();
                $table->tinyInteger('currency_legacy')->nullable()->default(1);
                $table->longText('custom_fields')->nullable();
                $table->string('attachment')->nullable();
                $table->boolean('show_employee')->default(false);
                $table->tinyInteger('status')->nullable()->default(1);
                $table->unsignedBigInteger('status_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('job_roles')) {
            $missing = array_values(array_filter(
                self::ROLE_COLUMNS,
                fn (string $column) => ! Schema::hasColumn('job_roles', $column)
            ));

            if ($missing !== []) {
                Schema::table('job_roles', function (Blueprint $table) use ($missing) {
                    foreach ($missing as $column) {
                        $table->boolean($column)->default(false);
                    }
                });
            }
        }
    }
};
