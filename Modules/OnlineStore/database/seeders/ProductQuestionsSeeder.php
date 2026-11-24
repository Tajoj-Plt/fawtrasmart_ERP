<?php

namespace Modules\OnlineStore\Database\Seeders;

use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات تجريبية لشاشة «أسئلة المنتجات»: أسئلة وإجابات عربية واقعية على
 * بضعة منتجات موجودة — ليرى صاحب المتجر الشاشة مأهولة ويفهم وظيفتها.
 *
 * آمنٌ للتكرار: السؤال يُعرَّف بـ(المنتج + نصّه)؛ الموجود يُتخطّى ولا يُعدَّل،
 * ولا يُحذف شيء. بلا منتجات ⇒ يخرج بصمت.
 *
 * ويتأكد أن قالبَي البريد `question.answered` و`review.replied` موجودان
 * (عبر EmailTemplateSeeder الذي يزرع الناقص فقط) — بدونهما لا يصل العميلَ
 * بريدُ الإجابة.
 *
 * التشغيل:
 *   php artisan db:seed --class="Modules\OnlineStore\Database\Seeders\ProductQuestionsSeeder" --force
 */
class ProductQuestionsSeeder extends Seeder
{
    /**
     * [السائل، السؤال، الإجابة أو null، منشور؟، قبل كم يوماً]
     */
    private const SAMPLES = [
        ['أبو فهد', 'هل المنتج أصلي وعليه ضمان؟', 'نعم، المنتج أصلي 100% ويشمله ضمان الاستبدال خلال 7 أيام من الاستلام إذا وُجد عيب مصنعي.', true, 12],
        ['نورة', 'كم يستغرق التوصيل داخل المدينة؟', 'التوصيل داخل المدينة من يوم إلى يومَي عمل، وخارجها من 3 إلى 5 أيام عمل.', true, 9],
        ['سارة العتيبي', 'هل يمكن الدفع عند الاستلام؟', 'نعم، الدفع عند الاستلام متاح، وتظهر لك طرق الدفع المتاحة في صفحة إتمام الطلب.', true, 7],
        ['محمد', 'هل يمكن إرجاع المنتج إذا لم يناسبني؟', 'يمكنك طلب الإرجاع خلال 7 أيام من الاستلام بشرط أن يكون المنتج بحالته الأصلية وفي عبوته.', true, 5],
        ['خالد الحربي', 'هل يتوفر خصم عند شراء كمية؟', 'نعم، تواصل معنا عبر الواتساب عند طلب 10 قطع فأكثر لنرسل لك عرض سعر خاصاً.', false, 4],
        ['أم عبدالله', 'هل المنتج متوفر حالياً أم يحتاج طلباً مسبقاً؟', null, true, 2],
        ['فيصل', 'هل تشحنون إلى خارج المملكة؟', null, true, 1],
    ];

    public function run(): void
    {
        $this->ensureEmailTemplates();

        if (! Schema::hasTable('product_questions') || ! Schema::hasTable('products')) {
            return;
        }

        // بضعة منتجات: المعروض في المتجر أولاً، ثم الأقدم
        $products = DB::table('products')
            ->when(Schema::hasColumn('products', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when(Schema::hasColumn('products', 'available_online'), fn ($q) => $q->orderByDesc('available_online'))
            ->orderBy('id')
            ->limit(3)
            ->get(['id', 'name']);

        if ($products->isEmpty()) {
            return; // لا منتجات — لا شيء يُزرع
        }

        $hasAskerName = Schema::hasColumn('product_questions', 'asker_name');
        $clientNullable = $this->clientIdIsNullable();

        // عملاء المتجر إن وُجدوا — يُنسب إليهم بعض الأسئلة
        $storeClients = Schema::hasColumn('clients', 'is_store_client')
            ? DB::table('clients')->where('is_store_client', 1)->whereNull('deleted_at')->orderBy('id')->limit(5)->pluck('id')->all()
            : [];

        if (! $clientNullable && $storeClients === []) {
            $this->command?->warn('أسئلة المنتجات: لا عملاء متجر والعمود client_id إلزامي — شغّل ترحيل 2026_10_07_400000 أولاً.');

            return;
        }

        $answererId = DB::table('users')->orderBy('id')->value('id');
        $inserted = 0;

        foreach ($products as $pIndex => $product) {
            foreach (self::SAMPLES as $sIndex => [$asker, $text, $answer, $public, $daysAgo]) {
                // توزيع: كل منتج يأخذ جزءاً من العيّنات لا كلَّها
                if (($sIndex + $pIndex) % 3 === 2) {
                    continue;
                }

                $exists = DB::table('product_questions')
                    ->where('product_id', $product->id)->where('question', $text)->exists();
                if ($exists) {
                    continue;
                }

                // سؤالٌ من كل ثلاثة يُنسب لعميل متجر إن وُجد، والباقي لزائر
                $clientId = null;
                if ($storeClients !== [] && (! $clientNullable || ! $hasAskerName || $sIndex % 3 === 0)) {
                    $clientId = $storeClients[($sIndex + $pIndex) % count($storeClients)];
                }

                $createdAt = now()->subDays($daysAgo)->subMinutes($pIndex * 17);
                $row = [
                    'product_id' => $product->id,
                    'client_id' => $clientId,
                    'question' => $text,
                    'answer' => $answer,
                    'answered_by' => $answer ? $answererId : null,
                    'answered_at' => $answer ? (clone $createdAt)->addHours(3) : null,
                    'status' => $answer ? 'answered' : 'pending',
                    'is_public' => $public,
                    'created_at' => $createdAt,
                    'updated_at' => $answer ? (clone $createdAt)->addHours(3) : $createdAt,
                ];
                if ($hasAskerName) {
                    $row['asker_name'] = $clientId ? null : $asker;
                }

                DB::table('product_questions')->insert($row);
                $inserted++;
            }
        }

        $this->command?->info("أسئلة المنتجات: أُضيف {$inserted} سؤالاً تجريبياً على {$products->count()} منتجات.");
    }

    private function clientIdIsNullable(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

        $column = collect(DB::select('SHOW COLUMNS FROM product_questions'))->firstWhere('Field', 'client_id');

        return $column && strtoupper((string) $column->Null) === 'YES';
    }

    /** قالبا بريد «الإجابة على سؤال» و«الرد على تقييم» — يُزرع الناقص فقط. */
    private function ensureEmailTemplates(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $missing = DB::table('email_templates')
            ->whereIn('template_code', ['question.answered', 'review.replied'])
            ->count() < 2;

        if ($missing && class_exists(EmailTemplateSeeder::class)) {
            $this->call(EmailTemplateSeeder::class);
        }
    }
}
