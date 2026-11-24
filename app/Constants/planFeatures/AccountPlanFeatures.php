<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة الحسابات على كتالوج باقة فوترة — **المصدر الوحيد لهذه الأسماء**.
 *
 * ─────────────────────── طبقتان لا واحدة ───────────────────────
 *
 * صلاحيات النظام ([AccountPermissions]، بنمط `account.journal.view`) تقول
 * **من** يفعل ماذا داخل المنشأة. وصلاحيات الباقة هنا (بنمط `journal_entries`)
 * تقول **ماذا اشترت المنشأة أصلاً**. لا يتداخلان ولا يُغني أحدهما عن الآخر:
 * محاسبٌ يملك كل صلاحيات النظام لا يفتح شاشةً لم تُشترَ، ومنشأةٌ اشترت
 * الميزة لا تفتحها لموظفٍ بلا صلاحية.
 *
 * ─────────────────────── المفتاحان يتشابهان هنا أكثر من غيرها ───────────────
 *
 * في المبيعات يفترق الاسمان بوضوح (`invoices` صلاحيةً و`sales_invoices`
 * حدّاً). أمّا هنا فأربعةٌ منها **متطابقة الاسم** في الخريطتين:
 * `journal_entries` و`expenses` و`incomes` و`cost_centers` و`assets`
 * و`check_books` و`payable_cheques` و`received_cheques`.
 *
 * والتطابق لا يعني التبادل: `treasury` صلاحيةٌ بلا حدٍّ بهذا الاسم، وحدُّها
 * `treasuries` بالجمع. فمن مرّر `treasury` إلى `liveLimit()` حصل على «ميزة
 * غير متابَعة» أي **بلا حدّ**، ومن مرّر `treasuries` إلى `allows()` حصل على
 * «مفتاح مجهول» أي **مسموح دائماً**. وكلاهما حارسٌ صامت لا يحرس.
 *
 * ولهذا فُصل الثابتان هنا كما في بقيّة الوحدات — والفصل هو ما يجعل الخطأ
 * مستحيلاً لا مجرّد غير محتمل.
 *
 * ─────────────────────── ما لا تذكره الباقة ───────────────────────
 *
 * «إعدادات المحاسبة» (السنوات المالية والفترات المقفلة وتوجيه الحسابات)
 * و«إعدادات المالية» لا مفتاح لهما في الكتالوج. فتُلحقان بأقرب ميزةٍ
 * تتبعانها فعلاً — الأولى بدليل الحسابات والثانية بالخزينة — ولا يُخترع
 * لهما مفتاح: المخترع يُعامَل مسموحاً دائماً فيبدو حارساً وليس بشيء.
 *
 * و«تقارير التحصيلات» (`collections`) تتبع التقارير المحاسبية.
 */
class AccountPlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    /* ── المحاسبة والمالية ── */

    public const JOURNAL_ENTRIES = 'journal_entries';

    public const EXPENSES = 'expenses';

    public const INCOMES = 'incomes';

    /** الخزينة والصناديق — **مفردةً** في الصلاحيات وجمعاً في الحدود. */
    public const TREASURY = 'treasury';

    public const CHART_OF_ACCOUNTS = 'chart_of_accounts';

    public const COST_CENTERS = 'cost_centers';

    public const ASSETS = 'assets';

    public const ACCOUNT_STATEMENT = 'account_statement';

    public const ACCOUNT_REPORTS = 'account_reports';

    /* ── دورة الشيكات ── */

    public const CHECK_BOOKS = 'check_books';

    public const PAYABLE_CHEQUES = 'payable_cheques';

    public const RECEIVED_CHEQUES = 'received_cheques';

    /**
     * كل صلاحيات الوحدة — تكفي واحدةٌ منها لفتح لوحتها وبند قائمتها الجامع.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::JOURNAL_ENTRIES,
            self::EXPENSES,
            self::INCOMES,
            self::TREASURY,
            self::CHART_OF_ACCOUNTS,
            self::COST_CENTERS,
            self::ASSETS,
            self::ACCOUNT_STATEMENT,
            self::ACCOUNT_REPORTS,
            self::CHECK_BOOKS,
            self::PAYABLE_CHEQUES,
            self::RECEIVED_CHEQUES,
        ];
    }

    /* ==================== مفاتيح حدود الاستخدام ==================== */

    /** عدّادٌ شهري: القيود اليومية. */
    public const LIMIT_JOURNAL_ENTRIES = 'journal_entries';

    /** عدّادٌ شهري: سندات الصرف. */
    public const LIMIT_EXPENSES = 'expenses';

    /** عدّادٌ شهري: سندات القبض. */
    public const LIMIT_INCOMES = 'incomes';

    /** عدّادٌ شهري: الشيكات الصادرة. */
    public const LIMIT_PAYABLE_CHEQUES = 'payable_cheques';

    /** عدّادٌ شهري: الشيكات المستلمة. */
    public const LIMIT_RECEIVED_CHEQUES = 'received_cheques';

    /** سقفٌ متزامن: الخزائن والصناديق — **بالجمع**، خلافاً لصلاحيتها. */
    public const LIMIT_TREASURIES = 'treasuries';

    /** سقفٌ متزامن: مراكز التكلفة. */
    public const LIMIT_COST_CENTERS = 'cost_centers';

    /** سقفٌ متزامن: دفاتر الشيكات. */
    public const LIMIT_CHECK_BOOKS = 'check_books';

    /** سقفٌ متزامن: الأصول الثابتة. */
    public const LIMIT_ASSETS = 'assets';
}
