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
        // التحقق من وجود الجدول أولاً
        if (Schema::hasTable('serial_settings')) {
            Schema::table('serial_settings', function (Blueprint $table) {
                // إضافة عمود reset_yearly إذا لم يكن موجوداً
                if (! Schema::hasColumn('serial_settings', 'reset_yearly')) {
                    $table->boolean('reset_yearly')->default(false)->after('mode')->comment('إعادة تعيين الترقيم سنوياً');
                }

                // تعديل القيمة الافتراضية لـ number_of_digits
                if (Schema::hasColumn('serial_settings', 'number_of_digits')) {
                    $table->integer('number_of_digits')->default(6)->change();
                }

                // تعديل طول عمود prefix
                if (Schema::hasColumn('serial_settings', 'prefix')) {
                    $table->string('prefix', 10)->nullable()->change();
                }

                // تغيير mode من integer إلى tinyInteger
                if (Schema::hasColumn('serial_settings', 'mode')) {
                    $table->tinyInteger('mode')->default(0)->change();
                }

                // إضافة تعليقات للأعمدة
                //
                // بلا `->unique()`: استدعاؤه داخل `->change()` لا يُدمج في أمر
                // التعديل بل يُصدر `ALTER TABLE ... ADD UNIQUE` مستقلاً، والفهرس
                // منشأ أصلاً في هجرة إنشاء الجدول. فكان `migrate` على قاعدة
                // جديدة يسقط هنا بـ «Duplicate key name
                // serial_settings_section_unique». المقصود من السطر تعليقُ
                // العمود لا فهرسته
                if (Schema::hasColumn('serial_settings', 'section')) {
                    $table->string('section')->comment('نوع القسم (invoice, customer, etc)')->change();
                }

                if (Schema::hasColumn('serial_settings', 'current_number')) {
                    $table->integer('current_number')->default(0)->comment('الرقم الحالي')->change();
                }
            });

            // إضافة فهرس على section إذا لم يكن موجوداً
            if (! Schema::hasIndex('serial_settings', 'serial_settings_section_index')) {
                Schema::table('serial_settings', function (Blueprint $table) {
                    if (! SchemaGuard::hasIndex('serial_settings', 'serial_settings_section_index')) {
                        $table->index('section');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('serial_settings')) {
            Schema::table('serial_settings', function (Blueprint $table) {
                if (Schema::hasColumn('serial_settings', 'reset_yearly')) {
                    $table->dropColumn('reset_yearly');
                }

                if (Schema::hasIndex('serial_settings', 'serial_settings_section_index')) {
                    $table->dropIndex('serial_settings_section_index');
                }
            });
        }
    }
};
