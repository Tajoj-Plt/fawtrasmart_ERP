<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Database\Seeders\Concerns\HrDocumentDesign;
use Database\Seeders\Concerns\UnifiedDocumentDesign;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    use HrDocumentDesign;
    use UnifiedDocumentDesign;

    public function run()
    {
        foreach (self::types() as $docType => $info) {
            foreach ($this->presetsFor($docType, $info) as $t) {
                DocumentTemplate::updateOrCreate(
                    ['name' => $t['name'], 'document_type' => $docType],
                    $this->attributesFor($t)
                );
            }
        }
    }

    /**
     * سمات القالب الجاهز عدا مفتاح البحث — يقرأها أيضاً أمر الإصلاح
     * ليعيد قالباً واحداً إلى أصله دون إعادة زراعة الجدول كلّه.
     *
     * @param  array<string, mixed>  $preset
     * @return array<string, mixed>
     */
    public function attributesFor(array $preset): array
    {
        return [
            'template_type' => 'ready',
            'paper_size' => $preset['paper_size'],
            'width' => $preset['paper_size'] === 'Thermal_80mm' ? 80 : ($preset['paper_size'] === 'Thermal_58mm' ? 58 : 210),
            'height' => $preset['paper_size'] === 'A4' ? 297 : null,
            'orientation' => $preset['orientation'] ?? 'portrait',
            'is_default' => $preset['is_default'] ?? false,
            'is_active' => true,
            'content' => $preset['content'],
            'styles' => $preset['styles'],
            'settings' => $preset['settings'],
            'user_id' => null,
            'branch_id' => null,
        ];
    }

    /**
     * القالب الجاهز المطابق لاسم ونوع — أو `null` إن لم يكن من المزروعة.
     *
     * @return array<string, mixed>|null
     */
    public function presetFor(string $documentType, string $name): ?array
    {
        $info = self::types()[$documentType] ?? null;

        if ($info === null) {
            return null;
        }

        foreach ($this->presetsFor($documentType, $info) as $preset) {
            if ($preset['name'] === $name) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * كلُّ قوالب نوعٍ مزروعةً: قوالبُ مجموعته، ثمّ ما يخصُّه وحده.
     *
     * **نقطةُ الجمع الواحدة** — يقرؤها الزرعُ والإصلاحُ معاً. ولولاها لظلّ
     * `presetFor()` لا يعرف القوالب الخاصّة بنوع، فيَعُدّها أمرُ
     * `document-templates:refresh` «قوالبَ لا مقابل لها» ويتركها بلا
     * تحديثٍ أبداً.
     *
     * @param  array<string, mixed>  $info
     * @return array<int, array<string, mixed>>
     */
    public function presetsFor(string $documentType, array $info): array
    {
        /* «التصميم الموحّد» هو الافتراضيُّ في التركيب الجديد — فقوالبُ المجموعة
           تُزرع غيرَ افتراضية، وإلّا صار للنوع افتراضيّان. وعلى نظامٍ قائم لا
           يُغيَّر الافتراضيُّ إلّا بأمرٍ صريح: `document-templates:refresh --adopt-unified`. */
        $unified = $this->unifiedPresetsFor($documentType, $info);

        $group = $unified === []
            ? $this->getPresetsForGroup($info['group'], $info)
            : array_map(fn ($preset) => ['is_default' => false] + $preset, $this->getPresetsForGroup($info['group'], $info));

        /* «تصميم الموارد البشرية» أوّلاً في أنواع الموارد البشرية (العقد، القسيمة،
           المسير، السلفة، العمولة): هويةٌ مستقلّة لا تشبه الفاتورة. فيُزرع هو
           الافتراضيَّ، ويتنحّى «التصميم الموحّد» عن الافتراض فيها. وعلى نظامٍ قائم
           لا يتغيّر الافتراضيُّ إلّا بـ`document-templates:refresh --adopt-hr`. */
        $hr = $this->hrPresetsFor($documentType, $info);

        if ($hr !== []) {
            $unified = array_map(fn ($preset) => ['is_default' => false] + $preset, $unified);
        }

        return array_merge($hr, $unified, $group, $this->extraPresetsFor($documentType));
    }

    /**
     * أنواع المستندات وألوانها — كانت داخل `run()` فتعذّر بلوغها من خارجه.
     *
     * `party` تسمية جهة المستند في المطبوعة: «العميل» افتراضاً، و«المورد»
     * في مستندات المشتريات — كانت كل القوالب تكتب «العميل» حتى في فاتورة
     * المشتريات، فيخرج المطبوع منسوباً إلى عميل لا وجود له.
     *
     * @return array<string, array{title:string,primary:string,secondary:string,prefix:string,group:string,party?:string}>
     */
    public static function types(): array
    {
        return [
            // ===== Sales / Invoices Group =====
            'sales_invoice' => ['title' => 'فاتورة مبيعات', 'primary' => '#7367F0', 'secondary' => '#82868b', 'prefix' => 'فاتورة مبيعات', 'group' => 'invoice'],
            'tax_invoice' => ['title' => 'فاتورة ضريبية', 'primary' => '#2563eb', 'secondary' => '#64748b', 'prefix' => 'فاتورة ضريبية', 'group' => 'invoice'],
            'simplified_invoice' => ['title' => 'فاتورة مبسطة', 'primary' => '#8b5cf6', 'secondary' => '#a78bfa', 'prefix' => 'فاتورة مبسطة', 'group' => 'invoice'],
            'purchase_invoice' => ['title' => 'فاتورة مشتريات', 'primary' => '#059669', 'secondary' => '#4b5563', 'prefix' => 'فاتورة مشتريات', 'group' => 'invoice', 'party' => 'المورّد'],
            'sales_return' => ['title' => 'مرتجع مبيعات', 'primary' => '#dc2626', 'secondary' => '#f87171', 'prefix' => 'مرتجع مبيعات', 'group' => 'invoice'],
            'purchase_return' => ['title' => 'مرتجع مشتريات', 'primary' => '#ea580c', 'secondary' => '#fb923c', 'prefix' => 'مرتجع مشتريات', 'group' => 'invoice', 'party' => 'المورّد'],
            'credit_note' => ['title' => 'إشعار دائن', 'primary' => '#0891b2', 'secondary' => '#67e8f9', 'prefix' => 'إشعار دائن', 'group' => 'invoice'],
            'debit_note' => ['title' => 'إشعار مدين', 'primary' => '#e11d48', 'secondary' => '#fda4af', 'prefix' => 'إشعار مدين', 'group' => 'invoice', 'party' => 'المورّد'],

            // ===== بقيّة دورة المشتريات — على هيئة الفاتورة =====
            // كانت قوالبُها تُستنسخ من «فاتورة مشتريات» في ترحيلَي
            // `2026_08_19_120000` و`2026_08_19_150000` فقط، فتركيبٌ رُتِّب ترحيلُه
            // قبل الزرع، أو مُسح جدولُه بـ`db:seed`، يبقى بلا قالبٍ واحدٍ لها —
            // وشاشاتُ العرض تُخفي قائمة «من الإعدادات» كلَّها، ولا يُنشئها
            // `document-templates:refresh --create-missing` لأنّها لم تكن هنا.
            // الصدورُ هي التي تركها الاستنساخ نفسه، فالصفوفُ القائمة على
            // السيرفر تُطابَق بالاسم ولا تتكرّر. وتقرأ رموزَ الدمج نفسَها:
            // `DocumentTemplateDataService` يُطبِّعها إلى بنية الفاتورة.
            'purchase_order' => ['title' => 'طلب شراء', 'primary' => '#0f766e', 'secondary' => '#5eead4', 'prefix' => 'طلب شراء', 'group' => 'invoice', 'party' => 'المورّد'],
            'quotation_request' => ['title' => 'طلب عرض أسعار', 'primary' => '#0369a1', 'secondary' => '#7dd3fc', 'prefix' => 'طلب عرض أسعار', 'group' => 'invoice', 'party' => 'المورّد'],
            'purchase_quotation' => ['title' => 'عرض سعر مشتريات', 'primary' => '#4338ca', 'secondary' => '#a5b4fc', 'prefix' => 'عرض سعر مشتريات', 'group' => 'invoice', 'party' => 'المورّد'],
            'purchase_order_request' => ['title' => 'أمر شراء', 'primary' => '#b45309', 'secondary' => '#fcd34d', 'prefix' => 'أمر شراء', 'group' => 'invoice', 'party' => 'المورّد'],

            // ===== Vouchers Group =====
            'receipt_voucher' => ['title' => 'سند قبض مالي', 'primary' => '#0f172a', 'secondary' => '#64748b', 'prefix' => 'سند قبض', 'group' => 'voucher'],
            'payment_voucher' => ['title' => 'سند صرف مالي', 'primary' => '#b91c1c', 'secondary' => '#4b5563', 'prefix' => 'سند صرف', 'group' => 'voucher'],
            // دفعة المورّد سندُ صرفٍ كذلك، لكنها تُحفَظ في `payments_process`
            // لا في `payment_vouchers` — فلها نوعها حتى تُطبَع من قوالبها
            'supplier_payment' => ['title' => 'سند صرف لمورّد', 'primary' => '#0369a1', 'secondary' => '#7dd3fc', 'prefix' => 'سند صرف لمورّد', 'group' => 'voucher', 'party' => 'المورّد'],

            // ===== Journal Entry Group =====
            'journal_entry' => ['title' => 'قيد يومية', 'primary' => '#4338ca', 'secondary' => '#818cf8', 'prefix' => 'قيد يومية', 'group' => 'journal'],

            // ===== Warehouse Group =====
            'warehouse_permit_issue' => ['title' => 'إذن صرف مخزن', 'primary' => '#0d9488', 'secondary' => '#5eead4', 'prefix' => 'إذن صرف مخزن', 'group' => 'warehouse'],
            'warehouse_permit_receipt' => ['title' => 'إذن استلام مخزن', 'primary' => '#16a34a', 'secondary' => '#86efac', 'prefix' => 'إذن استلام مخزن', 'group' => 'warehouse'],
            'inventory_transfer' => ['title' => 'تحويل مخزني', 'primary' => '#ca8a04', 'secondary' => '#fde047', 'prefix' => 'تحويل مخزني', 'group' => 'warehouse'],
            'inventory_adjustment' => ['title' => 'تسوية مخزنية', 'primary' => '#9333ea', 'secondary' => '#c084fc', 'prefix' => 'تسوية مخزنية', 'group' => 'warehouse'],

            // ===== Quote Group =====
            'quote' => ['title' => 'عرض سعر', 'primary' => '#0284c7', 'secondary' => '#7dd3fc', 'prefix' => 'عرض سعر', 'group' => 'quote'],
            // أمر البيع صفٌّ في `quotes` بنوعٍ آخر، فتصاميمُه تصاميمُ عرض السعر
            // بعبارات الأمر — انظر `salesOrderWording()`. كان بلا قالبٍ واحد،
            // فتطبع شاشتُه قالبَها المدمج وحده
            'sales_order' => ['title' => 'أمر بيع', 'primary' => '#0f766e', 'secondary' => '#5eead4', 'prefix' => 'أمر بيع', 'group' => 'quote', 'kind' => 'sales_order'],

            // ===== Contract Group =====
            'contract' => ['title' => 'عقد عمل', 'primary' => '#1e3a5f', 'secondary' => '#94a3b8', 'prefix' => 'عقد عمل', 'group' => 'contract'],

            // ===== Salary Slip Group =====
            'salary_slip' => ['title' => 'كشف رواتب', 'primary' => '#15803d', 'secondary' => '#4ade80', 'prefix' => 'كشف رواتب', 'group' => 'salary'],
            // كشفُ المسير كلِّه — سطرٌ لكلّ قسيمة. كانت شاشةُ المسير تطبع نفسَها
            // بـ`window.print()` لأنّ لا نوعَ له هنا.
            'payroll_sheet' => ['title' => 'كشف مسير رواتب', 'primary' => '#0f766e', 'secondary' => '#5eead4', 'prefix' => 'كشف مسير رواتب', 'group' => 'salary', 'kind' => 'payroll_sheet'],

            // ===== سندات الموارد البشرية =====
            // سندُ السلفة (`salary_advance_requests`) وسندُ صرف العمولة
            // (`sales_target_settlements`) — «التصميم الموحّد» وحده (A4 وحراري)،
            // لا قوالبَ مجموعةٍ قديمة لهما. `kind` يختار المتن في
            // `unifiedPresetsFor()`. يُنشآن بـ`document-templates:refresh --create-missing`.
            'salary_advance' => ['title' => 'سند سلفة', 'primary' => '#2f343a', 'secondary' => '#6b7280', 'prefix' => 'سند سلفة', 'group' => 'hr_voucher', 'kind' => 'salary_advance'],
            'commission_payment' => ['title' => 'سند صرف عمولة', 'primary' => '#2f343a', 'secondary' => '#6b7280', 'prefix' => 'سند صرف عمولة', 'group' => 'hr_voucher', 'kind' => 'commission_payment'],

            // ===== Account Statement Group =====
            // كشف الحساب لم يكن له نوعٌ هنا أصلاً، فشاشة «كشف حساب المورّد»
            // تطبع بقالبٍ مكتوبٍ داخلها لا يعرفه محرّر القوالب ولا تسري
            // عليه إعدادات المظهر ولا بيانات المنشأة.
            'supplier_statement' => ['title' => 'كشف حساب مورّد', 'primary' => '#0f766e', 'secondary' => '#5eead4', 'prefix' => 'كشف حساب مورّد', 'group' => 'statement', 'party' => 'المورّد'],
            'client_statement' => ['title' => 'كشف حساب عميل', 'primary' => '#1d4ed8', 'secondary' => '#93c5fd', 'prefix' => 'كشف حساب عميل', 'group' => 'statement'],
        ];
    }

    public function getPresetsForGroup($group, $info)
    {
        $p = $info['primary'];
        $s = $info['secondary'];
        $prefix = $info['prefix'];

        // جهة المستند: «المورد» في المشتريات و«العميل» فيما عداها
        $party = $info['party'] ?? 'العميل';

        switch ($group) {
            case 'invoice': return $this->getInvoicePresets($prefix, $p, $s, $party);
            case 'voucher': return $this->getVoucherPresets($prefix, $p, $s);
            case 'journal': return $this->getJournalPresets($prefix, $p, $s);
            case 'warehouse': return $this->getWarehousePresets($prefix, $p, $s);
            case 'quote': return $this->getQuotePresets($prefix, $p, $s, $info['kind'] ?? 'quote');
            case 'contract': return $this->getContractPresets($prefix, $p, $s);
            case 'salary': return $this->getSalaryPresets($prefix, $p, $s, $info['kind'] ?? 'salary_slip');
            case 'statement': return $this->getStatementPresets($prefix, $p, $s, $party);
            default: return [];
        }
    }

    private function makeSettings($p, $s, $fontSize = 13, $extra = [])
    {
        return array_merge([
            'show_logo' => true, 'show_qr' => true, 'show_barcode' => true,
            'show_customer' => true, 'show_tax' => true, 'show_discount' => true,
            'font_size' => $fontSize, 'direction' => 'rtl',
            'primary_color' => $p, 'secondary_color' => $s,
            'font_family' => 'Tajawal',
        ], $extra);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  GROUP 1: INVOICE PRESETS (Invoices, Returns, Notes)        ║
    // ╚══════════════════════════════════════════════════════════════╝
    private function getInvoicePresets($prefix, $p, $s, $party = 'العميل')
    {
        return [
            ['name' => "$prefix - A4 احترافي كلاسيكي", 'paper_size' => 'A4', 'is_default' => true,
                'content' => $this->getInvoiceA4Content($party), 'styles' => $this->getInvoiceA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s)],
            ['name' => "$prefix - A4 عصري ملون", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getInvoiceA4ModernContent($party), 'styles' => $this->getInvoiceA4ModernStyles($p, $s),
                'settings' => $this->makeSettings($p, $s)],
            ['name' => "$prefix - A4 كلاسيكي بإطار", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getInvoiceA4Content($party), 'styles' => $this->getInvoiceA4Styles('#1e3a8a', '#64748b', 'frame'),
                'settings' => $this->makeSettings('#1e3a8a', '#64748b')],
            ['name' => "$prefix - A4 فاخر داكن", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getInvoiceA4ModernContent($party), 'styles' => $this->getInvoiceA4ModernStyles('#1f2937', '#6b7280', 'dark'),
                'settings' => $this->makeSettings('#1f2937', '#6b7280')],
            ['name' => "$prefix - A4 دافئ نحاسي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getInvoiceA4Content($party), 'styles' => $this->getInvoiceA4Styles('#b45309', '#78350f', 'frame'),
                'settings' => $this->makeSettings('#b45309', '#78350f')],
            ['name' => "$prefix - A4 بسيط ونظيف", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getInvoiceA4Content($party), 'styles' => $this->getInvoiceA4Styles('#4b5563', '#9ca3af', 'minimal'),
                'settings' => $this->makeSettings('#4b5563', '#9ca3af', 12)],
            ['name' => "$prefix - حراري 80 مم كلاسيكي", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getInvoiceThermal80Content($party), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11)],
            ['name' => "$prefix - حراري 80 مم حديث", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getInvoiceThermal80ModernContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11)],
            ['name' => "$prefix - حراري 58 مم مصغر", 'paper_size' => 'Thermal_58mm', 'is_default' => false,
                'content' => $this->getInvoiceThermal58Content(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 10)],
            ['name' => "$prefix - A4 أنيق متدرج", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getInvoiceA4ModernContent($party), 'styles' => $this->getInvoiceA4ModernStyles($p, '#334155', 'gradient'),
                'settings' => $this->makeSettings($p, '#334155')],
        ];
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  GROUP 8: STATEMENT PRESETS (Supplier / Client statements)  ║
    // ╚══════════════════════════════════════════════════════════════╝
    //
    // كشف الحساب ليس مستنداً ببنود وأسعار بل سجلُّ حركةٍ على حساب طرف:
    // تاريخ ومرجع وبيان ومدين ودائن ورصيد جارٍ، يعلوه رصيدٌ افتتاحي
    // ويذيّله رصيدٌ ختامي. فحقوله من رموز `{{date}} {{reference}}
    // {{description}} {{debit}} {{credit}} {{balance}}` ومن `statement.*`،
    // لا من رموز الأصناف والإجماليات.
    //
    // والضريبة والخصم مطفآن في كل قوالبه: لا معنى لهما في كشف حساب.
    private function getStatementPresets($prefix, $p, $s, $party = 'العميل')
    {
        $noTotals = ['show_tax' => false, 'show_discount' => false, 'show_barcode' => false];

        return [
            ['name' => "$prefix - A4 احترافي كلاسيكي", 'paper_size' => 'A4', 'is_default' => true,
                'content' => $this->getStatementA4Content($party), 'styles' => $this->getStatementA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 12, $noTotals)],
            ['name' => "$prefix - A4 عصري ملون", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getStatementA4ModernContent($party), 'styles' => $this->getStatementA4ModernStyles($p, $s),
                'settings' => $this->makeSettings($p, $s, 12, $noTotals)],
            ['name' => "$prefix - A4 كلاسيكي أزرق", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getStatementA4Content($party), 'styles' => $this->getStatementA4Styles('#1e3a8a', '#64748b'),
                'settings' => $this->makeSettings('#1e3a8a', '#64748b', 12, $noTotals)],
            ['name' => "$prefix - A4 فاخر داكن", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getStatementA4ModernContent($party), 'styles' => $this->getStatementA4ModernStyles('#1f2937', '#6b7280', 'dark'),
                'settings' => $this->makeSettings('#1f2937', '#6b7280', 12, $noTotals)],
            ['name' => "$prefix - A4 أخضر زمردي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getStatementA4Content($party), 'styles' => $this->getStatementA4Styles('#065f46', '#6b7280'),
                'settings' => $this->makeSettings('#065f46', '#6b7280', 12, $noTotals)],
            ['name' => "$prefix - A4 دافئ نحاسي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getStatementA4ModernContent($party), 'styles' => $this->getStatementA4ModernStyles('#b45309', '#78350f', 'frame'),
                'settings' => $this->makeSettings('#b45309', '#78350f', 12, $noTotals)],
            ['name' => "$prefix - A4 بسيط ونظيف", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getStatementA4Content($party), 'styles' => $this->getStatementA4Styles('#4b5563', '#9ca3af', 'minimal'),
                'settings' => $this->makeSettings('#4b5563', '#9ca3af', 11, $noTotals)],
            ['name' => "$prefix - A4 مضغوط كثيف", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getStatementA4CompactContent($party), 'styles' => $this->getStatementA4Styles($p, $s, 'compact'),
                'settings' => $this->makeSettings($p, $s, 10, $noTotals + ['show_logo' => false])],
            ['name' => "$prefix - حراري 80 مم", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getStatementThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11, $noTotals)],
            ['name' => "$prefix - حراري 58 مم مصغر", 'paper_size' => 'Thermal_58mm', 'is_default' => false,
                'content' => $this->getStatementThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 10, $noTotals + ['show_logo' => false])],
        ];
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║               محتوى القوالب — كشوف الحسابات                ║
    // ╚══════════════════════════════════════════════════════════════╝
    //
    // كشف الحساب ليس مستنداً ببنود وأسعار بل سجلُّ حركةٍ على حساب طرف:
    // تاريخ ومرجع وبيان ومدين ودائن ورصيد جارٍ، يعلوه رصيدٌ افتتاحي
    // ويذيّله رصيدٌ ختامي. فحقوله من رموز الحركة و`statement.*`، لا من
    // رموز الأصناف والإجماليات. والضريبة والخصم مطفآن في كل قوالبه.

    /** كشف حساب A4 كلاسيكي — `colgroup` لازمة: بها وحدها يثبت عرض «البيان» فلا يتكسّر حرفاً حرفاً */
    private function getStatementA4Content($party = 'العميل')
    {
        return str_replace('__PARTY__', $party, <<<'HTML'
<div class="doc-ds stmt-wrapper">
    <div class="header-line"></div>

    <div class="stmt-header">
        <div class="company-block">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="company-logo" alt="">
            @endif
            <h1 class="company-name">{{business.name}}</h1>
            <p class="meta-item">{{business.address}}</p>
            <p class="meta-item">{{business.phone}} — {{business.tax_number}}</p>
        </div>

        <div class="doc-block">
            <div class="doc-title-badge">{{document.title}}</div>
            <table class="doc-meta-table">
                <tr><td class="label">رقم الحساب</td><td>{{document.number}}</td></tr>
                <tr><td class="label">تاريخ الإصدار</td><td>{{document.date}}</td></tr>
                <tr><td class="label">من تاريخ</td><td>{{statement.from}}</td></tr>
                <tr><td class="label">إلى تاريخ</td><td>{{statement.to}}</td></tr>
            </table>
        </div>
    </div>

    <div class="party-card">
        <div class="party-title">بيانات __PARTY__</div>
        <div class="kv-grid">
            <div class="kv"><span class="kv-label">الاسم</span><span class="kv-value">{{customer.name}}</span></div>
            <div class="kv"><span class="kv-label">الرقم</span><span class="kv-value">{{customer.code}}</span></div>
            <div class="kv"><span class="kv-label">الهاتف</span><span class="kv-value">{{customer.phone}}</span></div>
            <div class="kv"><span class="kv-label">الرقم الضريبي</span><span class="kv-value">{{customer.tax_number}}</span></div>
            <div class="kv kv--wide"><span class="kv-label">العنوان</span><span class="kv-value">{{customer.address}}</span></div>
        </div>
    </div>

    <table class="stmt-table">
        <colgroup>
            <col class="c-date"><col class="c-ref"><col class="c-desc">
            <col class="c-num"><col class="c-num"><col class="c-bal">
        </colgroup>
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>المرجع</th>
                <th>البيان</th>
                <th>مدين</th>
                <th>دائن</th>
                <th>الرصيد</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening-row">
                <td colspan="5">الرصيد الافتتاحي</td>
                <td class="num-cell">{{statement.opening}}</td>
            </tr>
            {{#items}}
            <tr>
                <td class="num-cell">{{date}}</td>
                <td>{{reference}}</td>
                <td class="desc-cell">{{description}}</td>
                <td class="num-cell">{{debit}}</td>
                <td class="num-cell">{{credit}}</td>
                <td class="num-cell">{{balance}}</td>
            </tr>
            {{/items}}
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td colspan="3">إجمالي حركة الفترة</td>
                <td class="num-cell">{{statement.debit}}</td>
                <td class="num-cell">{{statement.credit}}</td>
                <td></td>
            </tr>
            <tr class="grand-total-row">
                <td colspan="5">الرصيد الختامي ({{statement.direction}})</td>
                <td class="num-cell">{{statement.closing}}</td>
            </tr>
        </tfoot>
    </table>

    <div class="totals-band">
        <div class="totals-side">
            @if(!empty(document.notes))
            <div class="notes-block"><strong>الحساب:</strong> {{document.notes}}</div>
            @endif
            <p class="foot-note">عدد الحركات: {{statement.count}} — العملة: {{business.currency}}</p>
        </div>
        <div class="totals-box">
            <table class="tot-table">
                <tr><td>الرصيد الافتتاحي</td><td>{{statement.opening}}</td></tr>
                <tr><td>إجمالي المدين</td><td>{{statement.debit}}</td></tr>
                <tr><td>إجمالي الدائن</td><td>{{statement.credit}}</td></tr>
                <tr class="grand-total"><td>الرصيد الختامي</td><td>{{statement.closing}}</td></tr>
            </table>
        </div>
    </div>

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="doc-foot">
        <div class="sigs">
            <div class="sig-block"><div class="sig-label">المحاسب</div><div class="sig-line"></div></div>
            <div class="sig-block"><div class="sig-label">المدير المالي</div><div class="sig-line"></div></div>
        </div>
    </div>
</div>
HTML);
    }

    /** كشف حساب A4 عصري — بطاقات ملخّص فوق جدول الحركة */
    private function getStatementA4ModernContent($party = 'العميل')
    {
        return str_replace('__PARTY__', $party, <<<'HTML'
<div class="doc-ds stmt-modern">
    <div class="modern-header">
        <div class="brand-area">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="modern-logo" alt="">
            @endif
            <div>
                <h1>{{business.name}}</h1>
                <span class="brand-meta">{{business.phone}} — {{business.tax_number}}</span>
            </div>
        </div>
        <div class="modern-badge">
            {{document.title}}
            <span class="badge-sub">{{document.number}}</span>
        </div>
    </div>

    <div class="modern-meta-strip">
        <span>__PARTY__: <strong>{{customer.name}}</strong></span>
        <span>الرقم: <strong>{{customer.code}}</strong></span>
        <span>الفترة: <strong>{{statement.from}} — {{statement.to}}</strong></span>
    </div>

    <div class="sum-cards">
        <div class="sum-card">
            <span class="sum-label">الرصيد الافتتاحي</span>
            <span class="sum-value">{{statement.opening}}</span>
        </div>
        <div class="sum-card">
            <span class="sum-label">إجمالي المدين</span>
            <span class="sum-value">{{statement.debit}}</span>
        </div>
        <div class="sum-card">
            <span class="sum-label">إجمالي الدائن</span>
            <span class="sum-value">{{statement.credit}}</span>
        </div>
        <div class="sum-card sum-card--grand">
            <span class="sum-label">الرصيد الختامي</span>
            <span class="sum-value">{{statement.closing}}</span>
        </div>
    </div>

    <table class="modern-stmt-table">
        <colgroup>
            <col class="c-date"><col class="c-ref"><col class="c-desc">
            <col class="c-num"><col class="c-num"><col class="c-bal">
        </colgroup>
        <thead>
            <tr><th>التاريخ</th><th>المرجع</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr>
        </thead>
        <tbody>
            <tr class="opening-row"><td colspan="5">الرصيد الافتتاحي</td><td class="num-cell">{{statement.opening}}</td></tr>
            {{#items}}
            <tr>
                <td class="num-cell">{{date}}</td>
                <td>{{reference}}</td>
                <td class="desc-cell">{{description}}</td>
                <td class="num-cell">{{debit}}</td>
                <td class="num-cell">{{credit}}</td>
                <td class="num-cell">{{balance}}</td>
            </tr>
            {{/items}}
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3">المجموع</td>
                <td class="num-cell">{{statement.debit}}</td>
                <td class="num-cell">{{statement.credit}}</td>
                <td class="num-cell">{{statement.closing}}</td>
            </tr>
        </tfoot>
    </table>

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="modern-foot">
        <span>عدد الحركات: {{statement.count}}</span>
        <span>العملة: {{business.currency}}</span>
        <span>الرصيد الختامي {{statement.direction}}</span>
    </div>
</div>
HTML);
    }

    /** كشف حساب A4 مضغوط — أكبر عدد صفوف في الورقة */
    private function getStatementA4CompactContent($party = 'العميل')
    {
        return str_replace('__PARTY__', $party, <<<'HTML'
<div class="doc-ds stmt-wrapper stmt-compact">
    <div class="compact-head">
        <strong>{{business.name}}</strong>
        <span>{{document.title}} — __PARTY__: {{customer.name}} ({{customer.code}})</span>
        <span>{{statement.from}} — {{statement.to}}</span>
    </div>

    <table class="stmt-table">
        <colgroup>
            <col class="c-date"><col class="c-ref"><col class="c-desc">
            <col class="c-num"><col class="c-num"><col class="c-bal">
        </colgroup>
        <thead>
            <tr><th>التاريخ</th><th>المرجع</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr>
        </thead>
        <tbody>
            <tr class="opening-row"><td colspan="5">رصيد افتتاحي</td><td class="num-cell">{{statement.opening}}</td></tr>
            {{#items}}
            <tr>
                <td class="num-cell">{{date}}</td>
                <td>{{reference}}</td>
                <td class="desc-cell">{{description}}</td>
                <td class="num-cell">{{debit}}</td>
                <td class="num-cell">{{credit}}</td>
                <td class="num-cell">{{balance}}</td>
            </tr>
            {{/items}}
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td colspan="3">الحركة</td>
                <td class="num-cell">{{statement.debit}}</td>
                <td class="num-cell">{{statement.credit}}</td>
                <td></td>
            </tr>
            <tr class="grand-total-row">
                <td colspan="5">الرصيد الختامي ({{statement.direction}})</td>
                <td class="num-cell">{{statement.closing}}</td>
            </tr>
        </tfoot>
    </table>

    <p class="foot-note">عدد الحركات: {{statement.count}} — العملة: {{business.currency}}</p>
</div>
HTML);
    }

    /** كشف حساب حراري — ثلاثة أعمدة، فعرض الشريط لا يحتمل ستّة */
    private function getStatementThermalContent()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt">
    <div class="t-header">
        <h2 class="t-company">{{business.name}}</h2>
        <p class="t-sub">{{business.phone}}</p>
    </div>
    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <p class="t-sub">{{customer.name}} ({{customer.code}})</p>
    <p class="t-sub">{{statement.from}} — {{statement.to}}</p>
    <div class="t-divider"></div>
    <div class="t-total-row"><span>رصيد افتتاحي</span><span>{{statement.opening}}</span></div>
    <div class="t-divider"></div>
    <table class="t-items">
        <thead><tr><th>التاريخ</th><th>البيان</th><th>الرصيد</th></tr></thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="t-num">{{date}}</td>
                <td class="t-name">{{description}}</td>
                <td class="t-num">{{balance}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>
    <div class="t-divider"></div>
    <div class="t-total-row"><span>مدين</span><span>{{statement.debit}}</span></div>
    <div class="t-total-row"><span>دائن</span><span>{{statement.credit}}</span></div>
    <div class="t-total-row t-grand"><span>الرصيد الختامي</span><span>{{statement.closing}}</span></div>
    <p class="t-footer">عدد الحركات: {{statement.count}}</p>
    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  GROUP 2: VOUCHER PRESETS (Receipt / Payment Vouchers)      ║
    // ╚══════════════════════════════════════════════════════════════╝
    private function getVoucherPresets($prefix, $p, $s)
    {
        return [
            ['name' => "$prefix - A4 احترافي", 'paper_size' => 'A4', 'is_default' => true,
                'content' => $this->getVoucherA4Content(), 'styles' => $this->getVoucherA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_tax' => false, 'show_discount' => false])],
            ['name' => "$prefix - A4 عصري", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getVoucherA4ModernContent(), 'styles' => $this->getVoucherA4ModernStyles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_tax' => false, 'show_discount' => false])],
            ['name' => "$prefix - A4 كلاسيكي أزرق", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getVoucherA4Content(), 'styles' => $this->getVoucherA4Styles('#1e40af', '#64748b'),
                'settings' => $this->makeSettings('#1e40af', '#64748b', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 فاخر داكن", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getVoucherA4ModernContent(), 'styles' => $this->getVoucherA4ModernStyles('#1f2937', '#6b7280', 'dark'),
                'settings' => $this->makeSettings('#1f2937', '#6b7280', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 أنيق أخضر", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getVoucherA4Content(), 'styles' => $this->getVoucherA4Styles('#065f46', '#6b7280', 'gradient'),
                'settings' => $this->makeSettings('#065f46', '#6b7280', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 بسيط نظيف", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getVoucherA4Content(), 'styles' => $this->getVoucherA4Styles('#4b5563', '#9ca3af', 'minimal'),
                'settings' => $this->makeSettings('#4b5563', '#9ca3af', 12, ['show_tax' => false])],
            ['name' => "$prefix - حراري 80 مم", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getVoucherThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11, ['show_tax' => false])],
            ['name' => "$prefix - حراري 80 مم حديث", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getVoucherThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11, ['show_tax' => false])],
            ['name' => "$prefix - حراري 58 مم", 'paper_size' => 'Thermal_58mm', 'is_default' => false,
                'content' => $this->getVoucherThermalMiniContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 10, ['show_tax' => false, 'show_logo' => false])],
            ['name' => "$prefix - A4 دافئ نحاسي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getVoucherA4ModernContent(), 'styles' => $this->getVoucherA4ModernStyles('#92400e', '#78350f', 'frame'),
                'settings' => $this->makeSettings('#92400e', '#78350f', 13, ['show_tax' => false])],
        ];
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  GROUP 3: JOURNAL ENTRY PRESETS                             ║
    // ╚══════════════════════════════════════════════════════════════╝
    private function getJournalPresets($prefix, $p, $s)
    {
        return [
            ['name' => "$prefix - A4 احترافي", 'paper_size' => 'A4', 'is_default' => true,
                'content' => $this->getJournalA4Content(), 'styles' => $this->getJournalA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_discount' => false, 'show_customer' => false])],
            ['name' => "$prefix - A4 عصري", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getJournalA4ModernContent(), 'styles' => $this->getJournalA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_discount' => false, 'show_customer' => false])],
            ['name' => "$prefix - A4 كلاسيكي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getJournalA4Content(), 'styles' => $this->getJournalA4Styles('#1e3a8a', '#64748b'),
                'settings' => $this->makeSettings('#1e3a8a', '#64748b')],
            ['name' => "$prefix - A4 داكن فاخر", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getJournalA4ModernContent(), 'styles' => $this->getJournalA4Styles('#1f2937', '#6b7280', 'dark'),
                'settings' => $this->makeSettings('#1f2937', '#6b7280')],
            ['name' => "$prefix - A4 أخضر زمردي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getJournalA4Content(), 'styles' => $this->getJournalA4Styles('#065f46', '#6b7280'),
                'settings' => $this->makeSettings('#065f46', '#6b7280')],
            ['name' => "$prefix - A4 بسيط", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getJournalA4Content(), 'styles' => $this->getJournalA4Styles('#4b5563', '#9ca3af', 'minimal'),
                'settings' => $this->makeSettings('#4b5563', '#9ca3af', 12)],
            ['name' => "$prefix - حراري 80 مم", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getJournalThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11)],
            ['name' => "$prefix - حراري 58 مم", 'paper_size' => 'Thermal_58mm', 'is_default' => false,
                'content' => $this->getJournalThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 10)],
            ['name' => "$prefix - A4 بنفسجي حديث", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getJournalA4ModernContent(), 'styles' => $this->getJournalA4Styles('#7c3aed', '#a78bfa', 'gradient'),
                'settings' => $this->makeSettings('#7c3aed', '#a78bfa')],
            ['name' => "$prefix - A4 دافئ", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getJournalA4Content(), 'styles' => $this->getJournalA4Styles('#b45309', '#78350f'),
                'settings' => $this->makeSettings('#b45309', '#78350f')],
        ];
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  GROUP 4: WAREHOUSE PRESETS (Permits, Transfers, Adj.)      ║
    // ╚══════════════════════════════════════════════════════════════╝
    private function getWarehousePresets($prefix, $p, $s)
    {
        return [
            ['name' => "$prefix - A4 احترافي", 'paper_size' => 'A4', 'is_default' => true,
                'content' => $this->getWarehouseA4Content(), 'styles' => $this->getWarehouseA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_tax' => false, 'show_discount' => false])],
            ['name' => "$prefix - A4 عصري", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getWarehouseA4ModernContent(), 'styles' => $this->getWarehouseA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_tax' => false, 'show_discount' => false])],
            ['name' => "$prefix - A4 كلاسيكي أزرق", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getWarehouseA4Content(), 'styles' => $this->getWarehouseA4Styles('#1e3a8a', '#64748b'),
                'settings' => $this->makeSettings('#1e3a8a', '#64748b', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 داكن", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getWarehouseA4ModernContent(), 'styles' => $this->getWarehouseA4Styles('#1f2937', '#6b7280', 'dark'),
                'settings' => $this->makeSettings('#1f2937', '#6b7280', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 أخضر", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getWarehouseA4Content(), 'styles' => $this->getWarehouseA4Styles('#065f46', '#047857'),
                'settings' => $this->makeSettings('#065f46', '#047857', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 بسيط", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getWarehouseA4Content(), 'styles' => $this->getWarehouseA4Styles('#4b5563', '#9ca3af', 'minimal'),
                'settings' => $this->makeSettings('#4b5563', '#9ca3af', 12, ['show_tax' => false])],
            ['name' => "$prefix - حراري 80 مم", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getWarehouseThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11, ['show_tax' => false])],
            ['name' => "$prefix - حراري 58 مم", 'paper_size' => 'Thermal_58mm', 'is_default' => false,
                'content' => $this->getWarehouseThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 10, ['show_tax' => false, 'show_logo' => false])],
            ['name' => "$prefix - A4 برتقالي حديث", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getWarehouseA4ModernContent(), 'styles' => $this->getWarehouseA4Styles('#ea580c', '#fb923c', 'gradient'),
                'settings' => $this->makeSettings('#ea580c', '#fb923c', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 بنفسجي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getWarehouseA4Content(), 'styles' => $this->getWarehouseA4Styles('#7c3aed', '#a78bfa'),
                'settings' => $this->makeSettings('#7c3aed', '#a78bfa', 13, ['show_tax' => false])],
        ];
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  GROUP 5: QUOTE PRESETS                                     ║
    // ╚══════════════════════════════════════════════════════════════╝
    private function getQuotePresets($prefix, $p, $s, string $kind = 'quote')
    {
        $presets = [
            ['name' => "$prefix - A4 احترافي", 'paper_size' => 'A4', 'is_default' => true,
                'content' => $this->getQuoteA4Content(), 'styles' => $this->getQuoteA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s)],
            ['name' => "$prefix - A4 عصري", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getQuoteA4ModernContent(), 'styles' => $this->getQuoteA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s)],
            ['name' => "$prefix - A4 كلاسيكي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getQuoteA4Content(), 'styles' => $this->getQuoteA4Styles('#1e3a8a', '#64748b'),
                'settings' => $this->makeSettings('#1e3a8a', '#64748b')],
            ['name' => "$prefix - A4 داكن فاخر", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getQuoteA4ModernContent(), 'styles' => $this->getQuoteA4Styles('#1f2937', '#374151', 'dark'),
                'settings' => $this->makeSettings('#1f2937', '#374151')],
            ['name' => "$prefix - A4 أنيق ذهبي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getQuoteA4Content(), 'styles' => $this->getQuoteA4Styles('#b45309', '#78350f', 'gradient'),
                'settings' => $this->makeSettings('#b45309', '#78350f')],
            ['name' => "$prefix - A4 بسيط", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getQuoteA4Content(), 'styles' => $this->getQuoteA4Styles('#4b5563', '#9ca3af', 'minimal'),
                'settings' => $this->makeSettings('#4b5563', '#9ca3af', 12)],
            ['name' => "$prefix - حراري 80 مم", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getQuoteThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11)],
            ['name' => "$prefix - حراري 58 مم", 'paper_size' => 'Thermal_58mm', 'is_default' => false,
                'content' => $this->getQuoteThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 10)],
            ['name' => "$prefix - A4 أزرق حديث", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getQuoteA4ModernContent(), 'styles' => $this->getQuoteA4Styles('#2563eb', '#3b82f6', 'gradient'),
                'settings' => $this->makeSettings('#2563eb', '#3b82f6')],
            ['name' => "$prefix - A4 أخضر طبيعي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getQuoteA4Content(), 'styles' => $this->getQuoteA4Styles('#065f46', '#047857'),
                'settings' => $this->makeSettings('#065f46', '#047857')],
        ];

        if ($kind !== 'sales_order') {
            return $presets;
        }

        return array_map(
            fn ($preset) => ['content' => $this->salesOrderWording($preset['content'])] + $preset,
            $presets
        );
    }

    /**
     * عباراتُ عرض السعر في نصّ القالب ← عباراتُ أمر البيع.
     *
     * أمرُ البيع مستندٌ مؤكَّدٌ لا عرضٌ تقديريّ، وليس له «صالح حتى»:
     * `normalizeQuote()` تُفرغ `due_date` له، فكان الإشعار العصريّ يطبع
     * «صالح حتى» بلا تاريخ. والعنوانُ نفسُه من `document.title` فلا يُمَسّ.
     */
    private function salesOrderWording(string $content): string
    {
        $notice = '<div class="notice">أمر بيع مؤكَّد وليس فاتورة — تُصدَر الفاتورة عند التسليم</div>';

        return strtr($content, [
            '<td class="label">رقم العرض</td>' => '<td class="label">رقم الأمر</td>',
            '<div class="notice">هذا عرض سعر تقديري وليس فاتورة — الأسعار قابلة للتغيير حسب التوفّر</div>' => $notice,
            '<div class="notice">عرض سعر تقديري — صالح حتى {{document.due_date}}</div>' => $notice,
            '<h3>تفاصيل العرض</h3>' => '<h3>تفاصيل الأمر</h3>',
            '<td>الإجمالي المقدّر</td>' => '<td>الإجمالي</td>',
            'مقدّم العرض' => 'مسؤول المبيعات',
            '<p class="t-footer">عرض سعر تقديري وليس فاتورة</p>' => '<p class="t-footer">أمر بيع — ليس فاتورة</p>',
            // «التصميم الموحّد»
            '<span class="u-k">رقم العرض</span>' => '<span class="u-k">رقم الأمر</span>',
            '<span>رقم العرض :</span>' => '<span>رقم الأمر :</span>',
            '<div class="u-notice">هذا عرض سعر تقديري وليس فاتورة — الأسعار قابلة للتغيير حسب التوفّر</div>' => '<div class="u-notice">أمر بيع مؤكَّد وليس فاتورة — تُصدَر الفاتورة عند التسليم</div>',
            '<span>الإجمالي المقدّر</span>' => '<span>الإجمالي</span>',
        ]);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  GROUP 6: CONTRACT PRESETS (A4 only - no thermal)           ║
    // ╚══════════════════════════════════════════════════════════════╝
    private function getContractPresets($prefix, $p, $s)
    {
        return [
            ['name' => "$prefix - A4 رسمي قانوني", 'paper_size' => 'A4', 'is_default' => true,
                'content' => $this->getContractA4Content(), 'styles' => $this->getContractA4Styles($p, $s, 'frame'),
                'settings' => $this->makeSettings($p, $s, 13, ['show_tax' => false, 'show_discount' => false, 'show_barcode' => false, 'show_qr' => false])],
            ['name' => "$prefix - A4 عصري حديث", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4ModernContent(), 'styles' => $this->getContractA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_tax' => false, 'show_discount' => false])],
            ['name' => "$prefix - A4 كلاسيكي أزرق", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4Content(), 'styles' => $this->getContractA4Styles('#1e3a8a', '#64748b'),
                'settings' => $this->makeSettings('#1e3a8a', '#64748b', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 داكن رسمي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4Content(), 'styles' => $this->getContractA4Styles('#1f2937', '#374151', 'dark'),
                'settings' => $this->makeSettings('#1f2937', '#374151', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 أنيق ذهبي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4ModernContent(), 'styles' => $this->getContractA4Styles('#92400e', '#78350f', 'gradient'),
                'settings' => $this->makeSettings('#92400e', '#78350f', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 أخضر رسمي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4Content(), 'styles' => $this->getContractA4Styles('#065f46', '#047857', 'frame'),
                'settings' => $this->makeSettings('#065f46', '#047857', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 بنفسجي أنيق", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4ModernContent(), 'styles' => $this->getContractA4Styles('#5b21b6', '#7c3aed', 'frame'),
                'settings' => $this->makeSettings('#5b21b6', '#7c3aed', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 بسيط نظيف", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4Content(), 'styles' => $this->getContractA4Styles('#4b5563', '#9ca3af', 'minimal'),
                'settings' => $this->makeSettings('#4b5563', '#9ca3af', 12, ['show_tax' => false])],
            ['name' => "$prefix - A4 أحمر رسمي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4Content(), 'styles' => $this->getContractA4Styles('#991b1b', '#b91c1c', 'frame'),
                'settings' => $this->makeSettings('#991b1b', '#b91c1c', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 سماوي حديث", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getContractA4ModernContent(), 'styles' => $this->getContractA4Styles('#0e7490', '#06b6d4', 'gradient'),
                'settings' => $this->makeSettings('#0e7490', '#06b6d4', 13, ['show_tax' => false])],
        ];
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  GROUP 7: SALARY SLIP PRESETS                               ║
    // ╚══════════════════════════════════════════════════════════════╝
    private function getSalaryPresets($prefix, $p, $s, string $kind = 'salary_slip')
    {
        $presets = $this->salaryPresetList($prefix, $p, $s);

        if ($kind !== 'payroll_sheet') {
            return $presets;
        }

        return array_map(function (array $preset) {
            $preset['content'] = $this->payrollSheetWording($preset['content']);

            return $preset;
        }, $presets);
    }

    /**
     * نصوصُ «كشف مسير رواتب» فوق قوالب القسيمة: الطرفُ مسيرٌ لا موظف،
     * والسطرُ موظفٌ لا بند، والإجماليُّ رواتبُ لا راتب.
     */
    private function payrollSheetWording(string $content): string
    {
        return strtr($content, [
            '<div class="kv"><span class="kv-label">رقم الهاتف</span><span class="kv-value">{{customer.phone}}</span></div>' => '<div class="kv"><span class="kv-label">رقم المسير</span><span class="kv-value">{{customer.code}}</span></div>',
            '<p>{{customer.phone}}</p>' => '<p>رقم المسير: {{customer.code}}</p>',
            'بيانات الموظف' => 'بيانات المسير',
            'اسم الموظف' => 'اسم المسير',
            'القسم / الإدارة' => 'النطاق',
            '<th class="c-name">البند</th>' => '<th class="c-name">الموظف</th>',
            '<th>البند</th>' => '<th>الموظف</th>',
            'إجمالي المستحقات' => 'إجمالي الرواتب',
            'إجمالي الاستقطاعات' => 'إجمالي الخصومات',
            'صافي الراتب' => 'صافي الرواتب',
            'ملخّص الراتب' => 'ملخّص المسير',
            '<strong>الموظف:</strong>' => '<strong>المسير:</strong>',
            '<strong>القسم:</strong>' => '<strong>النطاق:</strong>',
            'استلام الموظف' => 'المراجعة',
            '<div class="sig-title">الموظف</div>' => '<div class="sig-title">المراجعة</div>',
            'وثيقةٌ سريّة تخصّ الموظف وحده' => 'وثيقةٌ سريّة',
        ]);
    }

    private function salaryPresetList($prefix, $p, $s): array
    {
        return [
            ['name' => "$prefix - A4 احترافي", 'paper_size' => 'A4', 'is_default' => true,
                'content' => $this->getSalaryA4Content(), 'styles' => $this->getSalaryA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_tax' => false, 'show_qr' => false])],
            ['name' => "$prefix - A4 عصري", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getSalaryA4ModernContent(), 'styles' => $this->getSalaryA4Styles($p, $s),
                'settings' => $this->makeSettings($p, $s, 13, ['show_tax' => false, 'show_qr' => false])],
            ['name' => "$prefix - A4 كلاسيكي أزرق", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getSalaryA4Content(), 'styles' => $this->getSalaryA4Styles('#1e3a8a', '#64748b'),
                'settings' => $this->makeSettings('#1e3a8a', '#64748b', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 داكن", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getSalaryA4ModernContent(), 'styles' => $this->getSalaryA4Styles('#1f2937', '#374151', 'dark'),
                'settings' => $this->makeSettings('#1f2937', '#374151', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 أخضر", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getSalaryA4Content(), 'styles' => $this->getSalaryA4Styles('#065f46', '#047857'),
                'settings' => $this->makeSettings('#065f46', '#047857', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 بسيط", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getSalaryA4Content(), 'styles' => $this->getSalaryA4Styles('#4b5563', '#9ca3af', 'minimal'),
                'settings' => $this->makeSettings('#4b5563', '#9ca3af', 12, ['show_tax' => false])],
            ['name' => "$prefix - حراري 80 مم", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                'content' => $this->getSalaryThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 11, ['show_tax' => false])],
            ['name' => "$prefix - حراري 58 مم", 'paper_size' => 'Thermal_58mm', 'is_default' => false,
                'content' => $this->getSalaryThermalContent(), 'styles' => $this->getThermalStyles(),
                'settings' => $this->makeSettings('#000', '#555', 10, ['show_tax' => false, 'show_logo' => false])],
            ['name' => "$prefix - A4 بنفسجي", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getSalaryA4ModernContent(), 'styles' => $this->getSalaryA4Styles('#7c3aed', '#a78bfa'),
                'settings' => $this->makeSettings('#7c3aed', '#a78bfa', 13, ['show_tax' => false])],
            ['name' => "$prefix - A4 دافئ", 'paper_size' => 'A4', 'is_default' => false,
                'content' => $this->getSalaryA4Content(), 'styles' => $this->getSalaryA4Styles('#b45309', '#78350f'),
                'settings' => $this->makeSettings('#b45309', '#78350f', 13, ['show_tax' => false])],
        ];
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                  محتوى القوالب — الفواتير                  ║
    // ╚══════════════════════════════════════════════════════════════╝

    /** فاتورة A4 كلاسيكية: ترويسة، بطاقة طرف، جدول أصناف، شريط إجماليات، تواقيع */
    private function getInvoiceA4Content($party = 'العميل')
    {
        return str_replace('__PARTY__', $party, <<<'HTML'
<div class="doc-ds inv-doc">
    <div class="header-line"></div>

    <div class="header-section">
        <div class="company-block">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="company-logo" alt="">
            @endif
            <h1 class="company-name">{{business.name}}</h1>
            <p class="meta-item">الرقم الضريبي: {{business.tax_number}}</p>
            <p class="meta-item">السجل التجاري: {{business.commercial_registration}}</p>
            <p class="meta-item">{{business.address}}</p>
            <p class="meta-item">{{business.phone}} — {{business.email}}</p>
        </div>

        <div class="doc-block">
            <div class="doc-title-badge">{{document.title}}</div>
            <table class="doc-meta-table">
                <tr><td class="label">الرقم</td><td>{{document.number}}</td></tr>
                <tr><td class="label">التاريخ</td><td>{{document.date}}</td></tr>
                @if(!empty(document.due_date))
                <tr><td class="label">الاستحقاق</td><td>{{document.due_date}}</td></tr>
                @endif
                <tr><td class="label">طريقة الدفع</td><td>{{document.payment_method}}</td></tr>
            </table>
        </div>
    </div>

    <div class="party-card">
        <div class="party-title">بيانات __PARTY__</div>
        <div class="kv-grid">
            <div class="kv"><span class="kv-label">الاسم</span><span class="kv-value">{{customer.name}}</span></div>
            <div class="kv"><span class="kv-label">الرقم الضريبي</span><span class="kv-value">{{customer.tax_number}}</span></div>
            <div class="kv"><span class="kv-label">الهاتف</span><span class="kv-value">{{customer.phone}}</span></div>
            <div class="kv"><span class="kv-label">العنوان</span><span class="kv-value">{{customer.address}}</span></div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">الصنف</th>
                <th>الكمية</th>
                <th>الوحدة</th>
                <th>السعر</th>
                <th>الخصم</th>
                <th>الضريبة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name">
                    <span class="item-name">{{name}}</span>
                    <span class="item-code">{{code}}</span>
                </td>
                <td class="num-cell">{{quantity}}</td>
                <td>{{unit}}</td>
                <td class="num-cell">{{price}}</td>
                <td class="num-cell">{{discount}}</td>
                <td class="num-cell">{{tax}}</td>
                <td class="num-cell">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="totals-band">
        <div class="totals-side">
            @if(!empty(document.notes))
            <div class="notes-block"><strong>ملاحظات:</strong> {{document.notes}}</div>
            @endif
            @if(!empty(document.qr_code))
            <div class="qr-box">
                <span class="qr-hint">امسح الرمز للتحقّق من بيانات المستند</span>
                <img src="{{document.qr_code}}" class="qr-img" alt="">
            </div>
            @endif
        </div>

        <div class="totals-box">
            <table class="tot-table">
                <tr><td>المجموع قبل الضريبة</td><td>{{totals.subtotal}}</td></tr>
                @if(totals.discount > 0)
                <tr class="discount-row"><td>الخصم</td><td>{{totals.discount}}</td></tr>
                @endif
                @if(totals.shipping > 0)
                <tr class="shipping-row"><td>الشحن</td><td>{{totals.shipping}}</td></tr>
                @endif
                @if(totals.tax > 0)
                <tr class="tax-row"><td>ضريبة القيمة المضافة</td><td>{{totals.tax}}</td></tr>
                @endif
                <tr class="grand-total"><td>الإجمالي شامل الضريبة</td><td>{{totals.grand_total}}</td></tr>
                @if(totals.paid > 0)
                <tr class="paid-row"><td>المدفوع</td><td>{{totals.paid}}</td></tr>
                @endif
                @if(totals.returned > 0)
                <tr class="returned-row"><td>مرتجع</td><td>- {{totals.returned}}</td></tr>
                @endif
                @if(totals.paid > 0 || totals.returned > 0)
                <tr class="remaining-row"><td>المتبقي</td><td>{{totals.remaining}}</td></tr>
                @endif
            </table>
        </div>
    </div>

    @if(!empty(document.barcode))
    <div class="barcode-box">
        <img src="{{document.barcode}}" class="barcode-img" alt="">
        <span class="barcode-value">{{document.number}}</span>
    </div>
    @endif

    <div class="doc-foot">
        <div class="sigs">
            <div class="sig-block">
                <div class="sig-label">المحاسب</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع</div>
            </div>
            <div class="sig-block">
                <div class="sig-label">__PARTY__</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع</div>
            </div>
        </div>
        <div class="foot-note">{{business.name}} — {{business.website}} — شكراً لتعاملكم معنا</div>
    </div>
</div>
HTML);
    }

    /** فاتورة A4 عصرية: ترويسة ملوّنة وبطاقات معلومات */
    private function getInvoiceA4ModernContent($party = 'العميل')
    {
        return str_replace('__PARTY__', $party, <<<'HTML'
<div class="doc-ds inv-modern">
    <div class="modern-header">
        <div class="brand-area">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="modern-logo" alt="">
            @endif
            <div>
                <h1>{{business.name}}</h1>
                <span class="brand-meta">{{business.tax_number}} — {{business.phone}}</span>
            </div>
        </div>
        <div class="modern-badge">
            {{document.title}}
            <span class="badge-sub">{{document.number}}</span>
        </div>
    </div>

    <div class="modern-meta-strip">
        <span>التاريخ: <strong>{{document.date}}</strong></span>
        @if(!empty(document.due_date))
        <span>الاستحقاق: <strong>{{document.due_date}}</strong></span>
        @endif
        <span>الدفع: <strong>{{document.payment_method}}</strong></span>
        <span>العملة: <strong>{{business.currency}}</strong></span>
    </div>

    <div class="info-cards">
        <div class="info-card">
            <h3>بيانات المنشأة</h3>
            <p>{{business.name}}</p>
            <p>الرقم الضريبي: {{business.tax_number}}</p>
            <p>{{business.address}}</p>
        </div>
        <div class="info-card">
            <h3>بيانات __PARTY__</h3>
            <p>{{customer.name}}</p>
            <p>الرقم الضريبي: {{customer.tax_number}}</p>
            <p>{{customer.address}} — {{customer.phone}}</p>
        </div>
    </div>

    <table class="modern-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">الصنف</th>
                <th>الكمية</th>
                <th>السعر</th>
                <th>الخصم</th>
                <th>الضريبة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name">
                    <span class="item-name">{{name}}</span>
                    <span class="item-code">{{code}} — {{unit}}</span>
                </td>
                <td class="num-cell">{{quantity}}</td>
                <td class="num-cell">{{price}}</td>
                <td class="num-cell">{{discount}}</td>
                <td class="num-cell">{{tax}}</td>
                <td class="num-cell">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="totals-band">
        <div class="totals-side">
            @if(!empty(document.notes))
            <div class="notes-block"><strong>ملاحظات:</strong> {{document.notes}}</div>
            @endif
            @if(!empty(document.qr_code))
            <div class="qr-box">
                <span class="qr-hint">امسح الرمز للتحقّق من بيانات المستند</span>
                <img src="{{document.qr_code}}" class="qr-img" alt="">
            </div>
            @endif
        </div>

        <div class="totals-box">
            <table class="tot-table">
                <tr><td>المجموع الفرعي</td><td>{{totals.subtotal}}</td></tr>
                @if(totals.discount > 0)
                <tr class="discount-row"><td>الخصم</td><td>{{totals.discount}}</td></tr>
                @endif
                @if(totals.shipping > 0)
                <tr class="shipping-row"><td>الشحن</td><td>{{totals.shipping}}</td></tr>
                @endif
                @if(totals.tax > 0)
                <tr class="tax-row"><td>الضريبة</td><td>{{totals.tax}}</td></tr>
                @endif
                <tr class="grand-total"><td>الإجمالي</td><td>{{totals.grand_total}}</td></tr>
                @if(totals.paid > 0)
                <tr class="paid-row"><td>المدفوع</td><td>{{totals.paid}}</td></tr>
                @endif
                @if(totals.returned > 0)
                <tr class="returned-row"><td>مرتجع</td><td>- {{totals.returned}}</td></tr>
                @endif
                @if(totals.paid > 0 || totals.returned > 0)
                <tr class="remaining-row"><td>المتبقي</td><td>{{totals.remaining}}</td></tr>
                @endif
            </table>
        </div>
    </div>

    @if(!empty(document.barcode))
    <div class="barcode-box">
        <img src="{{document.barcode}}" class="barcode-img" alt="">
        <span class="barcode-value">{{document.number}}</span>
    </div>
    @endif

    <div class="modern-sigs">
        <div class="sig-col"><div class="sig-title">المحاسب</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">__PARTY__</div><div class="sig-line"></div></div>
    </div>

    <div class="modern-foot">
        <span>{{business.name}}</span>
        <span>{{business.website}}</span>
        <span>{{business.email}}</span>
    </div>
</div>
HTML);
    }

    private function getInvoiceThermal80Content($party = 'العميل')
    {
        return str_replace('__PARTY__', $party, <<<'HTML'
<div class="doc-ds thermal-receipt">
    <div class="t-header">
        @if(!empty(business.logo))
        <img src="{{business.logo}}" class="t-logo" alt="">
        @endif
        <h2 class="t-company">{{business.name}}</h2>
        <p class="t-sub">{{business.address}}</p>
        <p class="t-sub">هاتف: {{business.phone}}</p>
        <p class="t-sub">الرقم الضريبي: {{business.tax_number}}</p>
    </div>

    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <div class="t-meta"><span>الرقم</span><span>{{document.number}}</span></div>
    <div class="t-meta"><span>التاريخ</span><span>{{document.date}}</span></div>
    <div class="t-meta"><span>الدفع</span><span>{{document.payment_method}}</span></div>
    <div class="t-divider"></div>
    <p class="t-party"><strong>__PARTY__:</strong> {{customer.name}}</p>
    <p class="t-party">{{customer.phone}}</p>

    <div class="t-divider"></div>
    <table class="t-items">
        <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>المجموع</th></tr></thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="t-name">{{name}}</td>
                <td class="t-num">{{quantity}}<span class="t-unit">{{unit}}</span></td>
                <td class="t-num">{{price}}</td>
                <td class="t-num">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="t-divider"></div>
    <div class="t-totals">
        <div class="t-total-row"><span>المجموع</span><span>{{totals.subtotal}}</span></div>
        @if(totals.discount > 0)
        <div class="t-total-row discount-row"><span>الخصم</span><span>{{totals.discount}}</span></div>
        @endif
        @if(totals.shipping > 0)
        <div class="t-total-row shipping-row"><span>الشحن</span><span>{{totals.shipping}}</span></div>
        @endif
        @if(totals.tax > 0)
        <div class="t-total-row tax-row"><span>الضريبة</span><span>{{totals.tax}}</span></div>
        @endif
        <div class="t-total-row t-grand"><span>الإجمالي</span><span>{{totals.grand_total}}</span></div>
        @if(totals.paid > 0)
        <div class="t-total-row"><span>المدفوع</span><span>{{totals.paid}}</span></div>
        @endif
        @if(totals.returned > 0)
        <div class="t-total-row"><span>مرتجع</span><span>- {{totals.returned}}</span></div>
        @endif
        @if(totals.paid > 0 || totals.returned > 0)
        <div class="t-total-row"><span>المتبقي</span><span>{{totals.remaining}}</span></div>
        @endif
    </div>

    @if(!empty(document.notes))
    <div class="t-divider"></div>
    <p class="t-party">{{document.notes}}</p>
    @endif

    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    <p class="t-footer">شكراً لتعاملكم معنا</p>
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML);
    }

    private function getInvoiceThermal80ModernContent()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt">
    <div class="t-header">
        @if(!empty(business.logo))
        <img src="{{business.logo}}" class="t-logo" alt="">
        @endif
        <h2 class="t-company">{{business.name}}</h2>
        <p class="t-sub">{{business.tax_number}}</p>
    </div>

    <div class="t-divider-double"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <p class="t-sub">{{document.number}} — {{document.date}}</p>
    <p class="t-sub">{{customer.name}}</p>
    <div class="t-divider"></div>

    <table class="t-items">
        <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>المجموع</th></tr></thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="t-name">{{name}}</td>
                <td class="t-num">{{quantity}}<span class="t-unit">{{unit}}</span></td>
                <td class="t-num">{{price}}</td>
                <td class="t-num">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="t-divider-double"></div>
    <div class="t-totals">
        <div class="t-total-row"><span>المجموع</span><span>{{totals.subtotal}}</span></div>
        @if(totals.discount > 0)
        <div class="t-total-row discount-row"><span>الخصم</span><span>{{totals.discount}}</span></div>
        @endif
        @if(totals.shipping > 0)
        <div class="t-total-row shipping-row"><span>الشحن</span><span>{{totals.shipping}}</span></div>
        @endif
        @if(totals.tax > 0)
        <div class="t-total-row tax-row"><span>الضريبة</span><span>{{totals.tax}}</span></div>
        @endif
        <div class="t-total-row t-grand"><span>الإجمالي</span><span>{{totals.grand_total}}</span></div>
        @if(totals.paid > 0)
        <div class="t-total-row"><span>المدفوع</span><span>{{totals.paid}}</span></div>
        @endif
        @if(totals.returned > 0)
        <div class="t-total-row"><span>مرتجع</span><span>- {{totals.returned}}</span></div>
        @endif
        @if(totals.paid > 0 || totals.returned > 0)
        <div class="t-total-row"><span>المتبقي</span><span>{{totals.remaining}}</span></div>
        @endif
    </div>

    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    <p class="t-footer">نسعد بخدمتكم دائماً</p>
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    private function getInvoiceThermal58Content()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt mini">
    <h3 class="t-company">{{business.name}}</h3>
    <p class="t-sub">{{business.tax_number}}</p>
    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <p class="t-sub">{{document.number}} — {{document.date}}</p>
    <div class="t-divider"></div>
    {{#items}}
    <div class="mini-item"><span>{{name}} ×{{quantity}}<span class="t-unit">{{unit}}</span></span><span>{{total}}</span></div>
    {{/items}}
    <div class="t-divider"></div>
    <div class="t-totals">
        <div class="t-total-row"><span>المجموع</span><span>{{totals.subtotal}}</span></div>
        @if(totals.discount > 0)
        <div class="t-total-row discount-row"><span>الخصم</span><span>{{totals.discount}}</span></div>
        @endif
        @if(totals.shipping > 0)
        <div class="t-total-row shipping-row"><span>الشحن</span><span>{{totals.shipping}}</span></div>
        @endif
        @if(totals.tax > 0)
        <div class="t-total-row tax-row"><span>الضريبة</span><span>{{totals.tax}}</span></div>
        @endif
        <div class="t-total-row t-grand"><span>الإجمالي</span><span>{{totals.grand_total}}</span></div>
        @if(totals.paid > 0)
        <div class="t-total-row"><span>المدفوع</span><span>{{totals.paid}}</span></div>
        @endif
        @if(totals.returned > 0)
        <div class="t-total-row"><span>مرتجع</span><span>- {{totals.returned}}</span></div>
        @endif
        @if(totals.paid > 0 || totals.returned > 0)
        <div class="t-total-row"><span>المتبقي</span><span>{{totals.remaining}}</span></div>
        @endif
    </div>
    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                  محتوى القوالب — السندات                   ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function getVoucherA4Content()
    {
        return <<<'HTML'
<div class="doc-ds vch-doc">
    <div class="header-line"></div>

    <div class="header-section">
        <div class="company-block">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="company-logo" alt="">
            @endif
            <h1 class="company-name">{{business.name}}</h1>
            <p class="meta-item">الرقم الضريبي: {{business.tax_number}}</p>
            <p class="meta-item">السجل التجاري: {{business.commercial_registration}}</p>
            <p class="meta-item">{{business.address}}</p>
        </div>

        <div class="doc-block">
            <div class="doc-title-badge">{{document.title}}</div>
            <table class="doc-meta-table">
                <tr><td class="label">رقم السند</td><td>{{document.number}}</td></tr>
                <tr><td class="label">التاريخ</td><td>{{document.date}}</td></tr>
                <tr><td class="label">طريقة الدفع</td><td>{{document.payment_method}}</td></tr>
            </table>
        </div>
    </div>

    <div class="amount-box">
        <span class="amount-label">المبلغ المستحق</span>
        <span class="amount-value">{{totals.grand_total}} <span class="amount-unit">{{business.currency}}</span></span>
    </div>

    <div class="block">
        <div class="block-title">بيانات الطرف</div>
        <table class="kv-table">
            <tr><td class="label-cell">استلمنا من / صرفنا إلى</td><td class="value-cell">{{customer.name}}</td></tr>
            <tr><td class="label-cell">العنوان</td><td class="value-cell">{{customer.address}}</td></tr>
            <tr><td class="label-cell">رقم الهاتف</td><td class="value-cell">{{customer.phone}}</td></tr>
            <tr><td class="label-cell">طريقة الدفع</td><td class="value-cell">{{document.payment_method}}</td></tr>
        </table>
    </div>

    @if(!empty(document.notes))
    <div class="notes-block"><strong>البيان / الغرض:</strong> {{document.notes}}</div>
    @endif

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="doc-foot">
        <div class="sigs">
            <div class="sig-block">
                <div class="sig-label">المستلم</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع</div>
            </div>
            <div class="sig-block">
                <div class="sig-label">المسلّم</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع</div>
            </div>
            <div class="sig-block">
                <div class="sig-label">المعتمد</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع</div>
            </div>
        </div>
        <div class="foot-note">{{business.name}} — هذا السند لا يُعتدّ به إلا بتوقيع الطرفين</div>
    </div>
</div>
HTML;
    }

    private function getVoucherA4ModernContent()
    {
        return <<<'HTML'
<div class="doc-ds vch-modern">
    <div class="modern-header">
        <div class="brand-area">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="modern-logo" alt="">
            @endif
            <div>
                <h1>{{business.name}}</h1>
                <span class="brand-meta">{{business.tax_number}} — {{business.phone}}</span>
            </div>
        </div>
        <div class="modern-badge">
            {{document.title}}
            <span class="badge-sub">{{document.number}}</span>
        </div>
    </div>

    <div class="modern-meta-strip">
        <span>التاريخ: <strong>{{document.date}}</strong></span>
        <span>الدفع: <strong>{{document.payment_method}}</strong></span>
        <span>العملة: <strong>{{business.currency}}</strong></span>
    </div>

    <div class="amount-card">
        <span class="amount-label">المبلغ الإجمالي</span>
        <span class="amount-value">{{totals.grand_total}} <span class="amount-unit">{{business.currency}}</span></span>
    </div>

    <div class="info-cards">
        <div class="info-card">
            <h3>الطرف</h3>
            <p>{{customer.name}}</p>
            <p>{{customer.address}}</p>
            <p>{{customer.phone}}</p>
        </div>
        <div class="info-card">
            <h3>تفاصيل السند</h3>
            <p>الرقم: {{document.number}}</p>
            <p>التاريخ: {{document.date}}</p>
            <p>الدفع: {{document.payment_method}}</p>
        </div>
    </div>

    @if(!empty(document.notes))
    <div class="notes-block"><strong>البيان:</strong> {{document.notes}}</div>
    @endif

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="modern-sigs">
        <div class="sig-col"><div class="sig-title">المستلم</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">المسلّم</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">المعتمد</div><div class="sig-line"></div></div>
    </div>

    <div class="modern-foot">
        <span>{{business.name}}</span>
        <span>{{business.phone}}</span>
        <span>{{business.website}}</span>
    </div>
</div>
HTML;
    }

    private function getVoucherThermalContent()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt">
    <div class="t-header">
        @if(!empty(business.logo))
        <img src="{{business.logo}}" class="t-logo" alt="">
        @endif
        <h2 class="t-company">{{business.name}}</h2>
        <p class="t-sub">{{business.tax_number}}</p>
    </div>
    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <div class="t-meta"><span>الرقم</span><span>{{document.number}}</span></div>
    <div class="t-meta"><span>التاريخ</span><span>{{document.date}}</span></div>
    <div class="t-divider"></div>
    <div class="t-amount-box">{{totals.grand_total}}</div>
    <p class="t-sub">{{business.currency}}</p>
    <div class="t-divider"></div>
    <p class="t-party"><strong>الطرف:</strong> {{customer.name}}</p>
    <p class="t-party"><strong>الدفع:</strong> {{document.payment_method}}</p>
    @if(!empty(document.notes))
    <div class="t-divider"></div>
    <p class="t-party"><strong>البيان:</strong> {{document.notes}}</p>
    @endif
    <div class="t-divider"></div>
    <p class="t-footer">المستلم: .............. المسلّم: ..............</p>
    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    private function getVoucherThermalMiniContent()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt mini">
    <h3 class="t-company">{{business.name}}</h3>
    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <p class="t-sub">{{document.number}} — {{document.date}}</p>
    <div class="t-divider"></div>
    <div class="t-amount-box">{{totals.grand_total}}</div>
    <p class="t-sub">{{customer.name}}</p>
    <p class="t-sub">{{document.payment_method}}</p>
    <div class="t-divider"></div>
    <p class="t-footer">المستلم: ......... المسلّم: .........</p>
    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                محتوى القوالب — قيود اليومية                ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function getJournalA4Content()
    {
        return <<<'HTML'
<div class="doc-ds jrn-doc">
    <div class="header-line"></div>

    <div class="header-section">
        <div class="company-block">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="company-logo" alt="">
            @endif
            <h1 class="company-name">{{business.name}}</h1>
            <p class="meta-item">الرقم الضريبي: {{business.tax_number}}</p>
            <p class="meta-item">{{business.address}}</p>
        </div>

        <div class="doc-block">
            <div class="doc-title-badge">{{document.title}}</div>
            <table class="doc-meta-table">
                <tr><td class="label">رقم القيد</td><td>{{document.number}}</td></tr>
                <tr><td class="label">التاريخ</td><td>{{document.date}}</td></tr>
                <tr><td class="label">العملة</td><td>{{business.currency}}</td></tr>
            </table>
        </div>
    </div>

    @if(!empty(document.notes))
    <div class="party-card">
        <div class="party-title">وصف القيد / المرجع</div>
        <p class="desc-cell">{{document.notes}}</p>
    </div>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">اسم الحساب</th>
                <th>رقم الحساب</th>
                <th>مدين</th>
                <th>دائن</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name desc-cell">{{name}}</td>
                <td class="num-cell">{{code}}</td>
                <td class="num-cell">{{price}}</td>
                <td class="num-cell">{{tax}}</td>
            </tr>
            {{/items}}
        </tbody>
        <tfoot>
            <tr class="grand-total-row">
                <td colspan="3">إجمالي القيد</td>
                <td class="num-cell">{{totals.subtotal}}</td>
                <td class="num-cell">{{totals.grand_total}}</td>
            </tr>
        </tfoot>
    </table>

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="doc-foot">
        <div class="sigs">
            <div class="sig-block"><div class="sig-label">أعدّ بواسطة</div><div class="sig-line"></div></div>
            <div class="sig-block"><div class="sig-label">روجع بواسطة</div><div class="sig-line"></div></div>
            <div class="sig-block"><div class="sig-label">اعتُمد بواسطة</div><div class="sig-line"></div></div>
        </div>
        <div class="foot-note">{{business.name}} — قيدٌ محاسبيّ صادر من النظام</div>
    </div>
</div>
HTML;
    }

    private function getJournalA4ModernContent()
    {
        return <<<'HTML'
<div class="doc-ds jrn-modern">
    <div class="modern-header">
        <div class="brand-area">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="modern-logo" alt="">
            @endif
            <div>
                <h1>{{business.name}}</h1>
                <span class="brand-meta">{{business.tax_number}}</span>
            </div>
        </div>
        <div class="modern-badge">
            {{document.title}}
            <span class="badge-sub">{{document.number}}</span>
        </div>
    </div>

    <div class="modern-meta-strip">
        <span>التاريخ: <strong>{{document.date}}</strong></span>
        <span>العملة: <strong>{{business.currency}}</strong></span>
    </div>

    <div class="sum-cards">
        <div class="sum-card">
            <span class="sum-label">إجمالي المدين</span>
            <span class="sum-value">{{totals.subtotal}}</span>
        </div>
        <div class="sum-card sum-card--grand">
            <span class="sum-label">إجمالي الدائن</span>
            <span class="sum-value">{{totals.grand_total}}</span>
        </div>
    </div>

    @if(!empty(document.notes))
    <div class="notes-block"><strong>الوصف:</strong> {{document.notes}}</div>
    @endif

    <table class="modern-table mt-block">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">الحساب</th>
                <th>رقم الحساب</th>
                <th>مدين</th>
                <th>دائن</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name desc-cell">{{name}}</td>
                <td class="num-cell">{{code}}</td>
                <td class="num-cell">{{price}}</td>
                <td class="num-cell">{{tax}}</td>
            </tr>
            {{/items}}
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">المجموع</td>
                <td class="num-cell">{{totals.subtotal}}</td>
                <td class="num-cell">{{totals.grand_total}}</td>
            </tr>
        </tfoot>
    </table>

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="modern-sigs">
        <div class="sig-col"><div class="sig-title">الإعداد</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">المراجعة</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">الاعتماد</div><div class="sig-line"></div></div>
    </div>
</div>
HTML;
    }

    private function getJournalThermalContent()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt">
    <div class="t-header">
        <h2 class="t-company">{{business.name}}</h2>
        <p class="t-sub">{{business.tax_number}}</p>
    </div>
    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <div class="t-meta"><span>الرقم</span><span>{{document.number}}</span></div>
    <div class="t-meta"><span>التاريخ</span><span>{{document.date}}</span></div>
    @if(!empty(document.notes))
    <p class="t-party">{{document.notes}}</p>
    @endif
    <div class="t-divider"></div>
    <table class="t-items">
        <thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th></tr></thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="t-name">{{name}}</td>
                <td class="t-num">{{price}}</td>
                <td class="t-num">{{tax}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>
    <div class="t-divider"></div>
    <div class="t-total-row"><span>إجمالي المدين</span><span>{{totals.subtotal}}</span></div>
    <div class="t-total-row t-grand"><span>إجمالي الدائن</span><span>{{totals.grand_total}}</span></div>
    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                  محتوى القوالب — المخازن                   ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function getWarehouseA4Content()
    {
        return <<<'HTML'
<div class="doc-ds wh-doc">
    <div class="header-line"></div>

    <div class="header-section">
        <div class="company-block">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="company-logo" alt="">
            @endif
            <h1 class="company-name">{{business.name}}</h1>
            <p class="meta-item">{{business.address}}</p>
            <p class="meta-item">{{business.phone}}</p>
        </div>

        <div class="doc-block">
            <div class="doc-title-badge">{{document.title}}</div>
            <table class="doc-meta-table">
                <tr><td class="label">رقم الإذن</td><td>{{document.number}}</td></tr>
                <tr><td class="label">التاريخ</td><td>{{document.date}}</td></tr>
            </table>
        </div>
    </div>

    <div class="card-row">
        <div class="plain-card">
            <span class="label">أمين المستودع / المستلم</span>
            <span class="value">{{customer.name}}</span>
        </div>
        <div class="plain-card">
            <span class="label">موقع المستودع</span>
            <span class="value">{{customer.address}}</span>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">اسم الصنف</th>
                <th>كود الصنف</th>
                <th>الوحدة</th>
                <th>الكمية</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name desc-cell">{{name}}</td>
                <td class="num-cell">{{code}}</td>
                <td>{{unit}}</td>
                <td class="num-cell">{{quantity}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    @if(!empty(document.notes))
    <div class="notes-block mt-block"><strong>ملاحظات:</strong> {{document.notes}}</div>
    @endif

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="doc-foot">
        <div class="sigs">
            <div class="sig-block">
                <div class="sig-label">أمين المستودع</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع</div>
            </div>
            <div class="sig-block">
                <div class="sig-label">المستلم</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع</div>
            </div>
            <div class="sig-block">
                <div class="sig-label">المعتمد</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع</div>
            </div>
        </div>
        <div class="foot-note">{{business.name}} — لا تُصرف البضاعة إلا بموجب هذا الإذن</div>
    </div>
</div>
HTML;
    }

    private function getWarehouseA4ModernContent()
    {
        return <<<'HTML'
<div class="doc-ds wh-modern">
    <div class="modern-header">
        <div class="brand-area">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="modern-logo" alt="">
            @endif
            <div>
                <h1>{{business.name}}</h1>
                <span class="brand-meta">{{business.address}}</span>
            </div>
        </div>
        <div class="modern-badge">
            {{document.title}}
            <span class="badge-sub">{{document.number}}</span>
        </div>
    </div>

    <div class="modern-meta-strip">
        <span>التاريخ: <strong>{{document.date}}</strong></span>
        <span>رقم الإذن: <strong>{{document.number}}</strong></span>
    </div>

    <div class="info-cards">
        <div class="info-card"><h3>المسؤول</h3><p>{{customer.name}}</p><p>{{customer.phone}}</p></div>
        <div class="info-card"><h3>المستودع</h3><p>{{customer.address}}</p></div>
    </div>

    <table class="modern-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">الصنف</th>
                <th>الكود</th>
                <th>الوحدة</th>
                <th>الكمية</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name desc-cell">{{name}}</td>
                <td class="num-cell">{{code}}</td>
                <td>{{unit}}</td>
                <td class="num-cell">{{quantity}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    @if(!empty(document.notes))
    <div class="notes-block mt-block"><strong>ملاحظات:</strong> {{document.notes}}</div>
    @endif

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="modern-sigs">
        <div class="sig-col"><div class="sig-title">أمين المستودع</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">المستلم</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">المعتمد</div><div class="sig-line"></div></div>
    </div>
</div>
HTML;
    }

    private function getWarehouseThermalContent()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt">
    <div class="t-header">
        <h2 class="t-company">{{business.name}}</h2>
        <p class="t-sub">{{business.phone}}</p>
    </div>
    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <div class="t-meta"><span>الرقم</span><span>{{document.number}}</span></div>
    <div class="t-meta"><span>التاريخ</span><span>{{document.date}}</span></div>
    <p class="t-party"><strong>المسؤول:</strong> {{customer.name}}</p>
    <div class="t-divider"></div>
    <table class="t-items">
        <thead><tr><th>الصنف</th><th>الوحدة</th><th>الكمية</th></tr></thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="t-name">{{name}}</td>
                <td>{{unit}}</td>
                <td class="t-num">{{quantity}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>
    @if(!empty(document.notes))
    <div class="t-divider"></div>
    <p class="t-party">{{document.notes}}</p>
    @endif
    <div class="t-divider"></div>
    <p class="t-footer">أمين المستودع: ............ المستلم: ............</p>
    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                محتوى القوالب — عروض الأسعار                ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function getQuoteA4Content()
    {
        return <<<'HTML'
<div class="doc-ds qte-doc">
    <div class="header-line"></div>

    <div class="header-section">
        <div class="company-block">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="company-logo" alt="">
            @endif
            <h1 class="company-name">{{business.name}}</h1>
            <p class="meta-item">الرقم الضريبي: {{business.tax_number}}</p>
            <p class="meta-item">{{business.address}}</p>
            <p class="meta-item">{{business.phone}} — {{business.email}}</p>
        </div>

        <div class="doc-block">
            <div class="doc-title-badge">{{document.title}}</div>
            <table class="doc-meta-table">
                <tr><td class="label">رقم العرض</td><td>{{document.number}}</td></tr>
                <tr><td class="label">التاريخ</td><td>{{document.date}}</td></tr>
                @if(!empty(document.due_date))
                <tr><td class="label">صالح حتى</td><td>{{document.due_date}}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="notice">هذا عرض سعر تقديري وليس فاتورة — الأسعار قابلة للتغيير حسب التوفّر</div>

    <div class="party-card">
        <div class="party-title">بيانات العميل</div>
        <div class="kv-grid">
            <div class="kv"><span class="kv-label">الاسم</span><span class="kv-value">{{customer.name}}</span></div>
            <div class="kv"><span class="kv-label">الهاتف</span><span class="kv-value">{{customer.phone}}</span></div>
            <div class="kv"><span class="kv-label">الرقم الضريبي</span><span class="kv-value">{{customer.tax_number}}</span></div>
            <div class="kv"><span class="kv-label">العنوان</span><span class="kv-value">{{customer.address}}</span></div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">الصنف</th>
                <th>الكمية</th>
                <th>الوحدة</th>
                <th>السعر</th>
                <th>الخصم</th>
                <th>الضريبة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name">
                    <span class="item-name">{{name}}</span>
                    <span class="item-code">{{code}}</span>
                </td>
                <td class="num-cell">{{quantity}}</td>
                <td>{{unit}}</td>
                <td class="num-cell">{{price}}</td>
                <td class="num-cell">{{discount}}</td>
                <td class="num-cell">{{tax}}</td>
                <td class="num-cell">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="totals-band">
        <div class="totals-side">
            @if(!empty(document.notes))
            <div class="notes-block"><strong>الشروط والأحكام:</strong> {{document.notes}}</div>
            @endif
        </div>
        <div class="totals-box">
            <table class="tot-table">
                <tr><td>المجموع قبل الضريبة</td><td>{{totals.subtotal}}</td></tr>
                @if(totals.discount > 0)
                <tr class="discount-row"><td>الخصم</td><td>{{totals.discount}}</td></tr>
                @endif
                @if(totals.shipping > 0)
                <tr class="shipping-row"><td>الشحن</td><td>{{totals.shipping}}</td></tr>
                @endif
                @if(totals.tax > 0)
                <tr class="tax-row"><td>الضريبة</td><td>{{totals.tax}}</td></tr>
                @endif
                <tr class="grand-total"><td>الإجمالي المقدّر</td><td>{{totals.grand_total}}</td></tr>
            </table>
        </div>
    </div>

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="doc-foot">
        <div class="sigs">
            <div class="sig-block">
                <div class="sig-label">مقدّم العرض</div>
                <div class="sig-line"></div>
                <div class="sig-sub">{{business.name}}</div>
            </div>
            <div class="sig-block">
                <div class="sig-label">موافقة العميل</div>
                <div class="sig-line"></div>
                <div class="sig-sub">الاسم والتوقيع والتاريخ</div>
            </div>
        </div>
        <div class="foot-note">{{business.name}} — {{business.website}}</div>
    </div>
</div>
HTML;
    }

    private function getQuoteA4ModernContent()
    {
        return <<<'HTML'
<div class="doc-ds qte-modern">
    <div class="modern-header">
        <div class="brand-area">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="modern-logo" alt="">
            @endif
            <div>
                <h1>{{business.name}}</h1>
                <span class="brand-meta">{{business.phone}} — {{business.email}}</span>
            </div>
        </div>
        <div class="modern-badge">
            {{document.title}}
            <span class="badge-sub">{{document.number}}</span>
        </div>
    </div>

    <div class="notice">عرض سعر تقديري — صالح حتى {{document.due_date}}</div>

    <div class="info-cards">
        <div class="info-card">
            <h3>العميل</h3>
            <p>{{customer.name}}</p>
            <p>{{customer.phone}}</p>
            <p>{{customer.address}}</p>
        </div>
        <div class="info-card">
            <h3>تفاصيل العرض</h3>
            <p>الرقم: {{document.number}}</p>
            <p>التاريخ: {{document.date}}</p>
            <p>العملة: {{business.currency}}</p>
        </div>
    </div>

    <table class="modern-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">الصنف</th>
                <th>الكمية</th>
                <th>الوحدة</th>
                <th>السعر</th>
                <th>الخصم</th>
                <th>الضريبة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name">
                    <span class="item-name">{{name}}</span>
                    <span class="item-code">{{code}}</span>
                </td>
                <td class="num-cell">{{quantity}}</td>
                <td>{{unit}}</td>
                <td class="num-cell">{{price}}</td>
                <td class="num-cell">{{discount}}</td>
                <td class="num-cell">{{tax}}</td>
                <td class="num-cell">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="totals-band">
        <div class="totals-side">
            @if(!empty(document.notes))
            <div class="notes-block"><strong>الشروط:</strong> {{document.notes}}</div>
            @endif
        </div>
        <div class="totals-box">
            <table class="tot-table">
                <tr><td>المجموع</td><td>{{totals.subtotal}}</td></tr>
                @if(totals.discount > 0)
                <tr class="discount-row"><td>الخصم</td><td>{{totals.discount}}</td></tr>
                @endif
                @if(totals.shipping > 0)
                <tr class="shipping-row"><td>الشحن</td><td>{{totals.shipping}}</td></tr>
                @endif
                @if(totals.tax > 0)
                <tr class="tax-row"><td>الضريبة</td><td>{{totals.tax}}</td></tr>
                @endif
                <tr class="grand-total"><td>الإجمالي</td><td>{{totals.grand_total}}</td></tr>
            </table>
        </div>
    </div>

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="modern-sigs">
        <div class="sig-col"><div class="sig-title">مقدّم العرض</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">موافقة العميل</div><div class="sig-line"></div></div>
    </div>
</div>
HTML;
    }

    private function getQuoteThermalContent()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt">
    <div class="t-header">
        <h2 class="t-company">{{business.name}}</h2>
        <p class="t-sub">{{business.phone}}</p>
    </div>
    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <div class="t-meta"><span>الرقم</span><span>{{document.number}}</span></div>
    <div class="t-meta"><span>التاريخ</span><span>{{document.date}}</span></div>
    <p class="t-party"><strong>العميل:</strong> {{customer.name}}</p>
    <div class="t-divider"></div>
    <table class="t-items">
        <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>المجموع</th></tr></thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="t-name">{{name}}</td>
                <td class="t-num">{{quantity}}<span class="t-unit">{{unit}}</span></td>
                <td class="t-num">{{price}}</td>
                <td class="t-num">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>
    <div class="t-divider"></div>
    <div class="t-totals">
        <div class="t-total-row"><span>المجموع</span><span>{{totals.subtotal}}</span></div>
        @if(totals.discount > 0)
        <div class="t-total-row discount-row"><span>الخصم</span><span>{{totals.discount}}</span></div>
        @endif
        @if(totals.shipping > 0)
        <div class="t-total-row shipping-row"><span>الشحن</span><span>{{totals.shipping}}</span></div>
        @endif
        @if(totals.tax > 0)
        <div class="t-total-row tax-row"><span>الضريبة</span><span>{{totals.tax}}</span></div>
        @endif
        <div class="t-total-row t-grand"><span>الإجمالي</span><span>{{totals.grand_total}}</span></div>
    </div>
    <p class="t-footer">عرض سعر تقديري وليس فاتورة</p>
    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                   محتوى القوالب — العقود                   ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function getContractA4Content()
    {
        return <<<'HTML'
<div class="doc-ds ctr-doc">
    <div class="header-line"></div>

    <div class="ctr-head">
        @if(!empty(business.logo))
            <img src="{{business.logo}}" class="company-logo" alt="">
        @endif
        <h1 class="company-name">{{business.name}}</h1>
        <p class="meta-item">السجل التجاري: {{business.commercial_registration}}</p>
        <p class="meta-item">{{business.address}} — {{business.phone}}</p>
    </div>

    <div class="contract-title-box">
        <h2>{{document.title}}</h2>
        <p>رقم العقد: {{document.number}} — تاريخ التحرير: {{document.date}}</p>
    </div>

    <div class="card-row">
        <div class="plain-card">
            <div class="party-title">الطرف الأول (صاحب العمل)</div>
            <div class="kv-grid kv-grid--stacked">
                <div class="kv"><span class="kv-label">الاسم</span><span class="kv-value">{{business.name}}</span></div>
                <div class="kv"><span class="kv-label">السجل</span><span class="kv-value">{{business.commercial_registration}}</span></div>
                <div class="kv"><span class="kv-label">العنوان</span><span class="kv-value">{{business.address}}</span></div>
                <div class="kv"><span class="kv-label">الهاتف</span><span class="kv-value">{{business.phone}}</span></div>
            </div>
        </div>
        <div class="plain-card">
            <div class="party-title">الطرف الثاني (الموظف)</div>
            <div class="kv-grid kv-grid--stacked">
                <div class="kv"><span class="kv-label">الاسم</span><span class="kv-value">{{customer.name}}</span></div>
                <div class="kv"><span class="kv-label">الهاتف</span><span class="kv-value">{{customer.phone}}</span></div>
                <div class="kv"><span class="kv-label">العنوان</span><span class="kv-value">{{customer.address}}</span></div>
            </div>
        </div>
    </div>

    <div class="block">
        <div class="block-title">تفاصيل العقد</div>
        <table class="kv-table">
            <tr><td class="label-cell">تاريخ بداية العقد</td><td class="value-cell">{{document.date}}</td></tr>
            @if(!empty(document.due_date))
            <tr><td class="label-cell">تاريخ انتهاء العقد</td><td class="value-cell">{{document.due_date}}</td></tr>
            @endif
            <tr><td class="label-cell">طريقة صرف الراتب</td><td class="value-cell">{{document.payment_method}}</td></tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">بند الراتب</th>
                <th>النوع</th>
                <th>المبلغ</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name desc-cell">{{name}}</td>
                <td>{{unit}}</td>
                <td class="num-cell">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="totals-band">
        <div class="totals-side"></div>
        <div class="totals-box">
            <table class="tot-table">
                <tr><td>الراتب الأساسي</td><td>{{contract.basic_salary}}</td></tr>
                <tr><td>إجمالي المستحقات</td><td>{{contract.total_additions}}</td></tr>
                <tr><td>إجمالي الراتب</td><td>{{contract.gross_salary}}</td></tr>
                @if(!empty(contract.has_deductions))
                <tr><td>إجمالي المستقطعات</td><td>- {{contract.total_deductions}}</td></tr>
                @endif
                <tr class="grand-total"><td>صافي الراتب الشهري</td><td>{{contract.net_salary}} {{business.currency}}</td></tr>
            </table>
        </div>
    </div>

    @if(!empty(contract.terms))
    <div class="block">
        <div class="block-title">بنود وشروط العقد</div>
        <div class="terms-box">{{contract.terms}}</div>
    </div>
    @endif

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="doc-foot">
        <div class="sigs">
            <div class="sig-block">
                <div class="sig-label">الطرف الأول (صاحب العمل)</div>
                <div class="sig-line"></div>
                <div class="sig-sub">{{business.name}}</div>
                <div class="sig-sub">التوقيع والتاريخ</div>
            </div>
            <div class="sig-block">
                <div class="sig-label">الطرف الثاني (الموظف)</div>
                <div class="sig-line"></div>
                <div class="sig-sub">{{customer.name}}</div>
                <div class="sig-sub">التوقيع والتاريخ</div>
            </div>
        </div>
        <div class="foot-note">حُرّر هذا العقد من نسختين، بيد كلّ طرفٍ نسخةٌ للعمل بموجبها</div>
    </div>
</div>
HTML;
    }

    private function getContractA4ModernContent()
    {
        return <<<'HTML'
<div class="doc-ds ctr-modern">
    <div class="modern-header">
        <div class="brand-area">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="modern-logo" alt="">
            @endif
            <div>
                <h1>{{business.name}}</h1>
                <span class="brand-meta">س.ت: {{business.commercial_registration}}</span>
            </div>
        </div>
        <div class="modern-badge">
            {{document.title}}
            <span class="badge-sub">{{document.number}}</span>
        </div>
    </div>

    <div class="modern-meta-strip">
        <span>تاريخ التحرير: <strong>{{document.date}}</strong></span>
        @if(!empty(document.due_date))
        <span>ينتهي في: <strong>{{document.due_date}}</strong></span>
        @endif
    </div>

    <div class="info-cards">
        <div class="info-card">
            <h3>الطرف الأول — صاحب العمل</h3>
            <p>{{business.name}}</p>
            <p>س.ت: {{business.commercial_registration}}</p>
            <p>{{business.address}}</p>
        </div>
        <div class="info-card">
            <h3>الطرف الثاني — الموظف</h3>
            <p>{{customer.name}}</p>
            <p>{{customer.phone}}</p>
            <p>{{customer.address}}</p>
        </div>
    </div>

    <table class="kv-table">
        <tr><td class="label-cell">بداية العقد</td><td class="value-cell">{{document.date}}</td></tr>
        @if(!empty(document.due_date))
        <tr><td class="label-cell">نهاية العقد</td><td class="value-cell">{{document.due_date}}</td></tr>
        @endif
        <tr class="kv-highlight"><td class="label-cell">صافي الراتب الشهري</td><td class="value-cell">{{contract.net_salary}} {{business.currency}}</td></tr>
        <tr><td class="label-cell">طريقة الصرف</td><td class="value-cell">{{document.payment_method}}</td></tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">بند الراتب</th>
                <th>النوع</th>
                <th>المبلغ</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name desc-cell">{{name}}</td>
                <td>{{unit}}</td>
                <td class="num-cell">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="totals-band">
        <div class="totals-side"></div>
        <div class="totals-box">
            <table class="tot-table">
                <tr><td>الراتب الأساسي</td><td>{{contract.basic_salary}}</td></tr>
                <tr><td>إجمالي المستحقات</td><td>{{contract.total_additions}}</td></tr>
                <tr><td>إجمالي الراتب</td><td>{{contract.gross_salary}}</td></tr>
                @if(!empty(contract.has_deductions))
                <tr><td>إجمالي المستقطعات</td><td>- {{contract.total_deductions}}</td></tr>
                @endif
                <tr class="grand-total"><td>صافي الراتب الشهري</td><td>{{contract.net_salary}} {{business.currency}}</td></tr>
            </table>
        </div>
    </div>

    @if(!empty(contract.terms))
    <div class="block mt-block">
        <div class="block-title">بنود العقد</div>
        <div class="terms-box">{{contract.terms}}</div>
    </div>
    @endif

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="modern-sigs">
        <div class="sig-col"><div class="sig-title">صاحب العمل</div><div class="sig-line"></div><div class="sig-sub">{{business.name}}</div></div>
        <div class="sig-col"><div class="sig-title">الموظف</div><div class="sig-line"></div><div class="sig-sub">{{customer.name}}</div></div>
    </div>
</div>
HTML;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                  محتوى القوالب — الرواتب                   ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function getSalaryA4Content()
    {
        return <<<'HTML'
<div class="doc-ds sal-doc">
    <div class="header-line"></div>

    <div class="header-section">
        <div class="company-block">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="company-logo" alt="">
            @endif
            <h1 class="company-name">{{business.name}}</h1>
            <p class="meta-item">السجل التجاري: {{business.commercial_registration}}</p>
            <p class="meta-item">{{business.address}}</p>
        </div>

        <div class="doc-block">
            <div class="doc-title-badge">{{document.title}}</div>
            <table class="doc-meta-table">
                <tr><td class="label">الرقم</td><td>{{document.number}}</td></tr>
                <tr><td class="label">الشهر</td><td>{{document.date}}</td></tr>
                <tr><td class="label">العملة</td><td>{{business.currency}}</td></tr>
            </table>
        </div>
    </div>

    <div class="party-card">
        <div class="party-title">بيانات الموظف</div>
        <div class="kv-grid">
            <div class="kv"><span class="kv-label">اسم الموظف</span><span class="kv-value">{{customer.name}}</span></div>
            <div class="kv"><span class="kv-label">رقم الهاتف</span><span class="kv-value">{{customer.phone}}</span></div>
            <div class="kv"><span class="kv-label">القسم / الإدارة</span><span class="kv-value">{{customer.address}}</span></div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">البند</th>
                <th>المبلغ</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name desc-cell">{{name}}</td>
                <td class="num-cell">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="totals-band">
        <div class="totals-side">
            @if(!empty(document.notes))
            <div class="notes-block"><strong>ملاحظات:</strong> {{document.notes}}</div>
            @endif
        </div>
        <div class="totals-box">
            <table class="tot-table">
                <tr><td>إجمالي المستحقات</td><td>{{totals.subtotal}}</td></tr>
                @if(totals.discount > 0)
                <tr class="discount-row"><td>إجمالي الاستقطاعات</td><td>{{totals.discount}}</td></tr>
                @endif
                <tr class="grand-total"><td>صافي الراتب</td><td>{{totals.grand_total}}</td></tr>
            </table>
        </div>
    </div>

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="doc-foot">
        <div class="sigs">
            <div class="sig-block"><div class="sig-label">إدارة الموارد البشرية</div><div class="sig-line"></div></div>
            <div class="sig-block"><div class="sig-label">المدير المالي</div><div class="sig-line"></div></div>
            <div class="sig-block"><div class="sig-label">استلام الموظف</div><div class="sig-line"></div></div>
        </div>
        <div class="foot-note">{{business.name}} — كشفٌ صادر من النظام، وثيقةٌ سريّة تخصّ الموظف وحده</div>
    </div>
</div>
HTML;
    }

    private function getSalaryA4ModernContent()
    {
        return <<<'HTML'
<div class="doc-ds sal-modern">
    <div class="modern-header">
        <div class="brand-area">
            @if(!empty(business.logo))
                <img src="{{business.logo}}" class="modern-logo" alt="">
            @endif
            <div>
                <h1>{{business.name}}</h1>
                <span class="brand-meta">{{business.address}}</span>
            </div>
        </div>
        <div class="modern-badge">
            {{document.title}}
            <span class="badge-sub">{{document.number}}</span>
        </div>
    </div>

    <div class="modern-meta-strip">
        <span>الشهر: <strong>{{document.date}}</strong></span>
        <span>العملة: <strong>{{business.currency}}</strong></span>
    </div>

    <div class="info-cards">
        <div class="info-card">
            <h3>بيانات الموظف</h3>
            <p>{{customer.name}}</p>
            <p>{{customer.phone}}</p>
            <p>{{customer.address}}</p>
        </div>
        <div class="info-card">
            <h3>ملخّص الراتب</h3>
            <p>المستحقات: {{totals.subtotal}}</p>
            @if(totals.discount > 0)
            <p class="discount-row">الاستقطاعات: {{totals.discount}}</p>
            @endif
            <p>الصافي: {{totals.grand_total}}</p>
        </div>
    </div>

    <table class="modern-table">
        <thead>
            <tr>
                <th class="c-seq">#</th>
                <th class="c-name">البند</th>
                <th>المبلغ</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="c-seq">{{index}}</td>
                <td class="c-name desc-cell">{{name}}</td>
                <td class="num-cell">{{total}}</td>
            </tr>
            {{/items}}
        </tbody>
    </table>

    <div class="sum-cards mt-block">
        <div class="sum-card">
            <span class="sum-label">المستحقات</span>
            <span class="sum-value">{{totals.subtotal}}</span>
        </div>
        @if(totals.discount > 0)
        <div class="sum-card discount-row">
            <span class="sum-label">الاستقطاعات</span>
            <span class="sum-value">{{totals.discount}}</span>
        </div>
        @endif
        <div class="sum-card sum-card--grand">
            <span class="sum-label">صافي الراتب</span>
            <span class="sum-value">{{totals.grand_total}}</span>
        </div>
    </div>

    <div class="codes-band">
        @if(!empty(document.qr_code))
        <div class="qr-box">
            <img src="{{document.qr_code}}" class="qr-img" alt="">
        </div>
        @endif
        @if(!empty(document.barcode))
        <div class="barcode-box">
            <img src="{{document.barcode}}" class="barcode-img" alt="">
            <span class="barcode-value">{{document.number}}</span>
        </div>
        @endif
    </div>

    <div class="modern-sigs">
        <div class="sig-col"><div class="sig-title">الموارد البشرية</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">المدير المالي</div><div class="sig-line"></div></div>
        <div class="sig-col"><div class="sig-title">الموظف</div><div class="sig-line"></div></div>
    </div>
</div>
HTML;
    }

    private function getSalaryThermalContent()
    {
        return <<<'HTML'
<div class="doc-ds thermal-receipt">
    <div class="t-header">
        <h2 class="t-company">{{business.name}}</h2>
    </div>
    <div class="t-divider"></div>
    <h3 class="t-title">{{document.title}}</h3>
    <div class="t-meta"><span>الرقم</span><span>{{document.number}}</span></div>
    <div class="t-meta"><span>الشهر</span><span>{{document.date}}</span></div>
    <div class="t-divider"></div>
    <p class="t-party"><strong>الموظف:</strong> {{customer.name}}</p>
    <p class="t-party"><strong>القسم:</strong> {{customer.address}}</p>
    <div class="t-divider"></div>
    <table class="t-items">
        <thead><tr><th>البند</th><th>المبلغ</th></tr></thead>
        <tbody>
            {{#items}}
            <tr><td class="t-name">{{name}}</td><td class="t-num">{{total}}</td></tr>
            {{/items}}
        </tbody>
    </table>
    <div class="t-divider"></div>
    <div class="t-totals">
        <div class="t-total-row"><span>المستحقات</span><span>{{totals.subtotal}}</span></div>
        @if(totals.discount > 0)
        <div class="t-total-row discount-row"><span>الاستقطاعات</span><span>{{totals.discount}}</span></div>
        @endif
        <div class="t-total-row t-grand"><span>صافي الراتب</span><span>{{totals.grand_total}}</span></div>
    </div>
    @if(!empty(document.qr_code))
    <div class="t-qr"><img src="{{document.qr_code}}" alt=""></div>
    @endif
    @if(!empty(document.barcode))
    <div class="t-barcode">
        <img src="{{document.barcode}}" alt="">
        <span>{{document.number}}</span>
    </div>
    @endif
</div>
HTML;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                     أنماط نظام التصميم                       ║
    // ╚══════════════════════════════════════════════════════════════╝
    //
    // كل القوالب تشترك في `docCore()`: الترويسة والجداول وشريط
    // الإجماليات والتواقيع بأسماء أصنافٍ واحدة، فلا يختلف مستندان إلا
    // فيما يخصّ نوعهما. والتنويعة طبقةٌ رقيقة فوقه، فاسم القالب في
    // الشاشة يقابل فرقاً مرئياً لا لوناً مختلفاً وحده.
    //
    // ولا يُستعمل `$p` و`$s` في الأنماط: الألوان تأتي من
    // `--primary-color` و`--secondary-color` اللذين يحقنهما المصيّر من
    // إعدادات القالب — فمنتقي الألوان في المحرّر يسري على التصميم كلّه
    // بدل أن يبقى محبوساً في نصٍّ مزروع. والوسيطان باقيان في التوقيع
    // لأن قوائم القوالب تمرّرهما.

    /**
     * أنماط مستندٍ كامل: الأساس، فما يخصّ نوعه، فالتنويعة.
     */
    private function docStyles(string $variant = '', string $extra = ''): string
    {
        return $this->docCore().$extra.$this->docVariant($variant);
    }

    private function docCore(): string
    {
        return <<<'CSS'
/* ═════════════ نظام تصميم المستندات — الأساس المشترك ═════════════
   كل المقاسات بـ`em` منسوبةً إلى غلاف الورقة، فحجم الخط المختار في
   إعدادات القالب يكبّر المستند كلّه ويصغّره دون أن ينهار التدرّج.
   وكل الألوان من `--primary-color` و`--secondary-color`، فمنتقي
   الألوان في المحرّر يسري على التصميم بأسره. */
.doc-ds { color: #1f2937; line-height: 1.6; }
.doc-ds h1, .doc-ds h2, .doc-ds h3, .doc-ds h4 { margin: 0; font-weight: 700; }
.doc-ds p { margin: 0; }
.doc-ds table { width: 100%; border-collapse: collapse; }
.doc-ds img { max-width: 100%; }

/* ───── شريط علوي ───── */
.header-line { height: 5px; background: var(--primary-color); border-radius: 3px;
    margin-bottom: 16px; }

/* ───── ترويسة كلاسيكية: المنشأة يميناً وهوية المستند يساراً ───── */
.header-section, .stmt-header { display: flex; justify-content: space-between;
    align-items: flex-start; gap: 20px; margin-bottom: 16px; }
.company-block { flex: 1 1 55%; min-width: 0; }
.company-logo { max-height: 58px; max-width: 170px; object-fit: contain; margin-bottom: 8px; }
/* بديل الشعار: لا تستعمله القوالب المزروعة — اسم المنشأة تحته مباشرةً
   فكان يُطبع مرّتين — لكنه يبقى لقوالبَ كتبها المستخدم. */
.logo-fallback { display: inline-block; max-width: 180px; margin-bottom: 8px; padding: 6px 12px;
    border: 1px dashed #cbd5e1; border-radius: 6px; font-weight: 800; font-size: 1.05em;
    line-height: 1.4; }
.company-name { font-size: 1.32em; line-height: 1.35; margin: 0 0 6px; color: var(--primary-color); }
.meta-item { margin: 2px 0; color: var(--secondary-color); font-size: .85em; line-height: 1.65; }
.doc-block { flex: 0 0 245px; }
.doc-title-badge { display: block; background: var(--primary-color); color: #fff;
    padding: 8px 14px; border-radius: 6px; font-weight: 700; margin-bottom: 10px;
    text-align: center; font-size: 1.02em; line-height: 1.4; }
.doc-meta-table { font-size: .85em; }
.doc-meta-table td { padding: 5px 6px; border-bottom: 1px solid #eef0f3; }
.doc-meta-table td:last-child { text-align: end; font-weight: 600; white-space: nowrap; }
.doc-meta-table .label { color: var(--secondary-color); white-space: nowrap; font-weight: 400;
    text-align: start; }

/* ───── بطاقة الطرف ───── */
.party-card { border: 1px solid #e5e7eb; border-radius: 7px; padding: 11px 14px;
    margin-bottom: 16px; background: #f9fafb; }
.party-title, .section-title { font-weight: 700; color: var(--primary-color);
    margin-bottom: 8px; font-size: .96em; }
/* شبكة «تسمية: قيمة» — لا جدول: إطفاء «إظهار بيانات العميل» يحذف
   العنصر الحاوي للرمز، وفي الجدول يعني ذلك حذف عمودٍ كامل فتبقى
   التسميات بلا قيم. وفي الشبكة يختفي الحقل وحده كما يُتوقَّع. */
.kv-grid { display: flex; flex-wrap: wrap; gap: 3px 18px; }
.kv { flex: 1 1 44%; min-width: 0; display: flex; gap: 8px; font-size: .87em; line-height: 1.75; }
.kv--wide { flex-basis: 100%; }
.kv-grid--stacked .kv { flex-basis: 100%; }
.kv-label { color: var(--secondary-color); flex: 0 0 92px; }
.kv-value { font-weight: 600; overflow-wrap: break-word; min-width: 0; }

/* بطاقتان أو أكثر في صفّ واحد */
.card-row { display: flex; gap: 12px; margin-bottom: 16px; }
.card-row > * { flex: 1 1 0; min-width: 0; }
.plain-card { border: 1px solid #e5e7eb; border-radius: 7px; padding: 11px 14px;
    background: #f9fafb; }
.plain-card .label { display: block; color: var(--secondary-color); font-size: .82em;
    margin-bottom: 4px; }
.plain-card .value { display: block; font-weight: 700; font-size: 1.02em; line-height: 1.5;
    overflow-wrap: break-word; }

/* ───── جدول البيانات ─────
   بلا `table-layout: fixed`: إخفاء «الضريبة» أو «الخصم» من الإعدادات
   يحذف العمود من الصفوف كلّها، والعروض الثابتة لا تُعاد قسمتها بعده.
   فعمود الاسم يأخذ ما تبقّى بـ`width:99%` وأعمدةُ الأرقام لا تلتفّ. */
.data-table { font-size: .84em; }
.data-table thead th { background: var(--primary-color); color: #fff; padding: 8px 6px;
    text-align: center; font-weight: 700; border: 1px solid var(--primary-color);
    white-space: nowrap; }
.data-table tbody td { padding: 7px 6px; text-align: center; border: 1px solid #e5e7eb;
    vertical-align: middle; }
.data-table tbody tr:nth-child(even) td { background: #f9fafb; }
.data-table tfoot td { padding: 8px 6px; text-align: center; border: 1px solid #e5e7eb;
    font-weight: 700; background: #f3f4f6; white-space: nowrap; }
.data-table .c-seq { width: 34px; color: var(--secondary-color); }
.data-table .c-name { width: 99%; text-align: start; }
.item-name { display: block; font-weight: 600; line-height: 1.55; }
.item-code { display: block; font-size: .86em; color: var(--secondary-color);
    direction: ltr; text-align: start; margin-top: 1px; }
.num-cell { white-space: nowrap; font-variant-numeric: tabular-nums;
    font-feature-settings: 'tnum'; direction: ltr; }
.desc-cell { text-align: start; word-break: normal; overflow-wrap: break-word; line-height: 1.7; }
.totals-row td:first-child, .grand-total-row td:first-child { text-align: start; }

/* `!important` هنا وحده: المصيّر يفرض لون النصّ الأساسي على
   `.grand-total-row td` لقوالبَ قديمة، فيصير أبيضُ الصفّ لوناً على لونه. */
.doc-ds .grand-total-row td { background: var(--primary-color); color: #fff !important;
    border-color: var(--primary-color); font-size: 1.04em; }

/* ───── شريط الإجماليات: البيان يميناً والمبالغ يساراً ───── */
.totals-band { display: flex; gap: 18px; align-items: flex-start; margin-top: 16px; }
.totals-side { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 10px; }
.totals-box { flex: 0 0 290px; border: 1px solid #e5e7eb; border-radius: 7px; overflow: hidden; }
.tot-table { font-size: .88em; }
.tot-table td { padding: 7px 12px; border-bottom: 1px solid #eef0f3; }
.tot-table tr:last-child td { border-bottom: none; }
.tot-table td:first-child { color: var(--secondary-color); }
.tot-table td:last-child { text-align: end; white-space: nowrap; font-weight: 700;
    font-variant-numeric: tabular-nums; direction: ltr; }
.tot-table .grand-total td { background: var(--primary-color); color: #fff;
    font-size: 1.08em; font-weight: 800; }
.tot-table .paid-row td:last-child { color: #047857; }
.tot-table .remaining-row td:last-child { color: #b91c1c; }

/* ───── ملاحظات ورمز الاستجابة ───── */
.notes-block { border: 1px solid #fde68a; background: #fffbeb; border-radius: 7px;
    padding: 9px 12px; font-size: .85em; line-height: 1.75; color: #78350f; }
.notes-block strong { color: #92400e; }
/* عمودٌ لا صفّ: النصّ عنوانٌ للرمز فوقه، لا جارٌ له يزاحمه على العرض */
.qr-box { display: flex; flex-direction: column; align-items: center; gap: 6px;
    text-align: center; }
.qr-box .qr-img { width: 92px; height: 92px; flex: 0 0 auto; }
.qr-hint { font-size: .8em; color: var(--secondary-color); line-height: 1.6;
    max-width: 200px; }
/* الباركود: `print-color-adjust` لازم — المتصفّحات تسقط الألوان
   في الطباعة افتراضياً، وباركودٌ بقضبانٍ باهتة لا يُقرأ بالماسح. */
/* الرمزان في صفٍّ واحد: كتلتان مكدّستان تدفعان المستند إلى ورقةٍ ثانية */
.codes-band { display: flex; align-items: center; justify-content: center;
    flex-wrap: wrap; gap: 26px; margin: 16px 0 4px; }
.codes-band .qr-box, .codes-band .barcode-box { margin: 0; }
.codes-band .barcode-img { max-width: 100%; }
.barcode-box { text-align: center; margin: 16px 0 4px; }
.barcode-img { display: block; margin: 0 auto; height: 46px; max-width: 78%;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.barcode-value { display: block; margin-top: 4px; font-size: .78em; letter-spacing: .14em;
    color: var(--secondary-color); direction: ltr; font-variant-numeric: tabular-nums; }
.qr-box .qr-img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.qr-box--center { margin: 14px auto 0; }
.mt-block { margin-top: 14px; }

/* ───── تذييل وتواقيع ───── */
.doc-foot { margin-top: 22px; }
.sigs, .modern-sigs { display: flex; gap: 22px; margin-top: 26px; }
.sig-block, .sig-col { flex: 1 1 0; text-align: center; }
.sig-label, .sig-title { font-size: .85em; color: var(--secondary-color); font-weight: 700;
    margin-bottom: 34px; }
.sig-title { color: var(--primary-color); }
.sig-line { border-top: 1px solid #9ca3af; }
.sig-sub { font-size: .78em; color: var(--secondary-color); margin-top: 5px; }
.foot-note { margin-top: 16px; padding-top: 9px; border-top: 1px dashed #d1d5db;
    text-align: center; font-size: .8em; color: var(--secondary-color); line-height: 1.7; }

/* ───── كتلة عنوان داخلية ───── */
.block { margin-bottom: 16px; }
.block-title { color: var(--primary-color); font-size: 1.02em; margin-bottom: 9px;
    padding-bottom: 5px; border-bottom: 1px solid #e5e7eb; }
.notice { text-align: center; padding: 9px 14px; margin-bottom: 14px; background: #fef3c7;
    border: 1px solid #fbbf24; border-radius: 7px; font-size: .86em; font-weight: 700;
    color: #92400e; line-height: 1.6; }

/* ───── جدول تسمية/قيمة ───── */
.kv-table { font-size: .88em; border: 1px solid #e5e7eb; border-radius: 7px; }
.kv-table td { padding: 9px 12px; border-bottom: 1px solid #eef0f3; }
.kv-table tr:last-child td { border-bottom: none; }
.kv-table .label-cell { background: #f9fafb; color: var(--secondary-color); width: 34%;
    white-space: nowrap; }
.kv-table .value-cell { font-weight: 600; overflow-wrap: break-word; }

/* ───── صندوق المبلغ (السندات) ───── */
.amount-box { margin: 18px 0; padding: 20px; border: 2px solid var(--primary-color);
    border-radius: 12px; background: #f8fafc; text-align: center; }
.amount-label { display: block; font-size: .88em; color: var(--secondary-color);
    margin-bottom: 6px; }
.amount-value { display: block; font-size: 2.1em; font-weight: 800; color: var(--primary-color);
    direction: ltr; font-variant-numeric: tabular-nums; line-height: 1.25; }
.amount-unit { font-size: .42em; font-weight: 600; }
.amount-card { margin: 16px 0; padding: 22px; border-radius: 12px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: #fff; text-align: center; }
.amount-card .amount-label { color: rgba(255,255,255,.9); }
.amount-card .amount-value { color: #fff; }

/* ═════════════ الطراز العصري ═════════════ */
.modern-header { display: flex; justify-content: space-between; align-items: center;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: #fff; padding: 15px 18px; border-radius: 10px; margin-bottom: 14px; gap: 16px; }
.brand-area { display: flex; align-items: center; gap: 12px; min-width: 0; }
.modern-logo { max-height: 50px; max-width: 140px; object-fit: contain; background: #fff;
    border-radius: 8px; padding: 4px; }
.modern-header h1 { font-size: 1.2em; line-height: 1.35; }
.brand-meta { display: block; font-size: .8em; opacity: .92; margin-top: 3px; line-height: 1.55; }
.modern-badge { background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.45);
    padding: 8px 15px; border-radius: 20px; font-weight: 700; white-space: nowrap;
    text-align: center; line-height: 1.4; }
.badge-sub { display: block; font-size: .82em; font-weight: 400; opacity: .92;
    direction: ltr; margin-top: 2px; }
.modern-meta-strip { display: flex; flex-wrap: wrap; gap: 18px; padding: 9px 14px;
    background: #f3f4f6; border-radius: 8px; margin-bottom: 14px; font-size: .86em; }
.info-cards { display: flex; gap: 10px; margin-bottom: 14px; }
.info-card { flex: 1 1 0; min-width: 0; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 10px 12px; background: #fff; border-top: 3px solid var(--primary-color); }
.info-card h3 { font-size: .88em; color: var(--primary-color); margin-bottom: 6px; }
.info-card p { font-size: .85em; line-height: 1.7; overflow-wrap: break-word; }
.sum-cards { display: flex; gap: 10px; margin-bottom: 16px; }
.sum-card { flex: 1 1 0; min-width: 0; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 10px 8px; text-align: center; background: #fff; }
.sum-label { display: block; font-size: .78em; color: var(--secondary-color); margin-bottom: 5px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sum-value { display: block; font-size: 1.04em; font-weight: 700; color: var(--primary-color);
    white-space: nowrap; font-variant-numeric: tabular-nums; direction: ltr; }
.sum-card--grand { background: var(--primary-color); border-color: var(--primary-color); }
.sum-card--grand .sum-label, .sum-card--grand .sum-value { color: #fff; }
.modern-table { font-size: .84em; }
.modern-table thead th { background: #f3f4f6; color: #374151; padding: 9px 6px;
    text-align: center; border-bottom: 2px solid var(--primary-color); white-space: nowrap; }
.modern-table tbody td { padding: 8px 6px; text-align: center; border-bottom: 1px solid #eef0f3;
    vertical-align: middle; }
.modern-table tbody tr:last-child td { border-bottom: 1px solid #e5e7eb; }
.modern-table tfoot td { padding: 9px 6px; text-align: center; font-weight: 700;
    background: var(--primary-color); color: #fff; white-space: nowrap; }
.modern-table tfoot td:first-child { text-align: start; }
.modern-table .c-seq { width: 34px; color: var(--secondary-color); }
.modern-table .c-name { width: 99%; text-align: start; }
.modern-foot { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    margin-top: 16px; font-size: .83em; color: var(--secondary-color);
    border-top: 1px dashed #d1d5db; padding-top: 9px; }

@media print {
    .data-table, .modern-table, .stmt-table, .modern-stmt-table { page-break-inside: auto; }
    .data-table tr, .modern-table tr, .stmt-table tr, .modern-stmt-table tr
        { page-break-inside: avoid; }
    .data-table thead, .modern-table thead, .stmt-table thead, .modern-stmt-table thead
        { display: table-header-group; }
    .totals-band, .sigs, .modern-sigs, .amount-box, .amount-card { page-break-inside: avoid; }
    .barcode-box, .qr-box, .codes-band { page-break-inside: avoid; }
}
CSS;
    }

    /**
     * فروق التنويعة عن الأساس — التنويعة المجهولة تُهمَل بلا خطأ.
     */
    private function docVariant(string $variant): string
    {
        switch ($variant) {
            case 'frame':
                return <<<'CSS'
/* ───── تنويعة: إطارٌ حول الورقة ───── */
.doc-ds { padding: 15px; border: 2px solid var(--primary-color); border-radius: 10px; }
.header-line { display: none; }
.header-section, .stmt-header, .modern-header { padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-color); }
.modern-header { border-radius: 8px; border-bottom: none; }
CSS;
            case 'dark':
                return <<<'CSS'
/* ───── تنويعة: ترويسة داكنة ───── */
.header-line { display: none; }
.header-section, .stmt-header { background: #111827; color: #fff; padding: 16px 18px;
    border-radius: 10px; }
.header-section .company-name, .stmt-header .company-name { color: #fff; }
.header-section .meta-item, .stmt-header .meta-item { color: #cbd5e1; }
.header-section .logo-fallback, .stmt-header .logo-fallback { border-color: #4b5563; color: #fff; }
.header-section .doc-meta-table td, .stmt-header .doc-meta-table td
    { border-bottom-color: #374151; }
.header-section .doc-meta-table .label, .stmt-header .doc-meta-table .label { color: #9ca3af; }
.data-table thead th { background: #111827; border-color: #111827; }
.modern-table thead th { background: #111827; color: #fff; border-bottom-color: #111827; }
CSS;
            case 'minimal':
                return <<<'CSS'
/* ───── تنويعة: بسيطة بلا حدود ───── */
.header-line { height: 2px; }
.party-card, .plain-card, .kv-table, .totals-box, .info-card { background: transparent;
    border-color: #edf0f4; }
.info-card { border-top-width: 2px; }
.data-table thead th { background: transparent; color: var(--primary-color);
    border: none; border-bottom: 2px solid var(--primary-color); }
.data-table tbody td { border: none; border-bottom: 1px solid #f1f3f6; }
.data-table tbody tr:nth-child(even) td { background: transparent; }
.data-table tfoot td { border: none; border-top: 1px solid #e5e7eb; background: transparent; }
.doc-ds .grand-total-row td { background: transparent; color: var(--primary-color) !important;
    border-top: 2px solid var(--primary-color); }
.tot-table .grand-total td { background: transparent; color: var(--primary-color);
    border-top: 2px solid var(--primary-color); }
.amount-box { background: transparent; }
CSS;
            case 'gradient':
                return <<<'CSS'
/* ───── تنويعة: رؤوسٌ متدرّجة ───── */
.header-line { height: 7px; }
.data-table thead th { background: linear-gradient(135deg, var(--primary-color),
    var(--secondary-color)); border-color: var(--secondary-color); }
.modern-table thead th { background: linear-gradient(135deg, var(--primary-color),
    var(--secondary-color)); color: #fff; border-bottom-color: var(--secondary-color); }
.tot-table .grand-total td { background: linear-gradient(135deg, var(--primary-color),
    var(--secondary-color)); }
.doc-ds .grand-total-row td { background: linear-gradient(135deg, var(--primary-color),
    var(--secondary-color)); }
.party-card { background: linear-gradient(180deg, #f9fafb, #fff); }
CSS;
            case 'compact':
                return <<<'CSS'
/* ───── تنويعة: مضغوطة، أكبر عدد صفوف في الورقة ───── */
.header-section, .stmt-header { margin-bottom: 10px; }
.party-card { padding: 7px 10px; margin-bottom: 10px; }
.data-table, .modern-table, .stmt-table { font-size: .76em; }
.data-table tbody td, .modern-table tbody td { padding: 4px 5px; }
.data-table thead th, .modern-table thead th { padding: 6px 5px; }
.totals-band { margin-top: 10px; gap: 12px; }
.sigs, .modern-sigs { margin-top: 16px; }
.sig-label, .sig-title { margin-bottom: 24px; }
CSS;
            default:
                return '';
        }
    }

    private function statementStyles(): string
    {
        return <<<'CSS'
/* ═════════ كشف الحساب ═════════
   `fixed` يفرض العروض المعلنة: بدونه يوزّع المتصفّح العرض على المحتوى
   فيُخنَق «البيان» ويتكسّر حرفاً حرفاً ويمطّ الصفَّ عشرة أسطر. */
.stmt-table { font-size: .82em; table-layout: fixed; }
.stmt-table col.c-date { width: 12%; }
.stmt-table col.c-ref { width: 14%; }
.stmt-table col.c-desc { width: 39%; }
.stmt-table col.c-num { width: 11%; }
.stmt-table col.c-bal { width: 13%; }
.stmt-table thead th { background: var(--primary-color); color: #fff; padding: 8px 6px;
    text-align: center; font-weight: 700; border: 1px solid var(--primary-color); }
.stmt-table tbody td { padding: 7px 6px; text-align: center; border: 1px solid #e5e7eb;
    vertical-align: middle; }
.stmt-table tbody tr:nth-child(even) td { background: #f9fafb; }
.stmt-table .opening-row td { background: #eef2f7; font-weight: 700; }
.stmt-table .opening-row td:first-child { text-align: start; }
.stmt-table tfoot td { padding: 8px 6px; text-align: center; border: 1px solid #e5e7eb;
    font-weight: 700; background: #f3f4f6; white-space: nowrap; }

.modern-stmt-table { font-size: .8em; table-layout: fixed; }
.modern-stmt-table col.c-date { width: 12%; }
.modern-stmt-table col.c-ref { width: 14%; }
.modern-stmt-table col.c-desc { width: 39%; }
.modern-stmt-table col.c-num { width: 11%; }
.modern-stmt-table col.c-bal { width: 13%; }
.modern-stmt-table thead th { background: #f3f4f6; color: #374151; padding: 8px 6px;
    text-align: center; border-bottom: 2px solid var(--primary-color); }
.modern-stmt-table tbody td { padding: 7px 6px; text-align: center;
    border-bottom: 1px solid #eef0f3; vertical-align: middle; }
.modern-stmt-table .opening-row td { background: #f9fafb; font-weight: 700; }
.modern-stmt-table .opening-row td:first-child { text-align: start; }
.modern-stmt-table tfoot .total-row td { padding: 9px 6px; text-align: center;
    font-weight: 700; background: var(--primary-color); color: #fff; white-space: nowrap; }
.modern-stmt-table tfoot .total-row td:first-child { text-align: start; }

.stmt-compact .compact-head { display: flex; justify-content: space-between; flex-wrap: wrap;
    gap: 8px; border-bottom: 2px solid var(--primary-color); padding-bottom: 7px;
    margin-bottom: 10px; font-size: .88em; }
.stmt-compact .stmt-table { font-size: .76em; }
.stmt-compact .stmt-table tbody td { padding: 4px 5px; }
.stmt-compact .stmt-table thead th { padding: 6px 5px; }
CSS;
    }

    private function contractStyles(): string
    {
        return <<<'CSS'
/* ═════════ العقود ═════════ */
.ctr-doc, .ctr-modern { line-height: 1.8; }
.ctr-head { text-align: center; padding-bottom: 14px; border-bottom: 1px solid #e5e7eb; }
.ctr-head .company-name { font-size: 1.45em; }
.contract-title-box { text-align: center; margin: 20px 0; padding: 16px;
    border: 2px solid var(--primary-color); border-radius: 10px; background: #f8fafc; }
.contract-title-box h2 { color: var(--primary-color); font-size: 1.35em; margin-bottom: 5px; }
.contract-title-box p { font-size: .86em; color: var(--secondary-color); }
.terms-box { padding: 14px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;
    font-size: .88em; line-height: 1.95; white-space: pre-line; }
.kv-table .kv-highlight .label-cell { background: var(--primary-color); color: #fff; }
.kv-table .kv-highlight .value-cell { background: var(--primary-color); color: #fff;
    font-size: 1.05em; }
CSS;
    }

    private function getInvoiceA4Styles($p, $s, $variant = '')
    {
        return $this->docStyles($variant);
    }

    private function getInvoiceA4ModernStyles($p, $s, $variant = '')
    {
        return $this->docStyles($variant);
    }

    private function getVoucherA4Styles($p, $s, $variant = '')
    {
        return $this->docStyles($variant);
    }

    private function getVoucherA4ModernStyles($p, $s, $variant = '')
    {
        return $this->docStyles($variant);
    }

    private function getJournalA4Styles($p, $s, $variant = '')
    {
        return $this->docStyles($variant);
    }

    private function getWarehouseA4Styles($p, $s, $variant = '')
    {
        return $this->docStyles($variant);
    }

    private function getQuoteA4Styles($p, $s, $variant = '')
    {
        return $this->docStyles($variant);
    }

    private function getContractA4Styles($p, $s, $variant = '')
    {
        return $this->docStyles($variant, $this->contractStyles());
    }

    private function getSalaryA4Styles($p, $s, $variant = '')
    {
        return $this->docStyles($variant);
    }

    private function getStatementA4Styles($p, $s, $variant = '')
    {
        return $this->docStyles($variant, $this->statementStyles());
    }

    private function getStatementA4ModernStyles($p, $s, $variant = '')
    {
        return $this->docStyles($variant, $this->statementStyles());
    }

    private function getThermalStyles()
    {
        return <<<'CSS'
/* ═════════ إيصال الطابعة الحرارية ═════════
   كل المقاسات بـ`em` كذلك، فحجم الخط المختار يسري على الإيصال كلّه. */
.thermal-receipt { text-align: center; color: #000; }
.thermal-receipt h2, .thermal-receipt h3 { margin: 0; }
.thermal-receipt p { margin: 0; }
.t-header { padding: 4px 0; }
.t-logo { max-height: 44px; margin-bottom: 4px; }
.t-company { font-size: 1.28em; font-weight: 800; margin: 3px 0; line-height: 1.4; }
.t-sub { font-size: .86em; line-height: 1.6; margin: 1px 0; }
.t-title { font-size: 1.12em; font-weight: 800; margin: 6px 0 3px; }
.t-divider { border-bottom: 1px dashed #000; margin: 5px 0; }
.t-divider-double { border-bottom: 3px double #000; margin: 5px 0; }
.t-meta { display: flex; justify-content: space-between; gap: 6px; font-size: .88em;
    padding: 1px 0; text-align: start; }
.t-meta span:last-child { direction: ltr; }
.t-party { font-size: .9em; text-align: start; padding: 1px 0; line-height: 1.6; }
.t-items { width: 100%; border-collapse: collapse; font-size: .88em; }
.t-items th { border-bottom: 1px solid #000; padding: 3px 2px; font-weight: 800;
    text-align: center; }
.t-items th:first-child { text-align: start; }
.t-items td { padding: 3px 2px; border-bottom: 1px dotted #bbb; vertical-align: top;
    text-align: center; }
.t-items .t-name { text-align: start; line-height: 1.5; }
.t-items .t-num { white-space: nowrap; direction: ltr; font-variant-numeric: tabular-nums; }
/* الوحدة تحت الكمية لا في عمودٍ خاصّ: عرضُ الورقة ٨٠ مم لا يحتمل عموداً
   خامساً. وسطرٌ بلا وحدةٍ محفوظة يُخرج الوسمَ فارغاً فلا يظهر منه شيء. */
.t-items .t-unit { display: block; direction: rtl; font-size: .82em; font-weight: 600;
    opacity: .75; line-height: 1.4; }
.mini-item .t-unit { display: inline; font-size: .85em; opacity: .75; margin-inline-start: 2px; }
.t-totals { margin: 4px 0; }
.t-total-row { display: flex; justify-content: space-between; gap: 8px; padding: 2px 0;
    font-size: .95em; text-align: start; }
.t-total-row span:last-child { direction: ltr; font-variant-numeric: tabular-nums;
    white-space: nowrap; }
.t-total-row.t-grand { font-weight: 900; font-size: 1.16em; border-top: 1px solid #000;
    margin-top: 3px; padding-top: 4px; }
.t-amount-box { font-size: 1.55em; font-weight: 900; padding: 9px; border: 2px solid #000;
    border-radius: 8px; margin: 8px 0; direction: ltr; font-variant-numeric: tabular-nums; }
.t-qr { text-align: center; margin: 6px 0; }
.t-qr img { width: 78px; height: 78px; -webkit-print-color-adjust: exact;
    print-color-adjust: exact; }
.t-barcode { text-align: center; margin: 6px 0; }
.t-barcode img { display: block; margin: 0 auto; height: 38px; max-width: 96%;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.t-barcode span { display: block; margin-top: 2px; font-size: .8em; letter-spacing: .12em;
    direction: ltr; }
.t-footer { font-size: .85em; margin-top: 6px; line-height: 1.7; }
.mini-item { display: flex; justify-content: space-between; gap: 6px; padding: 2px 0;
    font-size: .92em; border-bottom: 1px dotted #bbb; text-align: start; }
.mini-item span:last-child { direction: ltr; white-space: nowrap; }
.thermal-receipt.mini .t-company { font-size: 1.12em; }
.thermal-receipt.mini .t-title { font-size: 1em; }
.thermal-receipt.mini .t-amount-box { font-size: 1.3em; padding: 6px; }
CSS;
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  المطبوعات الأصلية — قوالبُ بلايد محوَّلةً إلى قوالب مخزَّنة    ║
    // ╚══════════════════════════════════════════════════════════════╝
    //
    // أربعُ مطبوعاتٍ كانت مرسومةً في ملفات بلايد ثابتة، لا يبلغها محرِّرُ
    // «قوالب المستندات» ولا تتأثّر بألوانه ولا بمفاتيح إظهاره:
    //
    //   • `invoices/print.blade.php`      ← فاتورة ضريبية حرارية ٨٠ مم
    //   • `invoices/pdf.blade.php`        ← فاتورة حرارية ٨٠ مم
    //   • `retend_invoice/pdf.blade.php`  ← مرتجع مبيعات حراري ٨٠ مم
    //   • `creted_note/pdf.blade.php`     ← إشعار دائن حراري ٨٠ مم
    //
    // نُقلت هنا كما هي — **قالبٌ لكلّ ملف، لا قالبٌ واحدٌ مدموج**: لكلٍّ
    // صفُّه في `document_templates` بنصِّه وأنماطه، فيظهر في المحرّر ويمكن
    // اختياره افتراضياً لنوعه وحده. وهذا خلافُ سائر المزروعات: تلك تُبنى
    // بالمجموعة (`getInvoicePresets`) فيتقاسمها ثمانيةُ أنواع، وهذه بالنوع
    // (`extraPresetsFor`) فلا تظهر فاتورةُ المشتريات بتصميم مرتجع.
    //
    // ومقاساتُها بالبكسل كما كُتبت أصلاً، فتحمل `doc-ds` على غلافها كي لا
    // يدهسها المصيّرُ بـ`font-size: var(--font-size) !important` — انظر
    // `DocumentTemplateRenderer::render()`. والثمنُ أنّ «حجم الخطّ» في
    // المحرّر لا يُكبّرها؛ وهو ثمنُ «التصميم نفسه بالضبط».

    /**
     * قوالبُ نوعٍ بعينه — تُضاف إلى قوالب مجموعته ولا تحلّ محلَّها.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extraPresetsFor(string $documentType): array
    {
        // الباركود مطفأ: المطبوعة الأصلية لا تحمله، وإظهاره يزيد سطراً
        // لم يكن فيها
        $legacy = ['show_barcode' => false];

        return match ($documentType) {
            'sales_invoice' => [
                ['name' => 'فاتورة مبيعات - حراري 80 مم (المطبوعة الأصلية)', 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                    'content' => $this->getLegacyTaxReceiptContent(), 'styles' => $this->legacyReceiptStyles(),
                    'settings' => $this->makeSettings('#000000', '#555555', 13, $legacy)],
                ['name' => 'فاتورة مبيعات - حراري 80 مم مبسّط (المطبوعة الأصلية)', 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                    'content' => $this->getLegacyPlainReceiptContent(), 'styles' => $this->legacyReceiptStyles(),
                    'settings' => $this->makeSettings('#000000', '#555555', 13, $legacy)],
            ],
            'sales_return' => [
                ['name' => 'مرتجع مبيعات - حراري 80 مم (المطبوعة الأصلية)', 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                    'content' => $this->getLegacyReturnReceiptContent(), 'styles' => $this->legacyReceiptStyles($this->legacyReturnExtras()),
                    'settings' => $this->makeSettings('#000000', '#555555', 13, $legacy)],
            ],
            'credit_note' => [
                ['name' => 'إشعار دائن - حراري 80 مم (المطبوعة الأصلية)', 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                    'content' => $this->getLegacyCreditNoteContent(), 'styles' => $this->legacyReceiptStyles($this->legacyCreditNoteExtras()),
                    'settings' => $this->makeSettings('#000000', '#555555', 13, $legacy)],
            ],
            default => [],
        };
    }

    /** «فاتورة ضريبية» — نقلاً عن `invoices/print.blade.php`. */
    private function getLegacyTaxReceiptContent(): string
    {
        return <<<'HTML'
<div class="doc-ds legacy-receipt">
    <div class="receipt-container">
        <div class="receipt">
            <div class="receipt-header">
                <h1 class="receipt-title">{{document.title}}</h1>
                <p class="mb-0">{{business.name}}</p>
                <p class="mb-0">{{business.address}}</p>
                <p class="mb-0">رقم المسؤول: {{business.phone}}</p>
            </div>

            <div class="invoice-to">
                <p class="mb-0">فاتورة الى: {{customer.name}}</p>
                <p class="mb-0">{{customer.address}}</p>
                <h1 class="mb-0">{{customer.code}}</h1>
                <p class="mb-0">الرقم الضريبي: {{customer.tax_number}}</p>
                @if(!empty(customer.phone))
                <p class="mb-0">رقم جوال العميل: {{customer.phone}}</p>
                @endif
            </div>

            <div class="invoice-details">
                <div class="summary-row"><span>رقم الفاتورة:</span><span>{{document.number}}</span></div>
                <div class="summary-row"><span>تاريخ الفاتورة:</span><span>{{document.date}}</span></div>
            </div>

            <div class="invoice-items">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="35%">المنتج</th>
                            <th width="12%">الكمية</th>
                            <th width="15%">السعر</th>
                            <th width="13%">الخصم</th>
                            <th width="20%">المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{#items}}
                        <tr>
                            <td>{{index}}</td>
                            <td class="item-name">{{name}}</td>
                            <td>{{quantity}}<span class="legacy-unit">{{unit}}</span></td>
                            <td>{{price}}</td>
                            <td class="discount-cell">{{discount}}</td>
                            <td>{{total}}</td>
                        </tr>
                        {{/items}}
                    </tbody>
                </table>
            </div>

            <div class="invoice-summary">
                <div class="summary-row">
                    <span>المجموع الفرعي:</span>
                    <span>{{totals.subtotal}} {{business.currency}}</span>
                </div>
                @if(totals.discount > 0)
                <div class="summary-row discount-row">
                    <span>الخصم:</span>
                    <span>- {{totals.discount}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.shipping > 0)
                <div class="summary-row">
                    <span>تكلفة الشحن:</span>
                    <span>{{totals.shipping}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.tax > 0)
                <div class="summary-row">
                    <span>ضريبة القيمة المضافة:</span>
                    <span>{{totals.tax}} {{business.currency}}</span>
                </div>
                @endif
                <div class="summary-row grand-row">
                    <span>المجموع الإجمالي:</span>
                    <span>{{totals.grand_total}} {{business.currency}}</span>
                </div>
                @if(totals.paid > 0)
                <div class="summary-row">
                    <span>المدفوع:</span>
                    <span>{{totals.paid}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.returned > 0)
                <div class="summary-row discount-row">
                    <span>المرتجع:</span>
                    <span>- {{totals.returned}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.paid > 0 || totals.returned > 0)
                <div class="summary-row grand-row">
                    <span>المبلغ المتبقي:</span>
                    <span>{{totals.remaining}} {{business.currency}}</span>
                </div>
                @endif
            </div>

            <div class="signature">
                <p>الاسم: ________________</p>
                <p>التوقيع: _______________</p>
                <p class="thank-you">شكراً لتعاملكم معنا</p>
            </div>

            @if(!empty(document.qr_code))
            <div class="qr-code"><img src="{{document.qr_code}}" alt=""></div>
            @endif
        </div>
    </div>
</div>
HTML;
    }

    /** «فاتورة» بأربعة أعمدة — نقلاً عن `invoices/pdf.blade.php`. */
    private function getLegacyPlainReceiptContent(): string
    {
        return <<<'HTML'
<div class="doc-ds legacy-receipt">
    <div class="receipt-container">
        <div class="receipt">
            <div class="receipt-header">
                <h1 class="receipt-title">{{document.title}}</h1>
                <p class="mb-0">{{business.name}}</p>
                <p class="mb-0">{{business.address}}</p>
                <p class="mb-0">رقم المسؤول: {{business.phone}}</p>
            </div>

            <div class="invoice-to">
                <p class="mb-0">فاتورة الى: {{customer.name}}</p>
                <p class="mb-0">{{customer.address}}</p>
                <p class="mb-0">كود العميل: {{customer.code}}</p>
                <p class="mb-0">الرقم الضريبي: {{customer.tax_number}}</p>
                @if(!empty(customer.phone))
                <p class="mb-0">رقم جوال العميل: {{customer.phone}}</p>
                @endif
            </div>

            <div class="invoice-details">
                <div class="summary-row"><span>رقم الفاتورة:</span><span>{{document.number}}</span></div>
                <div class="summary-row"><span>تاريخ الفاتورة:</span><span>{{document.date}}</span></div>
            </div>

            <div class="invoice-items">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="40%">البند</th>
                            <th width="15%">الكمية</th>
                            <th width="20%">السعر</th>
                            <th width="25%">المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{#items}}
                        <tr>
                            <td class="item-name">{{name}}</td>
                            <td>{{quantity}}<span class="legacy-unit">{{unit}}</span></td>
                            <td>{{price}}</td>
                            <td>{{total}}</td>
                        </tr>
                        {{/items}}
                    </tbody>
                </table>
            </div>

            <div class="invoice-summary">
                <div class="summary-row">
                    <span>المجموع الفرعي:</span>
                    <span>{{totals.subtotal}} {{business.currency}}</span>
                </div>
                @if(totals.discount > 0)
                <div class="summary-row">
                    <span>الخصم:</span>
                    <span>- {{totals.discount}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.shipping > 0)
                <div class="summary-row">
                    <span>تكلفة الشحن:</span>
                    <span>{{totals.shipping}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.tax > 0)
                <div class="summary-row">
                    <span>ضريبة القيمة المضافة:</span>
                    <span>{{totals.tax}} {{business.currency}}</span>
                </div>
                @endif
                <div class="summary-row grand-row">
                    <span>المجموع الكلي:</span>
                    <span>{{totals.grand_total}} {{business.currency}}</span>
                </div>
                @if(totals.paid > 0)
                <div class="summary-row">
                    <span>المدفوع:</span>
                    <span>{{totals.paid}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.returned > 0)
                <div class="summary-row">
                    <span>المرتجع:</span>
                    <span>- {{totals.returned}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.paid > 0 || totals.returned > 0)
                <div class="summary-row grand-row">
                    <span>المبلغ المتبقي:</span>
                    <span>{{totals.remaining}} {{business.currency}}</span>
                </div>
                @endif
            </div>

            @if(!empty(document.qr_code))
            <div class="qr-code"><img src="{{document.qr_code}}" alt=""></div>
            @endif

            <div class="signature">
                <p>الاسم: ________________</p>
                <p>التوقيع: _______________</p>
                <p class="thank-you">شكراً لتعاملكم معنا</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    /** «فاتورة مرتجع» — نقلاً عن `retend_invoice/pdf.blade.php`. */
    private function getLegacyReturnReceiptContent(): string
    {
        return <<<'HTML'
<div class="doc-ds legacy-receipt">
    <div class="receipt-container">
        <div class="receipt">
            <div class="receipt-header">
                <h1 class="receipt-title">{{document.title}}</h1>
                <p class="mb-0">{{business.name}}</p>
                <p class="mb-0">{{business.address}}</p>
                <p class="mb-0">رقم المسؤول: {{business.phone}}</p>
            </div>

            <div class="invoice-to">
                <p class="mb-0">العميل: {{customer.name}}</p>
                <p class="mb-0">{{customer.address}}</p>
                <h1 class="mb-0">{{customer.code}}</h1>
                <p class="mb-0">الرقم الضريبي: {{customer.tax_number}}</p>
                @if(!empty(customer.phone))
                <p class="mb-0">الهاتف: {{customer.phone}}</p>
                @endif
            </div>

            <div class="invoice-details">
                <div class="summary-row"><span>رقم المرتجع:</span><span>{{document.number}}</span></div>
                <div class="summary-row"><span>التاريخ:</span><span>{{document.date}}</span></div>
            </div>

            @if(!empty(document.notes))
            <div class="return-reason"><strong>سبب المرتجع:</strong> {{document.notes}}</div>
            @endif

            <div class="invoice-items">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="35%">الصنف</th>
                            <th width="12%">الكمية</th>
                            <th width="13%">السعر</th>
                            <th width="15%">الخصم</th>
                            <th width="20%">المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{#items}}
                        <tr>
                            <td>{{index}}</td>
                            <td class="item-name">{{name}}</td>
                            <td>{{quantity}}<span class="legacy-unit">{{unit}}</span></td>
                            <td>{{price}}</td>
                            <td>{{discount}}</td>
                            <td>{{total}}</td>
                        </tr>
                        {{/items}}
                    </tbody>
                </table>
            </div>

            <div class="invoice-summary">
                <div class="summary-row">
                    <span>المجموع الفرعي:</span>
                    <span>{{totals.subtotal}} {{business.currency}}</span>
                </div>
                @if(totals.discount > 0)
                <div class="summary-row discount-row">
                    <span>الخصم:</span>
                    <span>-{{totals.discount}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.shipping > 0)
                <div class="summary-row">
                    <span>الشحن:</span>
                    <span>+{{totals.shipping}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.tax > 0)
                <div class="summary-row">
                    <span>الضريبة:</span>
                    <span>+{{totals.tax}} {{business.currency}}</span>
                </div>
                @endif
                <div class="summary-row grand-row">
                    <span><strong>إجمالي المرتجع:</strong></span>
                    <span><strong>{{totals.grand_total}} {{business.currency}}</strong></span>
                </div>
            </div>

            @if(!empty(document.qr_code))
            <div class="qr-code"><img src="{{document.qr_code}}" alt=""></div>
            @endif

            <div class="signature">
                <p>توقيع العميل: _______________</p>
                <p>ختم المحل: _______________</p>
                <p class="thank-you">شكراً لتعاملكم معنا</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    /** «إشعار دائن» — نقلاً عن `creted_note/pdf.blade.php`. */
    private function getLegacyCreditNoteContent(): string
    {
        return <<<'HTML'
<div class="doc-ds legacy-receipt">
    <div class="receipt-container">
        <div class="receipt">
            <div class="receipt-header">
                <h1 class="receipt-title">{{document.title}}</h1>
                <p class="mb-0">{{business.name}}</p>
                <p class="mb-0">{{business.address}}</p>
                <p class="mb-0">رقم المسؤول: {{business.phone}}</p>
            </div>

            <div class="invoice-to">
                <p class="mb-0">فاتورة الى: {{customer.name}}</p>
                <p class="mb-0">{{customer.address}}</p>
                <h1 class="mb-0">{{customer.code}}</h1>
                <p class="mb-0">الرقم الضريبي: {{customer.tax_number}}</p>
                @if(!empty(customer.phone))
                <p class="mb-0">رقم جوال العميل: {{customer.phone}}</p>
                @endif
            </div>

            <div class="invoice-details">
                <div class="summary-row"><span>رقم الإشعار:</span><span>{{document.number}}</span></div>
                <div class="summary-row"><span>تاريخ الإشعار:</span><span>{{document.date}}</span></div>
            </div>

            <div class="invoice-items">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="35%">الصنف</th>
                            <th width="12%">الكمية</th>
                            <th width="13%">السعر</th>
                            <th width="15%">الخصم</th>
                            <th width="20%">المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{#items}}
                        <tr>
                            <td>{{index}}</td>
                            <td class="item-name">{{name}}</td>
                            <td>{{quantity}}<span class="legacy-unit">{{unit}}</span></td>
                            <td>{{price}}</td>
                            <td>{{discount}}</td>
                            <td>{{total}}</td>
                        </tr>
                        {{/items}}
                    </tbody>
                </table>
            </div>

            <div class="invoice-summary">
                <div class="summary-row">
                    <span>المجموع الفرعي:</span>
                    <span>{{totals.subtotal}} {{business.currency}}</span>
                </div>
                @if(totals.discount > 0)
                <div class="summary-row discount-row">
                    <span>الخصم:</span>
                    <span>-{{totals.discount}} {{business.currency}}</span>
                </div>
                @endif
                @if(totals.tax > 0)
                <div class="summary-row">
                    <span>الضريبة:</span>
                    <span>+{{totals.tax}} {{business.currency}}</span>
                </div>
                @endif
                <div class="summary-row grand-row">
                    <span><strong>إجمالي الإشعار:</strong></span>
                    <span><strong>{{totals.grand_total}} {{business.currency}}</strong></span>
                </div>
            </div>

            @if(!empty(document.notes))
            <div class="notes"><strong>ملاحظات:</strong> {{document.notes}}</div>
            @endif

            @if(!empty(document.qr_code))
            <div class="qr-code"><img src="{{document.qr_code}}" alt=""></div>
            @endif

            <div class="signature">
                <p>توقيع العميل: _______________</p>
                <p>ختم المحل: _______________</p>
                <p class="thank-you">شكراً لتعاملكم معنا</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * أنماطُ المطبوعة الأصلية — منقولةٌ عن `<style>` في ملفات بلايد
     * الأربعة بمقاييسها بالبكسل كما كُتبت.
     *
     * وما حُذف منها: خلفيةُ `body` الرمادية وحشوتُه وظلُّ الورقة وإطارُها.
     * صفحةُ الطباعة (`documents/print-page.blade.php`) تضع الورقةَ البيضاء
     * على أرضيةٍ رمادية أصلاً، فتكرارُها هنا يُدخل ورقةً في ورقة.
     */
    private function legacyReceiptStyles(string $extra = ''): string
    {
        return <<<CSS
.legacy-receipt { font-family: 'Tajawal', sans-serif; direction: rtl; font-weight: bold; }
/* الوحدة تحت الكمية: عرضُ الورقة ٨٠ مم لا يحتمل عموداً إضافياً، وسطرٌ بلا
   وحدةٍ محفوظة يُخرج الوسمَ فارغاً فلا يظهر منه شيء. */
.legacy-receipt .legacy-unit { display: block; font-size: .8em; font-weight: 600; opacity: .75;
    line-height: 1.4; }
.legacy-receipt .receipt-container { display: flex; justify-content: center; align-items: center; }
.legacy-receipt .receipt { width: 80mm; max-width: 100%; background-color: #fff; padding: 10px;
    margin: 0 auto; }
.legacy-receipt .receipt-header { padding-bottom: 10px; border-bottom: 1px dashed #ccc;
    margin-bottom: 10px; text-align: center; }
.legacy-receipt .receipt-title { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
.legacy-receipt p { margin: 0 0 2px; }
.legacy-receipt .mb-0 { margin-bottom: 0; }
.legacy-receipt .invoice-to { margin-bottom: 10px; padding-bottom: 10px;
    border-bottom: 1px dashed #ccc; }
.legacy-receipt .invoice-to h1 { font-size: 28px; text-align: center; margin: 8px 0; }
.legacy-receipt .invoice-details { margin-bottom: 10px; padding-bottom: 10px;
    border-bottom: 1px dashed #ccc; }
.legacy-receipt .invoice-items { margin-bottom: 10px; }
.legacy-receipt .table { width: 100%; margin-bottom: 10px; font-size: 12px;
    border-collapse: collapse; }
.legacy-receipt .table th, .legacy-receipt .table td { text-align: center; padding: 5px;
    border-bottom: 1px dashed #ddd; }
.legacy-receipt .table th { background-color: #f5f5f5; border-bottom: 1px solid #333;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.legacy-receipt .table td.item-name { text-align: right; }
.legacy-receipt .table td.discount-cell { color: #dc3545; }
.legacy-receipt .invoice-summary { border-top: 1px dashed #ccc; padding-top: 10px;
    margin-bottom: 15px; }
.legacy-receipt .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
.legacy-receipt .summary-row.discount-row { color: #dc3545; }
.legacy-receipt .summary-row.grand-row { font-weight: bold; border-top: 2px solid #333;
    padding-top: 10px; margin-top: 10px; font-size: 1.1em; }
.legacy-receipt .qr-code { margin: 15px 0; text-align: center; }
.legacy-receipt .qr-code img { width: 120px; height: 120px; -webkit-print-color-adjust: exact;
    print-color-adjust: exact; }
.legacy-receipt .signature { margin: 15px auto 0; padding-top: 10px; border-top: 1px dashed #333;
    width: 90%; text-align: center; }
.legacy-receipt .thank-you { font-style: italic; margin-top: 5px; }
{$extra}
@media print {
    .legacy-receipt .receipt { width: 100%; box-shadow: none; border: none; padding: 0;
        margin: 0 auto !important; }
    .legacy-receipt .receipt-container { min-height: auto; }
    .legacy-receipt .qr-code img { width: 70px !important; height: 70px !important; }
}
CSS;
    }

    /** ما تزيده مطبوعةُ المرتجع وحدها: لوحةُ سبب الإرجاع. */
    private function legacyReturnExtras(): string
    {
        return '.legacy-receipt .return-reason { margin-bottom: 10px; padding: 8px;'
            .' background-color: #fff3cd; border-radius: 4px; border-bottom: 1px dashed #ccc;'
            .' -webkit-print-color-adjust: exact; print-color-adjust: exact; }';
    }

    /** ما تزيده مطبوعةُ الإشعار الدائن وحدها: لوحةُ الملاحظات. */
    private function legacyCreditNoteExtras(): string
    {
        return '.legacy-receipt .notes { margin-bottom: 10px; padding: 8px;'
            .' background-color: #f8f9fa; border-radius: 4px; font-size: 12px;'
            .' -webkit-print-color-adjust: exact; print-color-adjust: exact; }';
    }
}
