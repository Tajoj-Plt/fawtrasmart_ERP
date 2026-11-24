<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * استكمال جدول مفاتيح الربط.
 *
 * النموذج `ApiKey` يكتب أعمدة `secret` و`last_used_ip` و`created_by`
 * وهي غير موجودة في الجدول — فإنشاء أي مفتاح كان يسقط بـ
 * «Unknown column 'secret'». ولهذا بقي الجدول فارغاً.
 *
 * ويُضاف عمود الصلاحيات: المفتاح بلا صلاحيات محدّدة يفتح كل شيء أو لا شيء،
 * وكلاهما غير مقبول في واجهة تُعطى لطرف خارجي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            if (! Schema::hasColumn('api_keys', 'secret')) {
                $table->string('secret', 64)->nullable()->after('key');
            }

            if (! Schema::hasColumn('api_keys', 'last_used_ip')) {
                $table->string('last_used_ip', 45)->nullable()->after('last_used_at');
            }

            if (! Schema::hasColumn('api_keys', 'abilities')) {
                $table->json('abilities')->nullable()->after('secret');
            }

            if (! Schema::hasColumn('api_keys', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('abilities');
            }

            if (! Schema::hasColumn('api_keys', 'usage_count')) {
                $table->unsignedInteger('usage_count')->default(0)->after('last_used_ip');
            }
        });

        // البحث يتم بالمفتاح في كل طلب — بلا فهرس يمسح الجدول كاملاً
        Schema::table('api_keys', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('api_keys'))->pluck('name')->all();

            if (! in_array('api_keys_key_index', $indexes, true)
                && ! in_array('api_keys_key_unique', $indexes, true)) {
                if (! SchemaGuard::hasIndex('api_keys', 'api_keys_key_index')) {
                    $table->index('key');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            foreach (['secret', 'abilities', 'created_by', 'last_used_ip', 'usage_count'] as $column) {
                if (Schema::hasColumn('api_keys', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
