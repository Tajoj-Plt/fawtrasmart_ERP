<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * مسح الحركة التجارية: مستندات البيع والشراء وقيودها وأذونها ومدفوعاتها.
 *
 * **ما الذي يُمسح ولماذا بهذا الترتيب؟**
 *
 * الحركة التجارية شبكةٌ لا جدول: الفاتورة لها بنودٌ وضرائبُ وإذنٌ مخزني
 * وقيدٌ بطرفين ومدفوعات. حذفُ رأس الفاتورة وحده يترك بنوداً معلَّقة على
 * مرجعٍ لا وجود له، وقيداً يظلّ يُحسب في ميزان المراجعة إلى الأبد. لذلك
 * يمشي المسح من الأوراق إلى الجذر:
 *
 *   1. تفاصيل القيود ← القيود        (وإلّا بقيت الأرصدة على حالها)
 *   2. بنود الأذون   ← الأذون
 *   3. بنود المستندات وضرائبها ← رؤوس المستندات
 *   4. الجهات (بعد أن خلت من المراجع)
 *   5. إعادة احتساب أرصدة الحسابات هرمياً
 *
 * **ما لا يُمسح أبداً**
 *
 * *الأرصدة الافتتاحية*. قيدُ الافتتاح ليس حركةً تجارية بل نقطةُ البدء
 * التي بُني عليها كلُّ ما بعدها؛ ومسحُه يُصفّر الخزينة ورصيد كل حساب
 * فيبدو النظام كأنّه لم يُشغَّل قطّ. يُعرَف بـ`is_opening_balance` أو
 * بنوع المرجع `OPB`، ويُستثنى في كل الأحوال.
 *
 * وكذلك: شجرة الحسابات، والأصناف، والمستودعات، والمستخدمون، والإعدادات —
 * هذا الأمر يمسح ما جرى، لا ما بُني عليه.
 *
 * **الاستعمال**
 *
 *     php artisan data:purge --dry-run     ← يعرض ما سيُمسح ولا يمسح شيئاً
 *     php artisan data:purge               ← يسأل قبل التنفيذ
 *     php artisan data:purge --force       ← بلا سؤال (للنصوص الآلية)
 *
 *     --clients=faker|all|none    الافتراضي faker: أكبر دفعةٍ أُدرجت في
 *                                 دقيقةٍ واحدة (≥100 صفّاً) — أي بيانات
 *                                 التوليد، دون ما قد يكون حقيقياً
 *     --suppliers=all|none        الافتراضي all
 *     --stock=reset|keep          الافتراضي reset: تُصفَّر الكميات، فلا
 *                                 يبقى مخزونٌ لا مستندَ وراءه
 *
 * **خُذ نسخة احتياطية قبله.** لا يفعل هذا الأمر ذلك عنك.
 *
 * @see \App\Console\Commands\SeedDemoTransactions النظير الذي يُعيد البناء
 */
class PurgeTransactionalData extends Command
{
    protected $signature = 'data:purge
                            {--dry-run : عرض ما سيُمسح بلا تنفيذ}
                            {--force : التنفيذ بلا سؤال}
                            {--clients=faker : faker|all|none}
                            {--suppliers=all : all|none}
                            {--stock=reset : reset|keep}';

    protected $description = 'مسح مستندات البيع والشراء والمرتجعات والإشعارات والمدفوعات مع قيودها وأذونها — مع الإبقاء على الأرصدة الافتتاحية';

    /** ما سيُمسح: جدول ⇐ عدد الصفوف */
    private array $plan = [];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info('══════ فحص ما سيُمسح ══════');
        $this->newLine();

        $clientIds = $this->clientsToPurge();
        $entryIds = $this->journalEntriesToPurge();

        $this->buildPlan($clientIds, $entryIds);
        $this->renderPlan($clientIds);

        if ($dry) {
            $this->newLine();
            $this->comment('عرضٌ فقط — لم يُمسح شيء. للتنفيذ: php artisan data:purge');

            return self::SUCCESS;
        }

        if (array_sum($this->plan) === 0) {
            $this->info('لا شيء ليُمسح.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('هذه العملية لا تُستردّ. تأكّد من وجود نسخة احتياطية.');

        if (! $this->option('force') && ! $this->confirm('أأتابع المسح؟', false)) {
            $this->comment('أُلغيت.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(fn () => $this->purge($clientIds, $entryIds));
        } catch (Throwable $e) {
            $this->error('أخفق المسح ولم يتغيّر شيء: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('تمّ المسح. إعادة احتساب أرصدة الحسابات…');
        $this->recalculateBalances();

        $this->newLine();
        $this->info('انتهى. لتوليد بيانات جديدة: php artisan data:seed-demo');

        return self::SUCCESS;
    }

    // ══════════════════════════════════════════════════════════════════
    //  ما الذي يُمسح
    // ══════════════════════════════════════════════════════════════════

    /**
     * القيود المرشّحة للمسح — كلُّها إلّا الافتتاحية.
     *
     * الاستثناء بشرطين لا بواحد: `is_opening_balance` هو الضابط الصريح،
     * و`reference_type = 'OPB'` يمسك ما كُتب قبل وجود العمود.
     *
     * @return array<int, int>
     */
    private function journalEntriesToPurge(): array
    {
        $q = DB::table('journal_entries');

        if (Schema::hasColumn('journal_entries', 'is_opening_balance')) {
            $q->where(fn ($w) => $w->whereNull('is_opening_balance')->orWhere('is_opening_balance', 0));
        }

        return $q->where(fn ($w) => $w->whereNull('reference_type')->orWhere('reference_type', '!=', 'OPB'))
            ->pluck('id')
            ->all();
    }

    /**
     * العملاء المرشّحون للمسح.
     *
     * `faker` تعني: أكبر دفعةٍ أُدرجت كلُّها في دقيقةٍ واحدة وعدّتها مئةٌ
     * فأكثر. إدراجُ ثلاثة آلاف عميلٍ في دقيقةٍ واحدة لا يقع إلّا من مولّد،
     * فهذا أدقُّ من «احذف الكلّ» وأسلمُ ممّا قد يكون بياناتٍ حقيقية أُدخلت
     * بيد على مدى أيام.
     *
     * @return array<int, int>
     */
    private function clientsToPurge(): array
    {
        return match ($this->option('clients')) {
            'all' => DB::table('clients')->pluck('id')->all(),
            'none' => [],
            default => $this->fakerClientIds(),
        };
    }

    /** @return array<int, int> */
    private function fakerClientIds(): array
    {
        $batch = DB::table('clients')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS minute, COUNT(*) AS c')
            ->whereNotNull('created_at')
            ->groupBy('minute')
            ->havingRaw('COUNT(*) >= 100')
            ->orderByDesc('c')
            ->first();

        if (! $batch) {
            return [];
        }

        $this->line("  دفعة التوليد المكتشَفة: <comment>{$batch->minute}</comment> — {$batch->c} عميلاً");

        return DB::table('clients')
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") = ?', [$batch->minute])
            ->pluck('id')
            ->all();
    }

    /**
     * بناء خطة المسح: كم صفّاً في كل جدول.
     *
     * @param  array<int, int>  $clientIds
     * @param  array<int, int>  $entryIds
     */
    private function buildPlan(array $clientIds, array $entryIds): void
    {
        $invoiceIds = DB::table('invoices')->pluck('id')->all();
        $purchaseIds = DB::table('purchase_invoices')->pluck('id')->all();
        $creditIds = DB::table('credit_notifications')->pluck('id')->all();
        $permitIds = DB::table('warehouse_permits')->pluck('id')->all();

        $this->plan = [
            'journal_entry_details' => $this->countIn('journal_entry_details', 'journal_entry_id', $entryIds),
            'journal_entries' => count($entryIds),
            'warehouse_permits_products' => $this->countIn('warehouse_permits_products', 'warehouse_permits_id', $permitIds),
            'warehouse_permits' => count($permitIds),
            'invoice_items' => $this->itemRowsQuery()->count(),
            'taxs_invoice' => $this->taxRowsQuery()->count(),
            'payments_process' => DB::table('payments_process')->count(),
            'credit_notifications' => count($creditIds),
            'invoices' => count($invoiceIds),
            'purchase_invoices' => count($purchaseIds),
            'purchase_orders' => DB::table('purchase_orders')->count(),
            'clients' => count($clientIds),
            'suppliers' => $this->option('suppliers') === 'none' ? 0 : DB::table('suppliers')->count(),
        ];
    }

    /**
     * صفوف الضرائب التي ستُمسح.
     *
     * **`taxs_invoice.invoice_id` عمودٌ مشترك بين أربعة مستندات**، يميّزها
     * `type_invoice` وحده: `invoice`/`sales`/`return` لفواتير البيع،
     * و`purchase`/`InvoiceReturn_purchase`/`cityNotices_purchase` لمستندات
     * الشراء، و`credit` للإشعارات الدائنة، و`quote` لعروض الأسعار.
     *
     * ولمّا كان هذا الأمر يمسح **كلّ** فواتير البيع والشراء والإشعارات،
     * فالقاعدة أبسط ممّا تبدو: يبقى ما يخصّ عروض الأسعار وحدها — وهي
     * خارج نطاق المسح — ويذهب ما عداه.
     *
     * وتذهب معه الصفوف اليتيمة: اثنان وعشرون صفّاً في هذه القاعدة تشير
     * إلى مستندات حُذفت قبل اليوم (تسعةٌ لإشعاراتٍ وثمانيةٌ لفواتير شراء)،
     * بقيت لأنّ الحذف كان يمسّ رأس المستند ولا يمسّ ضرائبه. تُلتقط هنا
     * لأنّ شرط البقاء صار «عرضُ سعرٍ قائم» لا «ليس مطابقاً لما نمسح».
     */
    private function taxRowsQuery(): \Illuminate\Database\Query\Builder
    {
        $quoteIds = DB::table('quotes')->pluck('id')->all();

        return DB::table('taxs_invoice')->where(function ($q) use ($quoteIds) {
            $q->where('type_invoice', '!=', 'quote')
                ->orWhereNull('type_invoice');

            if ($quoteIds !== []) {
                // عرضُ سعرٍ لم يعد موجوداً: صفُّه يذهب أيضاً
                $q->orWhere(fn ($w) => $w->where('type_invoice', 'quote')->whereNotIn('invoice_id', $quoteIds));
            }
        });
    }

    /**
     * بنود المستندات التي ستُمسح.
     *
     * `invoice_items` جدولٌ واحد لكلّ بنود النظام: للفاتورة، ولفاتورة
     * الشراء، وللإشعار الدائن، ولعرض السعر، وللفاتورة الدورية — كلٌّ
     * بعموده. فما يبقى هو ما يخصّ عرض سعرٍ أو فاتورةٍ دورية قائمة.
     *
     * وتذهب البنود التي لا تشير إلى شيء أصلاً: عشرة صفوفٍ في هذه القاعدة
     * كلُّ مفاتيحها فارغة — بقايا مستنداتٍ حُذفت رؤوسها وبقيت بنودها.
     */
    private function itemRowsQuery(): \Illuminate\Database\Query\Builder
    {
        $quoteIds = DB::table('quotes')->pluck('id')->all();
        $periodicIds = DB::table('periodic_invoices')->pluck('id')->all();

        return DB::table('invoice_items')->where(function ($q) use ($quoteIds) {
            // ليس بند عرض سعرٍ قائم
            $q->where(fn ($w) => $w->whereNull('quote_id')
                ->orWhereNotIn('quote_id', $quoteIds ?: [0]));
        })->where(function ($q) use ($periodicIds) {
            // وليس بند فاتورةٍ دورية قائمة
            $q->where(fn ($w) => $w->whereNull('periodic_invoice_id')
                ->orWhereNotIn('periodic_invoice_id', $periodicIds ?: [0]));
        });
    }

    /** @param  array<int, int>  $ids */
    private function countIn(string $table, string $column, array $ids): int
    {
        if ($ids === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->count();
    }

    /** @param  array<int, int>  $clientIds */
    private function renderPlan(array $clientIds): void
    {
        $rows = [];
        foreach ($this->plan as $table => $count) {
            $rows[] = [$table, number_format($count)];
        }

        $this->table(['الجدول', 'صفوف ستُمسح'], $rows);

        $kept = DB::table('journal_entries')->count() - count($this->journalEntriesToPurge());
        $this->line("  يبقى: <info>{$kept}</info> قيداً افتتاحياً، وشجرة الحسابات، والأصناف، والمستودعات، والإعدادات.");

        if ($this->option('clients') === 'faker' && $clientIds !== []) {
            $remaining = DB::table('clients')->count() - count($clientIds);
            $this->line("  يبقى: <info>{$remaining}</info> عميلاً لم يُنشأوا في دفعة التوليد.");
        }

        if ($this->option('stock') === 'reset') {
            $n = DB::table('product_details')->where('quantity', '!=', 0)->count();
            $this->line("  المخزون: تُصفَّر كميات <info>{$n}</info> صفّاً في product_details.");
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  التنفيذ
    // ══════════════════════════════════════════════════════════════════

    /**
     * @param  array<int, int>  $clientIds
     * @param  array<int, int>  $entryIds
     */
    private function purge(array $clientIds, array $entryIds): void
    {
        $invoiceIds = DB::table('invoices')->pluck('id')->all();
        $purchaseIds = DB::table('purchase_invoices')->pluck('id')->all();
        $creditIds = DB::table('credit_notifications')->pluck('id')->all();
        $permitIds = DB::table('warehouse_permits')->pluck('id')->all();

        // ①  القيود: التفاصيل قبل الرؤوس
        $this->chunkDelete('journal_entry_details', 'journal_entry_id', $entryIds);
        $this->chunkDelete('journal_entries', 'id', $entryIds);

        // ②  الأذون المخزنية: البنود قبل الرؤوس. وتُفكّ إشارة التبديلات
        //     إليها أوّلاً — المفتاح الأجنبي يمنع الحذف وهي تشير
        if (Schema::hasTable('product_exchanges')) {
            DB::table('product_exchanges')
                ->whereIn('outgoing_permit_id', $permitIds)
                ->orWhereIn('incoming_permit_id', $permitIds)
                ->update(['outgoing_permit_id' => null, 'incoming_permit_id' => null]);
        }

        $this->chunkDelete('warehouse_permits_products', 'warehouse_permits_id', $permitIds);
        $this->chunkDelete('warehouse_permits', 'id', $permitIds);

        // ③  المستندات: البنود والضرائب قبل الرؤوس
        $this->itemRowsQuery()->delete();
        $this->taxRowsQuery()->delete();

        DB::table('payments_process')->delete();
        $this->chunkDelete('credit_notifications', 'id', $creditIds);
        $this->chunkDelete('invoices', 'id', $invoiceIds);
        $this->chunkDelete('purchase_invoices', 'id', $purchaseIds);
        DB::table('purchase_orders')->delete();

        // ④  الجهات — بعد أن خلت من كل مرجع
        if ($clientIds !== []) {
            $this->purgeClients($clientIds);
        }

        if ($this->option('suppliers') !== 'none') {
            if (Schema::hasTable('contacts')) {
                DB::table('contacts')->whereNotNull('supplier_id')->delete();
            }

            // حسابُ المورّد في الشجرة يذهب معه — كما يذهب حسابُ العميل.
            // إغفالُه يترك حساباً في دليل الحسابات باسم جهةٍ لا وجود لها،
            // ويجعل `suppliers:backfill-accounts` يعدّ حساباتٍ أكثر من
            // المورّدين أنفسهم
            if (Schema::hasColumn('accounts', 'supplier_id')) {
                DB::table('accounts')->whereNotNull('supplier_id')->delete();
            }

            DB::table('suppliers')->delete();
        }

        // ⑤  المخزون: لا كميّةَ بلا مستندٍ وراءها
        if ($this->option('stock') === 'reset') {
            DB::table('product_details')->update(['quantity' => 0]);
        }
    }

    /**
     * حذف العملاء بعد تنظيف ما يتعلّق بهم.
     *
     * الجداول التابعة تُمسح أوّلاً حتى لا يردّ المفتاح الأجنبي الحذف.
     * تُتخطّى الجداول غير الموجودة بصمت: وحدةٌ معطَّلة في
     * `modules_statuses.json` قد لا تكون هجرتُها شُغّلت أصلاً.
     *
     * @param  array<int, int>  $clientIds
     */
    private function purgeClients(array $clientIds): void
    {
        $dependents = [
            'appointment_notes', 'appointments', 'balance_charges', 'balance_consumptions',
            'carts', 'challenge_participants', 'challenge_transactions', 'client_addresses',
            'client_devices', 'client_employee', 'client_employee_notes', 'client_gift_offer',
            'client_loyalty_rule', 'client_payment_cards', 'client_status_requests', 'contacts',
            'credit_limit', 'doubtful_debts', 'employee_client_visits', 'notifications',
            'online_orders', 'points_batches', 'points_notifications_log', 'points_transactions',
            'product_questions', 'product_review_helpfuls', 'product_reviews',
            'recently_viewed_products', 'reward_redemptions', 'store_coupon_usages',
            'tier_upgrades_log', 'user_achievements', 'wishlists',
        ];

        foreach ($dependents as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'client_id')) {
                $this->chunkDelete($table, 'client_id', $clientIds);
            }
        }

        // الإحالات تشير إلى العملاء بعمودين
        if (Schema::hasTable('referrals')) {
            DB::table('referrals')
                ->whereIn('referrer_id', $clientIds)
                ->orWhereIn('referred_id', $clientIds)
                ->delete();
        }

        // حسابُ العميل في شجرة الحسابات يذهب معه
        if (Schema::hasColumn('accounts', 'client_id')) {
            $this->chunkDelete('accounts', 'client_id', $clientIds);
        }

        $this->chunkDelete('clients', 'id', $clientIds);
    }

    /**
     * حذفٌ على دفعات.
     *
     * `whereIn` بثلاثة آلاف معرّف يبني استعلاماً يتجاوز
     * `max_allowed_packet` على بعض الإعدادات، ويقفل صفوفاً كثيرة دفعةً
     * واحدة. الألف في المرّة تكفي.
     *
     * @param  array<int, int>  $ids
     */
    private function chunkDelete(string $table, string $column, array $ids): void
    {
        if ($ids === [] || ! Schema::hasTable($table)) {
            return;
        }

        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table($table)->whereIn($column, $chunk)->delete();
        }
    }

    /**
     * إعادة احتساب الأرصدة هرمياً.
     *
     * تُنفَّذ **خارج** المعاملة عمداً: هي قراءةٌ وكتابةٌ على شجرة الحسابات
     * كلِّها، وضمُّها إلى معاملة المسح يُطيل قفل الجداول بلا فائدة — والمسح
     * قد تمّ وثبت قبلها.
     */
    private function recalculateBalances(): void
    {
        $ids = DB::table('accounts')->pluck('id');
        $bar = $this->output->createProgressBar($ids->count());

        foreach ($ids as $id) {
            try {
                Account::updateBalancesHierarchically($id);
            } catch (Throwable $e) {
                // حسابٌ واحد لا يُعيد حسابه لا يوقف الباقي
                $this->newLine();
                $this->warn("تعذّر احتساب رصيد الحساب #{$id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
