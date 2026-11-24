<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
    فهارس البحث في شاشات العملاء والزيارات والإشعارات — أعمدةٌ تُسأل في كل
    طلب ولا فهرس عليها، فكلُّ سؤالٍ مسحٌ كاملٌ للجدول. في الموقع وتطبيق
    الجوال معاً:

      • `notifications.created_at` — حدُّ الباقة (`internal_notifications`) يعدّ
        إشعارات الشهر مع **كل إدراج**: كان ثانيتين تقريباً لكل إشعار (بدء
        الزيارة، إنهاؤها، الملاحظة، الردّ، طلبات الحالة…).
      • `notifications.type` — سلاسل الردود (`replyThreads()`) تسأل بالنوع في
        كل فتحٍ لإشعار: ~٣ ثوانٍ.
      • `notifications.receiver_id+read` و`user_id` — الجرس وصندوق الوارد.
      • رصيد العميل (`accounts.client_id`)، موقعه وموقع المندوب (`locations`)،
        حيّه، سندات حسابه، ومسؤولوه.

    كل فهرسٍ مشروطٌ بوجود أعمدته وغياب اسمه — قواعدُ المنشآت ليست متطابقة.
*/
return new class extends Migration
{
    /** @var array<string, array<int, array<int, string>>> */
    private array $indexes = [
        'notifications' => [['created_at'], ['type'], ['receiver_id', 'read'], ['user_id']],
        'accounts' => [['client_id']],
        'neighborhoods' => [['client_id']],
        'locations' => [['client_id'], ['employee_id', 'client_id']],
        'receipts' => [['account_id'], ['client_id']],
        'expenses' => [['account_id']],
        'client_employee_notes' => [['client_id']],
        'hidden_clients' => [['user_id']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $sets) {
                foreach ($sets as $columns) {
                    $name = $table.'_'.implode('_', $columns).'_index';

                    if (Schema::hasColumns($table, $columns) && ! Schema::hasIndex($table, $name)) {
                        $blueprint->index($columns, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $sets) {
                foreach ($sets as $columns) {
                    $name = $table.'_'.implode('_', $columns).'_index';

                    if (Schema::hasIndex($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }
};
