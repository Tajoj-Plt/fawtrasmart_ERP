<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ترحيل تمويل التقسيط — المرابحة والرسوم الإدارية وضريبتهما.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * اتفاقيةُ التقسيط تُضيف فوق الفاتورة ربحَ مرابحةٍ ورسوماً إداريةً وضريبةً
 * عليهما، وتُقسّطها في جدولها — **ولا يُرحَّل منها شيءٌ**. فذمّةُ العميل
 * في دفتر الأستاذ قيمةُ الفاتورة وحدها، وسقفُ التحصيل إجماليُّ الفاتورة،
 * فيقف «سداد الكل» عند الفاتورة ويبقى آخرُ قسطٍ معلّقاً لا يُقبل عليه
 * سند، ولا يدخل الإيرادَ ربحٌ ولا رسمٌ ولا تصل الضريبةُ إلى حسابها.
 *
 * ═════════════════════ ما صار ═════════════════════
 *
 * عند حفظ الاتفاقية (`InstallmentFinancing::sync`):
 *
 *     من ح/ العميل                          ربح + رسوم + ضريبة
 *         إلى ح/ أرباح مرابحة مؤجلة (2151)       الربح
 *         إلى ح/ رسوم إدارية على التقسيط (4262)  الرسوم
 *         إلى ح/ ضريبة المبيعات (vat_payable)     الضريبة
 *
 * ومع كلّ تحصيلٍ يُعترَف بنصيبه من الربح:
 *
 *     من ح/ أرباح مرابحة مؤجلة  xx
 *         إلى ح/ أرباح المرابحة (4261)  xx
 *
 * ═════════════════════ الحسابات بالكود ═════════════════════
 *
 * كترحيل `add_late_fee_accounts` تماماً: البحثُ بالكود والإدراجُ بمعرّفٍ
 * تلقائيّ، لأنّ المعرّفات فوق آخر المزروع مأخوذةٌ بحسابات العملاء في
 * التركيبات القائمة. ومفتاحُ إعدادٍ ربطه المحاسبُ بيده لا يُمَسّ.
 */
return new class extends Migration
{
    /** «الخصوم المتداولة» (21) و«إيرادات أخرى» (42) — بكودهما لا بمعرّفهما. */
    private const CURRENT_LIABILITIES_CODE = '21';

    private const OTHER_REVENUE_CODE = '42';

    public function up(): void
    {
        $this->addColumns();
        $this->splitStoredFees();
        $this->addAccounts();
    }

    private function addColumns(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            // ما رُحِّل فعلاً من كلّ مكوّن — الترحيلُ بالفرق يقرأ منها
            if (! Schema::hasColumn('installments', 'financing_profit_posted')) {
                $table->decimal('financing_profit_posted', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('installments', 'financing_fees_posted')) {
                $table->decimal('financing_fees_posted', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('installments', 'financing_tax_posted')) {
                $table->decimal('financing_tax_posted', 15, 2)->default(0);
            }
            // ما نُقل من الربح المؤجَّل إلى الإيراد مع التحصيل
            if (! Schema::hasColumn('installments', 'profit_recognized')) {
                $table->decimal('profit_recognized', 15, 2)->default(0);
            }
        });

        Schema::table('installments_details', function (Blueprint $table) {
            // الرسوم الإدارية في عمودها — كانت داخل `tax_amount`
            if (! Schema::hasColumn('installments_details', 'fees_amount')) {
                $table->decimal('fees_amount', 15, 2)->default(0)->after('profit_amount');
            }
        });
    }

    /**
     * الصفوف القائمة: `tax_amount` فيها الضريبة **والرسوم** معاً.
     * يُفصَلان بنسبة الرسوم إلى (الرسوم + الضريبة) في الاتفاقية.
     */
    private function splitStoredFees(): void
    {
        $agreements = DB::table('installments')->where('admin_fees', '>', 0)->get(['id', 'admin_fees', 'tax_amount']);

        foreach ($agreements as $agreement) {
            $details = DB::table('installments_details')
                ->where('installments_id', $agreement->id)
                ->where('fees_amount', 0)
                ->orderBy('installment_number')
                ->get(['id', 'tax_amount']);

            if ($details->isEmpty()) {
                continue;
            }

            $feesTotal = (float) $agreement->admin_fees;
            $combined = $feesTotal + (float) $agreement->tax_amount;
            $left = $feesTotal;
            $last = $details->count() - 1;

            foreach ($details->values() as $i => $detail) {
                $mixed = (float) $detail->tax_amount;
                // الأخير يأخذ الباقي بالضبط — فلا كسرٌ يضيع
                $fees = $i === $last ? round($left, 2) : round($combined > 0 ? $mixed * $feesTotal / $combined : 0, 2);
                $fees = min($fees, $mixed);
                $left = round($left - $fees, 2);

                DB::table('installments_details')->where('id', $detail->id)->update([
                    'fees_amount' => $fees,
                    'tax_amount' => round($mixed - $fees, 2),
                ]);
            }
        }
    }

    private function addAccounts(): void
    {
        $liabilities = DB::table('accounts')->where('code', self::CURRENT_LIABILITIES_CODE)->value('id');
        $otherRevenue = DB::table('accounts')->where('code', self::OTHER_REVENUE_CODE)->value('id');

        if ($liabilities) {
            $deferredParent = $this->ensureAccount('215', 'إيرادات مؤجلة', (int) $liabilities, 'liabilities');

            if ($deferredParent) {
                $this->linkSetting(
                    'installment_deferred_profit',
                    $this->ensureAccount('2151', 'أرباح مرابحة مؤجلة', $deferredParent, 'liabilities'),
                    '2151',
                    'أرباح مرابحة مؤجلة',
                    'ربحُ التقسيط عند حفظ الاتفاقية — التزامٌ يُنقل إلى «أرباح المرابحة» مع كلّ تحصيل',
                    'العملاء والذمم'
                );
            }
        }

        if ($otherRevenue) {
            $revenueParent = $this->ensureAccount('426', 'إيرادات التقسيط', (int) $otherRevenue, 'revenues');

            if ($revenueParent) {
                $this->linkSetting(
                    'installment_profit_revenue',
                    $this->ensureAccount('4261', 'أرباح المرابحة', $revenueParent, 'revenues'),
                    '4261',
                    'أرباح المرابحة',
                    'الربحُ المحقَّق من أقساطٍ حُصِّلت: من ح/ أرباح مرابحة مؤجلة إلى ح/ أرباح المرابحة',
                    'المبيعات والإيرادات'
                );

                $this->linkSetting(
                    'installment_admin_fees',
                    $this->ensureAccount('4262', 'رسوم إدارية على التقسيط', $revenueParent, 'revenues'),
                    '4262',
                    'رسوم إدارية على التقسيط',
                    'الرسوم الإدارية لاتفاقية التقسيط — إيرادٌ عند حفظها',
                    'المبيعات والإيرادات'
                );
            }
        }
    }

    /**
     * الكودُ يُقبل حين يحمله الحسابُ المقصود باسمه فقط.
     *
     * 215 و2151 في الشجرة القياسية هما «رواتب ومستحقات الموظفين» وابنُه —
     * فكان البحثُ بالكود وحده يجعل أبَ الرواتب أباً للإيرادات المؤجلة، ويربط
     * `installment_deferred_profit` بحساب رواتب. فإن كان الكودُ لحسابٍ آخر
     * بُحث بالاسم تحت الأب، وإلا أُنشئ بكود الابن التالي تحته.
     */
    private function ensureAccount(string $code, string $name, int $parentId, string $type): ?int
    {
        $existing = DB::table('accounts')->where('code', $code)->first(['id', 'name']);

        if ($existing && trim((string) $existing->name) === $name) {
            return (int) $existing->id;
        }

        $byName = DB::table('accounts')->where('parent_id', $parentId)->where('name', $name)->value('id');

        if ($byName) {
            return (int) $byName;
        }

        if ($existing) {
            $code = $this->nextChildCode($parentId);
        }

        $balanceType = $type === 'revenues' || $type === 'liabilities' ? 'credit' : 'debit';

        return (int) DB::table('accounts')->insertGetId([
            'name' => $name,
            'code' => $code,
            'parent_id' => $parentId,
            'type' => 'sub',
            'account_type' => $type,
            'balance_type' => $balanceType,
            'balance' => 0.0,
            'opening_balance' => 0.0,
            'opening_balance_type' => $balanceType,
            'is_active' => true,
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** كودُ الابن التالي — بادئةُ الأب ثمّ خانة، كقاعدة شاشة الشجرة. */
    private function nextChildCode(int $parentId): string
    {
        $prefix = (int) DB::table('accounts')->where('id', $parentId)->value('code');

        for ($width = 1; $width <= 4; $width++) {
            $base = $prefix * (10 ** $width);
            for ($i = 1; $i < 10 ** $width; $i++) {
                if (! DB::table('accounts')->where('code', (string) ($base + $i))->exists()) {
                    return (string) ($base + $i);
                }
            }
        }

        throw new \RuntimeException('تعذّر توليد كود فرعي تحت الحساب '.$prefix);
    }

    /** يُنشئ المفتاح إن غاب، ويربطه إن كان بلا حساب — ولا يمسّ ربطاً يدوياً. */
    private function linkSetting(string $key, ?int $accountId, string $code, string $name, string $description, string $group): void
    {
        // الكودُ الفعليّ للحساب — قد يختلف عن المقترَح إن كان مشغولاً
        if ($accountId) {
            $code = (string) (DB::table('accounts')->where('id', $accountId)->value('code') ?? $code);
        }

        $setting = DB::table('accounting_settings')->where('key', $key)->first();

        if (! $setting) {
            DB::table('accounting_settings')->insert([
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'group' => $group,
                'account_id' => $accountId,
                'account_code' => $accountId ? $code : null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif (! $setting->account_id && $accountId) {
            DB::table('accounting_settings')->where('key', $key)->update([
                'account_id' => $accountId,
                'account_code' => $code,
                'updated_at' => now(),
            ]);
        }

        cache()->forget('accounting_settings.map');
    }

    public function down(): void
    {
        foreach (['installment_admin_fees' => '4262', 'installment_profit_revenue' => '4261', 'installment_deferred_profit' => '2151'] as $key => $code) {
            $id = DB::table('accounts')->where('code', $code)->value('id');

            DB::table('accounting_settings')->where('key', $key)->delete();

            // لا يُحذف حسابٌ تحرّك عليه قيد
            if ($id && ! DB::table('journal_entry_details')->where('account_id', $id)->exists()) {
                DB::table('accounts')->where('id', $id)->delete();
            }
        }

        foreach (['426', '215'] as $code) {
            $id = DB::table('accounts')->where('code', $code)->value('id');

            if ($id && ! DB::table('accounts')->where('parent_id', $id)->exists()
                && ! DB::table('journal_entry_details')->where('account_id', $id)->exists()) {
                DB::table('accounts')->where('id', $id)->delete();
            }
        }

        // الرسوم تعود إلى عمود الضريبة كما كانت
        DB::table('installments_details')->where('fees_amount', '>', 0)
            ->update(['tax_amount' => DB::raw('tax_amount + fees_amount')]);

        Schema::table('installments_details', function (Blueprint $table) {
            $table->dropColumn('fees_amount');
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->dropColumn(['financing_profit_posted', 'financing_fees_posted', 'financing_tax_posted', 'profit_recognized']);
        });
    }
};
