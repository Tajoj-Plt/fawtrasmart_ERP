<?php

namespace Database\Seeders\Concerns;

/**
 * «تصميم الموارد البشرية» — عائلةُ مطبوعاتٍ مستقلّة لعقد العمل وقسيمة الراتب
 * وكشف مسير الرواتب وسندَي السلفة وصرف العمولة (2026-09-24).
 *
 * طلب صاحبُ النظام أن تختلف هذه المستندات عن الفواتير: لا ترويسةَ داكنة ولا
 * جدولَ أصنافٍ وأسعارٍ وضريبة ولا رمزَ QR. فالهيكلُ هنا وثيقةٌ رسمية:
 *
 *   ترويسة (المنشأة ← عنوان المستند بخطٍّ نسخيّ ← بيانات المستند) وخطّان رفيعان
 *   ثمّ متنُ النوع: موادُّ العقد، أو عمودا المستحقات والاستقطاعات، أو جدولُ
 *   المسير العريض (A4 أفقي)، أو مبلغُ السند وتفقيطُه
 *   ثمّ التواقيعُ والاعتماد، وسطرُ تذييلٍ صغير.
 *
 * **الألوان**: `--primary-color` للخطوط والعناوين، و`--secondary-color` لونٌ
 * فاتح للخلفيات (الشريط، رأس الجدول، صندوق الصافي) — فمنتقي الألوان يلوّن
 * العائلةَ كلَّها. **والمقاسات بـ`em`** مع `doc-ds` على الغلاف.
 *
 * **الحقول المسمّاة** (`contract.*`، `slip.*`، `payroll.*`، `advance.*`،
 * `commission.*`، و`item.*` داخل حلقة البنود) يملؤها
 * `DocumentTemplateDataService`، ويفكّها `DocumentTemplateRenderer::parseTokens()`.
 * والشرطُ المقارِن يُكتب بين قوسين: `@if((item.kind) === 'deduction')` —
 * الرمزُ يصير `$item['kind'] ?? ''` و`??` أدنى أولويةً من `===`.
 */
trait HrDocumentDesign
{
    /** لاحقةُ الاسم — بها يعرفه أمرُ `document-templates:refresh --adopt-hr` */
    public const HR_SUFFIX = 'تصميم الموارد البشرية';

    /** أنواعُ المستندات التي لها هذا التصميم */
    public const HR_TYPES = ['contract', 'salary_slip', 'payroll_sheet', 'salary_advance', 'commission_payment'];

    /**
     * @param  array<string, mixed>  $info
     * @return array<int, array<string, mixed>>
     */
    public function hrPresetsFor(string $documentType, array $info): array
    {
        if (! in_array($documentType, self::HR_TYPES, true)) {
            return [];
        }

        $prefix = $info['prefix'];
        $name = "$prefix - ".self::HR_SUFFIX;
        $hidden = ['show_qr' => false, 'show_barcode' => false, 'show_tax' => false, 'show_discount' => false];

        // [لونٌ أساسيّ، لونٌ فاتح للخلفيات]
        [$primary, $tint] = match ($documentType) {
            'contract' => ['#1e3a5f', '#eef2f7'],
            'salary_slip' => ['#0f766e', '#e8f4f1'],
            'payroll_sheet' => ['#155e75', '#e9f3f6'],
            default => ['#4c3f73', '#f1eef7'],
        };

        $a4 = fn (string $content, int $fontSize = 13, array $extra = []) => array_merge([
            'name' => "$name A4", 'paper_size' => 'A4', 'is_default' => true,
            'content' => $content, 'styles' => $this->hrA4Styles(),
            'settings' => $this->makeSettings($primary, $tint, $fontSize, $hidden),
        ], $extra);

        return match ($documentType) {
            'contract' => [$a4($this->hrContractA4())],
            'salary_slip' => [
                $a4($this->hrSlipA4()),
                ['name' => "$name حراري 80 مم", 'paper_size' => 'Thermal_80mm', 'is_default' => false,
                    'content' => $this->hrSlipThermal(), 'styles' => $this->hrThermalStyles(),
                    'settings' => $this->makeSettings('#000000', '#eeeeee', 12, $hidden)],
            ],
            'payroll_sheet' => [$a4($this->hrPayrollA4(), 11, ['name' => "$name A4 أفقي", 'orientation' => 'landscape'])],
            'salary_advance' => [$a4($this->hrAdvanceA4())],
            'commission_payment' => [$a4($this->hrCommissionA4())],
        };
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                        لبنات مشتركة                          ║
    // ╚══════════════════════════════════════════════════════════════╝

    /** خطُّ العناوين النسخيّ — يُحمَّل في المتن لأنّ المصيّر يحمّل Cairo وTajawal وحدهما */
    private function hFont(): string
    {
        return '<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">';
    }

    /**
     * «تسمية : قيمة». `$cond` تعبيرُ ظهور، أو `true` فيُشتقّ من الرمز نفسه.
     */
    private function hKv(string $label, string $value, $cond = null, string $class = 'h-kv'): string
    {
        $row = '<div class="'.$class.'"><span class="h-k">'.$label.'</span><span class="h-v">'.$value.'</span></div>';

        if ($cond === true && preg_match('/^(?:<span class="h-ltr">)?\{\{([a-z_]+\.[a-z_]+)\}\}(?:<\/span>)?$/', $value, $m)) {
            $cond = '!empty('.$m[1].')';
        }

        return is_string($cond) ? "@if($cond)\n$row\n@endif" : $row;
    }

    /** الترويسة: المنشأة ← العنوان ← بيانات المستند */
    private function hHead(array $meta): string
    {
        $metaRows = implode("\n", $meta);

        return <<<HTML
    <div class="h-top">
        <div class="h-org">
            @if(!empty(business.logo))
            <img src="{{business.logo}}" class="h-logo company-logo" alt="">
            @endif
            <div class="h-org-text">
                <div class="h-org-name hr-serif">{{business.name}}</div>
                @if(!empty(business.commercial_registration))
                <div class="h-org-id">السجل التجاري: <span class="h-ltr">{{business.commercial_registration}}</span></div>
                @endif
                @if(!empty(business.tax_number))
                <div class="h-org-id">الرقم الضريبي: <span class="h-ltr">{{business.tax_number}}</span></div>
                @endif
            </div>
        </div>
        <div class="h-title-block">
            <div class="h-title hr-serif">{{document.title}}</div>
            @if(!empty(document.title_en))
            <div class="h-title-en">{{document.title_en}}</div>
            @endif
        </div>
        <div class="h-meta">
$metaRows
        </div>
    </div>
    <div class="h-rule"></div>
HTML;
    }

    /** عنوانُ قسمٍ بخطٍّ رفيعٍ ممتدّ */
    private function hSection(string $title, string $number = ''): string
    {
        $num = $number !== '' ? '<span class="h-sec-num">'.$number.'</span>' : '';

        return '<div class="h-sec">'.$num.'<span class="h-sec-title hr-serif">'.$title.'</span></div>';
    }

    /**
     * التواقيع — كلٌّ: [العنوان، الاسم أو فارغ]. `$stampFirst` يضع خانةَ الختم
     * تحت التوقيع الأوّل (المنشأة).
     *
     * @param  array<int, array{0:string,1?:string}>  $sigs
     */
    private function hSigs(array $sigs, bool $stampFirst = false): string
    {
        $html = '<div class="h-sigs">';

        foreach ($sigs as $i => $sig) {
            $name = ($sig[1] ?? '') !== '' ? '<div class="h-sig-name">'.$sig[1].'</div>' : '<div class="h-sig-name h-dots">الاسم: ....................................</div>';
            $stamp = ($stampFirst && $i === 0) ? '<div class="h-stamp">الختم</div>' : '';

            $html .= '<div class="h-sig"><div class="h-sig-title">'.$sig[0].'</div>'.$name
                .'<div class="h-sig-line"><span>التوقيع</span></div>'
                .'<div class="h-sig-date">التاريخ: ......./......./..........</div>'.$stamp.'</div>';
        }

        return $html.'</div>';
    }

    /** سطرُ التذييل الصغير */
    private function hFoot(string $text): string
    {
        return '<div class="h-foot"><span>'.$text.'</span><span class="h-ltr">{{document.number}}</span></div>';
    }

    private function hWrap(string $class, string $body): string
    {
        return $this->hFont()."\n<div class=\"doc-ds hr-doc $class\">\n".$body."\n</div>";
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                          عقد العمل                           ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function hrContractA4(): string
    {
        $head = $this->hHead([
            $this->hKv('رقم العقد', '<span class="h-ltr">{{document.number}}</span>'),
            $this->hKv('تاريخ التحرير', '{{contract.contract_date}}', true),
            $this->hKv('تاريخ البداية', '{{contract.start_date}}', true),
        ]);

        $first = implode("\n", [
            $this->hKv('المنشأة', '{{business.name}}'),
            $this->hKv('السجل التجاري', '<span class="h-ltr">{{business.commercial_registration}}</span>', '!empty(business.commercial_registration)'),
            $this->hKv('الرقم الضريبي', '<span class="h-ltr">{{business.tax_number}}</span>', '!empty(business.tax_number)'),
            $this->hKv('العنوان', '{{business.address}}', true),
            $this->hKv('الهاتف', '<span class="h-ltr">{{business.phone}}</span>', '!empty(business.phone)'),
        ]);

        $second = implode("\n", [
            $this->hKv('الاسم', '{{contract.employee_name}}'),
            $this->hKv('الرقم الوظيفي', '<span class="h-ltr">{{contract.employee_code}}</span>', '!empty(contract.employee_code)'),
            $this->hKv('الجنسية', '{{contract.nationality}}', true),
            $this->hKv('رقم التأمينات', '<span class="h-ltr">{{contract.gosi_number}}</span>', '!empty(contract.gosi_number)'),
            $this->hKv('المسمّى الوظيفي', '{{contract.job_title}}', true),
            $this->hKv('القسم', '{{contract.department}}', true),
            $this->hKv('الفرع', '{{contract.branch}}', true),
            $this->hKv('الجوال', '<span class="h-ltr">{{contract.phone}}</span>', '!empty(contract.phone)'),
        ]);

        $duration = implode("\n", [
            $this->hKv('تاريخ بداية العقد', '{{contract.start_date}}', true),
            $this->hKv('تاريخ نهاية العقد', '{{contract.end_date}}', true),
            $this->hKv('مدة العقد', '{{contract.duration}}', true),
            $this->hKv('تاريخ المباشرة', '{{contract.join_date}}', true),
            $this->hKv('نهاية فترة التجربة', '{{contract.probation_end_date}}', true),
            $this->hKv('دورة صرف الراتب', '{{contract.receiving_cycle}}', true),
        ]);

        $sec = fn (string $n, string $t) => $this->hSection($t, $n);

        $body = $head."\n".<<<HTML
    <p class="h-lead">
        @if(!empty(contract.contract_date))
        إنّه في تاريخ <strong>{{contract.contract_date}}</strong> تمّ الاتفاق بين كلٍّ من:
        @else
        تمّ الاتفاق بين كلٍّ من:
        @endif
    </p>
    <div class="h-parties">
        <div class="h-party">
            <div class="h-party-head"><span class="hr-serif">الطرف الأول</span><small>صاحب العمل</small></div>
$first
        </div>
        <div class="h-party party-card">
            <div class="h-party-head"><span class="hr-serif">الطرف الثاني</span><small>الموظف</small></div>
$second
        </div>
    </div>
    <p class="h-lead">وقد اتّفق الطرفان، وهما بكامل الأهلية المعتبرة نظاماً، على ما يلي:</p>

    {$sec('المادة الأولى', 'مدّة العقد')}
    <div class="h-grid2">
$duration
    </div>
    @if(!empty(contract.has_probation))
    <p class="h-text">يخضع الطرف الثاني لفترة تجربةٍ تنتهي في <strong>{{contract.probation_end_date}}</strong>، ويحقّ لأيٍّ من الطرفين إنهاء العقد خلالها وفق النظام.</p>
    @endif

    {$sec('المادة الثانية', 'المسمّى الوظيفي والمهام')}
    <p class="h-text">
        يعمل الطرف الثاني لدى الطرف الأول
        @if(!empty(contract.job_title))
        بوظيفة <strong>{{contract.job_title}}</strong>
        @endif
        @if(!empty(contract.department))
        في قسم <strong>{{contract.department}}</strong>
        @endif
        @if(!empty(contract.branch))
        في <strong>{{contract.branch}}</strong>
        @endif
        على أن يلتزم بأداء المهام الموكلة إليه بأمانةٍ وإتقان، وباتّباع التعليمات واللوائح المعتمدة لدى الطرف الأول بما لا يخالف النظام.
    </p>

    {$sec('المادة الثالثة', 'الأجر')}
    <table class="h-table h-pay">
        <thead><tr><th class="h-seq">م</th><th class="h-name">البند</th><th>النوع</th><th class="h-num">المبلغ الشهري</th></tr></thead>
        <tbody>
            {{#items}}
            <tr><td class="h-seq">{{index}}</td><td class="h-name">{{name}}</td><td>{{unit}}</td><td class="h-num">{{total}}</td></tr>
            {{/items}}
        </tbody>
        <tfoot>
            <tr><td colspan="3">الراتب الأساسي</td><td class="h-num">{{contract.basic_salary}}</td></tr>
            <tr><td colspan="3">إجمالي البدلات والمستحقات</td><td class="h-num">{{contract.total_additions}}</td></tr>
            <tr><td colspan="3">إجمالي الراتب الشهري</td><td class="h-num">{{contract.gross_salary}}</td></tr>
            @if(!empty(contract.has_deductions))
            <tr><td colspan="3">إجمالي المستقطعات</td><td class="h-num">- {{contract.total_deductions}}</td></tr>
            @endif
            <tr class="h-net-row"><td colspan="3">صافي الراتب الشهري</td><td class="h-num">{{contract.net_salary}} <small>{{contract.currency}}</small></td></tr>
        </tfoot>
    </table>
    @if(!empty(contract.net_salary_words))
    <p class="h-words">{{contract.net_salary_words}}</p>
    @endif

    {$sec('المادة الرابعة', 'ساعات العمل والإجازات')}
    <p class="h-text">تُحدَّد ساعاتُ العمل اليومية والأسبوعية وأوقاتُ الراحة والإجازةُ الأسبوعية والإجازةُ السنوية المدفوعة الأجر والإجازاتُ الأخرى وفق نظام العمل المعمول به ولائحة تنظيم العمل المعتمدة لدى الطرف الأول، ويُعوَّض العمل الإضافي وفق ما يقرّره النظام.</p>

    {$sec('المادة الخامسة', 'أحكام عامة')}
    @if(!empty(contract.description))
    <p class="h-text h-pre">{{contract.description}}</p>
    @endif
    <p class="h-text">يخضع هذا العقد لأحكام نظام العمل ولوائحه التنفيذية فيما لم يرد فيه نصّ، ولا يجوز تعديل أيٍّ من بنوده إلا باتفاقٍ مكتوبٍ موقّعٍ من الطرفين. وقد حُرِّر من نسختين أصليتين، بيد كلّ طرفٍ نسخةٌ للعمل بموجبها.</p>

HTML;

        $body .= $this->hSigs([['الطرف الأول (صاحب العمل)', '{{business.name}}'], ['الطرف الثاني (الموظف)', '{{contract.employee_name}}']], true)
            ."\n".$this->hFoot('عقد عمل — صادر من نظام {{business.name}}');

        return $this->hWrap('hr-contract', $body);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                         قسيمة الراتب                         ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function hrSlipA4(): string
    {
        $head = $this->hHead([
            $this->hKv('رقم القسيمة', '<span class="h-ltr">{{document.number}}</span>'),
            $this->hKv('عن شهر', '<span class="h-ltr">{{slip.period}}</span>', '!empty(slip.period)'),
            $this->hKv('تاريخ الصرف', '{{slip.payment_date}}', true),
        ]);

        $emp = implode("\n", [
            $this->hKv('اسم الموظف', '{{slip.employee_name}}'),
            $this->hKv('الرقم الوظيفي', '<span class="h-ltr">{{slip.employee_code}}</span>', '!empty(slip.employee_code)'),
            $this->hKv('المسمّى الوظيفي', '{{slip.job_title}}', true),
            $this->hKv('القسم', '{{slip.department}}', true),
            $this->hKv('الفرع', '{{slip.branch}}', true),
            $this->hKv('المسير', '{{slip.payroll_name}}', true),
            $this->hKv('الفترة', 'من <span class="h-ltr">{{slip.period_from}}</span> إلى <span class="h-ltr">{{slip.period_to}}</span>', '!empty(slip.period_from)'),
            $this->hKv('البنك', '{{slip.bank_name}}', true),
            $this->hKv('الآيبان', '<span class="h-ltr">{{slip.iban}}</span>', '!empty(slip.iban)'),
        ]);

        $body = $head."\n".$this->hSection('بيانات الموظف')."\n"
            ."<div class=\"h-grid3 party-card\">\n$emp\n</div>\n".<<<'HTML'
    <div class="h-cols">
        <div class="h-col h-earn">
            <div class="h-col-head hr-serif">المستحقات</div>
            <table class="h-lines">
                <tbody>
                    {{#items}}
                    @if((item.kind) === 'earning')
                    <tr><td>{{name}}</td><td class="h-num">{{item.amount}}</td></tr>
                    @endif
                    {{/items}}
                </tbody>
                <tfoot><tr><td>إجمالي المستحقات</td><td class="h-num">{{slip.total_earnings}}</td></tr></tfoot>
            </table>
        </div>
        <div class="h-col h-ded">
            <div class="h-col-head hr-serif">الاستقطاعات</div>
            <table class="h-lines">
                <tbody>
                    {{#items}}
                    @if((item.kind) === 'deduction')
                    <tr><td>{{name}}</td><td class="h-num">{{item.amount}}</td></tr>
                    @endif
                    {{/items}}
                    {{#items}}
                    @if((item.kind) === 'advance')
                    <tr><td>{{name}}</td><td class="h-num">{{item.amount}}</td></tr>
                    @endif
                    {{/items}}
                    @if(empty(slip.has_deductions))
                    <tr><td class="h-muted" colspan="2">لا استقطاعات على هذه القسيمة</td></tr>
                    @endif
                </tbody>
                <tfoot><tr><td>إجمالي الاستقطاعات</td><td class="h-num">{{slip.total_deductions}}</td></tr></tfoot>
            </table>
        </div>
    </div>
    <div class="h-net">
        <div class="h-net-label hr-serif">صافي الراتب المستحق</div>
        <div class="h-net-amount"><span class="h-ltr">{{slip.net}}</span> <small>{{slip.currency}}</small></div>
        <div class="h-net-words">{{slip.net_words}}</div>
    </div>
    @if(!empty(slip.notes))
    <p class="h-note"><strong>ملاحظات:</strong> {{slip.notes}}</p>
    @endif
    <p class="h-note h-conf">هذه القسيمة سرّية وتخصّ الموظف وحده، ويُرجى مراجعة إدارة الموارد البشرية في أيّ ملاحظةٍ خلال سبعة أيام من تاريخ الصرف.</p>
HTML;

        $body .= "\n".$this->hSigs([['المحاسب'], ['المستلم (الموظف)', '{{slip.employee_name}}']])
            ."\n".$this->hFoot('قسيمة راتب — صادرة من نظام {{business.name}}');

        return $this->hWrap('hr-slip', $body);
    }

    private function hrSlipThermal(): string
    {
        return $this->hWrap('hr-rcpt', <<<'HTML'
    <div class="t-org hr-serif">{{business.name}}</div>
    @if(!empty(business.tax_number))
    <div class="t-sub">الرقم الضريبي: <span class="h-ltr">{{business.tax_number}}</span></div>
    @endif
    <div class="t-title hr-serif">{{document.title}}</div>
    <div class="t-kv"><span>رقم القسيمة</span><span class="h-ltr">{{document.number}}</span></div>
    <div class="t-kv"><span>عن شهر</span><span class="h-ltr">{{slip.period}}</span></div>
    <div class="t-rule"></div>
    <div class="party-card">
        <div class="t-kv"><span>الموظف</span><span>{{slip.employee_name}}</span></div>
        @if(!empty(slip.employee_code))
        <div class="t-kv"><span>الرقم الوظيفي</span><span class="h-ltr">{{slip.employee_code}}</span></div>
        @endif
        @if(!empty(slip.department))
        <div class="t-kv"><span>القسم</span><span>{{slip.department}}</span></div>
        @endif
    </div>
    <div class="t-head">المستحقات</div>
    {{#items}}
    @if((item.kind) === 'earning')
    <div class="t-kv"><span>{{name}}</span><span>{{item.amount}}</span></div>
    @endif
    {{/items}}
    <div class="t-kv t-sum"><span>الإجمالي</span><span>{{slip.total_earnings}}</span></div>
    <div class="t-head">الاستقطاعات</div>
    {{#items}}
    @if((item.kind) === 'deduction')
    <div class="t-kv"><span>{{name}}</span><span>{{item.amount}}</span></div>
    @endif
    {{/items}}
    {{#items}}
    @if((item.kind) === 'advance')
    <div class="t-kv"><span>{{name}}</span><span>{{item.amount}}</span></div>
    @endif
    {{/items}}
    <div class="t-kv t-sum"><span>الإجمالي</span><span>{{slip.total_deductions}}</span></div>
    <div class="t-net"><span>صافي الراتب</span><span>{{slip.net}} <small>{{slip.currency}}</small></span></div>
    <div class="t-words">{{slip.net_words}}</div>
    <div class="t-sig">توقيع المستلم: ..............................</div>
HTML);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                  كشف مسير الرواتب (A4 أفقي)                 ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function hrPayrollA4(): string
    {
        $head = $this->hHead([
            $this->hKv('رقم المسير', '<span class="h-ltr">{{payroll.code}}</span>'),
            $this->hKv('الفترة', 'من <span class="h-ltr">{{payroll.period_from}}</span> إلى <span class="h-ltr">{{payroll.period_to}}</span>', '!empty(payroll.period_from)'),
            $this->hKv('الحالة', '{{payroll.status}}', true),
        ]);

        $scope = implode("\n", [
            $this->hKv('اسم المسير', '{{payroll.name}}', null, 'h-chip'),
            $this->hKv('الفرع', '{{payroll.branch}}', true, 'h-chip'),
            $this->hKv('القسم', '{{payroll.department}}', true, 'h-chip'),
            $this->hKv('المسمّى', '{{payroll.job_title}}', true, 'h-chip'),
            $this->hKv('تاريخ الاعتماد', '{{payroll.approved_at}}', true, 'h-chip'),
        ]);

        $body = $head."\n<div class=\"h-chips\">\n$scope\n</div>\n".<<<'HTML'
    <table class="h-table h-sheet">
        <thead>
            <tr>
                <th class="h-seq">#</th>
                <th class="h-name">الموظف</th>
                <th>الكود</th>
                <th>القسم</th>
                <th class="h-num">الأساسي</th>
                <th class="h-num">البدلات</th>
                <th class="h-num">الإجمالي</th>
                <th class="h-num">التأمينات</th>
                <th class="h-num">السلف</th>
                <th class="h-num">الخصميات</th>
                <th class="h-num h-net-col">الصافي</th>
                <th class="h-signcol">التوقيع</th>
            </tr>
        </thead>
        <tbody>
            {{#items}}
            <tr>
                <td class="h-seq">{{index}}</td>
                <td class="h-name">{{item.emp_name}}</td>
                <td class="h-ltr-cell">{{item.emp_code}}</td>
                <td>{{item.department}}</td>
                <td class="h-num">{{item.basic}}</td>
                <td class="h-num">{{item.allowances}}</td>
                <td class="h-num">{{item.gross}}</td>
                <td class="h-num">{{item.insurance}}</td>
                <td class="h-num">{{item.advances}}</td>
                <td class="h-num">{{item.other_deductions}}</td>
                <td class="h-num h-net-col">{{item.net}}</td>
                <td class="h-signcol"></td>
            </tr>
            {{/items}}
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">الإجمالي ({{payroll.employees_count}} موظف)</td>
                <td class="h-num">{{payroll.total_basic}}</td>
                <td class="h-num">{{payroll.total_allowances}}</td>
                <td class="h-num">{{payroll.total_gross}}</td>
                <td class="h-num">{{payroll.total_insurance}}</td>
                <td class="h-num">{{payroll.total_advances}}</td>
                <td class="h-num">{{payroll.total_other}}</td>
                <td class="h-num h-net-col">{{payroll.total_net}}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <div class="h-cards">
        <div class="h-card"><span>عدد الموظفين</span><strong>{{payroll.employees_count}}</strong></div>
        <div class="h-card"><span>إجمالي الرواتب</span><strong>{{payroll.total_gross}}</strong></div>
        <div class="h-card"><span>إجمالي الاستقطاعات</span><strong>{{payroll.total_deductions}}</strong></div>
        <div class="h-card h-card-main"><span>صافي المصروف</span><strong>{{payroll.total_net}} <small>{{business.currency}}</small></strong></div>
    </div>
    @if(!empty(payroll.net_words))
    <p class="h-words">صافي المصروف: {{payroll.net_words}}</p>
    @endif
HTML;

        $body .= "\n".$this->hSigs([['أعدّه'], ['راجعه'], ['اعتمده']])
            ."\n".$this->hFoot('كشف مسير رواتب — صادر من نظام {{business.name}}');

        return $this->hWrap('hr-payroll', $body);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                 سند السلفة وسند صرف العمولة                  ║
    // ╚══════════════════════════════════════════════════════════════╝

    /** بياناتُ الموظف في السندين — `customer.*` كما يملؤها `employeeParty()` */
    private function hVoucherEmployee(): string
    {
        return $this->hSection('بيانات الموظف')."\n<div class=\"h-grid2 party-card\">\n".implode("\n", [
            $this->hKv('الاسم', '{{customer.name}}'),
            $this->hKv('الرقم الوظيفي', '<span class="h-ltr">{{customer.code}}</span>', '!empty(customer.code)'),
            $this->hKv('القسم / الوظيفة', '{{customer.address}}', true),
            $this->hKv('الجوال', '<span class="h-ltr">{{customer.phone}}</span>', '!empty(customer.phone)'),
        ])."\n</div>";
    }

    private function hrAdvanceA4(): string
    {
        $head = $this->hHead([
            $this->hKv('رقم السند', '<span class="h-ltr">{{document.number}}</span>'),
            $this->hKv('تاريخ الطلب', '{{advance.requested_date}}', true),
            $this->hKv('تاريخ الصرف', '{{advance.disbursed_date}}', true),
        ]);

        $details = implode("\n", [
            $this->hKv('نوع السلفة', '{{advance.type}}', true),
            $this->hKv('الحالة', '{{advance.stage}}', true),
            $this->hKv('الخزينة', '{{advance.treasury}}', true),
            $this->hKv('بداية الاستقطاع', '<span class="h-ltr">{{advance.start_month}}</span>', '!empty(advance.start_month)'),
            $this->hKv('قيمة القسط', '{{advance.installment_amount}}', true),
            $this->hKv('المعتمِد', '{{advance.approver}}', true),
        ]);

        $body = $head."\n".$this->hVoucherEmployee()."\n".<<<HTML
    <div class="h-amount">
        <div class="h-net-label hr-serif">مبلغ السلفة</div>
        <div class="h-net-amount"><span class="h-ltr">{{advance.amount}}</span> <small>{{business.currency}}</small></div>
        @if(!empty(advance.amount_words))
        <div class="h-net-words">{{advance.amount_words}}</div>
        @endif
    </div>
{$this->hSection('تفاصيل السلفة')}
    <div class="h-grid2">
$details
    </div>
    @if(!empty(document.notes))
    <p class="h-note"><strong>البيان:</strong> {{document.notes}}</p>
    @endif
{$this->hSection('جدول السداد')}
    <table class="h-table">
        <thead><tr><th class="h-seq">م</th><th class="h-name">الاستحقاق</th><th>الحالة</th><th class="h-num">المبلغ</th></tr></thead>
        <tbody>
            {{#items}}
            <tr><td class="h-seq">{{index}}</td><td class="h-name">{{name}}</td><td>{{unit}}</td><td class="h-num">{{total}}</td></tr>
            {{/items}}
        </tbody>
        <tfoot>
            @if(!empty(advance.has_repaid))
            <tr><td colspan="3">المسدَّد</td><td class="h-num">{{advance.repaid}}</td></tr>
            @endif
            <tr class="h-net-row"><td colspan="3">المتبقّي على الموظف</td><td class="h-num">{{advance.remaining}}</td></tr>
        </tfoot>
    </table>
    <p class="h-note h-conf">أقرّ أنا الموظف المذكور أعلاه باستلام مبلغ السلفة، وأوافق على استقطاع أقساطها من راتبي في مواعيدها ما لم تُسدَّد نقداً.</p>
HTML;

        $body .= "\n".$this->hSigs([['الموظف المستلم', '{{customer.name}}'], ['المحاسب'], ['المعتمِد', '{{advance.approver}}']])
            ."\n".$this->hFoot('سند سلفة — مستند داخلي');

        return $this->hWrap('hr-voucher', $body);
    }

    private function hrCommissionA4(): string
    {
        $head = $this->hHead([
            $this->hKv('رقم السند', '<span class="h-ltr">{{document.number}}</span>'),
            $this->hKv('تاريخ الصرف', '{{commission.paid_date}}', true),
            $this->hKv('نوع العمولة', '{{commission.period_type}}', true),
        ]);

        $details = implode("\n", [
            $this->hKv('الهدف', '{{commission.target}}', true),
            $this->hKv('قيمة الهدف', '{{commission.target_value}}', true),
            $this->hKv('الفترة', '{{commission.period}}', true),
            $this->hKv('طريقة الصرف', '{{commission.method}}', true),
            $this->hKv('رقم القيد', '<span class="h-ltr">{{commission.journal_number}}</span>', '!empty(commission.journal_number)'),
            $this->hKv('حالة القيد', '{{commission.journal_status}}', true),
            $this->hKv('صرفها', '{{commission.settled_by}}', true),
        ]);

        $body = $head."\n".$this->hVoucherEmployee()."\n".<<<HTML
    <div class="h-amount">
        <div class="h-net-label hr-serif">مبلغ العمولة</div>
        <div class="h-net-amount"><span class="h-ltr">{{commission.amount}}</span> <small>{{business.currency}}</small></div>
        @if(!empty(commission.amount_words))
        <div class="h-net-words">{{commission.amount_words}}</div>
        @endif
    </div>
{$this->hSection('تفاصيل العمولة')}
    <div class="h-grid2">
$details
    </div>
    @if(!empty(document.notes))
    <p class="h-note"><strong>البيان:</strong> {{document.notes}}</p>
    @endif
    <p class="h-note h-conf">عمولةٌ محسوبة من إنجاز الهدف وشرائحه في إعدادات الأهداف، وأقرّ باستلامها كاملةً.</p>
HTML;

        $body .= "\n".$this->hSigs([['الموظف المستلم', '{{customer.name}}'], ['المحاسب'], ['المعتمِد']])
            ."\n".$this->hFoot('سند صرف عمولة — مستند داخلي');

        return $this->hWrap('hr-voucher', $body);
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                           الأنماط                            ║
    // ╚══════════════════════════════════════════════════════════════╝

    private function hrA4Styles(): string
    {
        return <<<'CSS'
/* ═════════════ تصميم الموارد البشرية — A4 ═════════════
   الألوان: --primary-color للخطوط والعناوين، --secondary-color لونٌ فاتح للخلفيات. */
@page { @bottom-center { content: "صفحة " counter(page) " من " counter(pages); font-size: 8pt; color: #6b7280; } }
.hr-doc { color: #1f2937; line-height: 1.6;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.hr-doc * { box-sizing: border-box; }
.hr-doc p { margin: 0; }
.hr-doc table { width: 100%; border-collapse: collapse; }
.hr-doc img { max-width: 100%; }
/* الخطّ النسخيّ للعناوين — يغلب قاعدةَ المصيّر `.document-a4.document-a4 div { font-family … !important }` */
.hr-doc.hr-doc .hr-serif, .hr-doc.hr-doc .hr-serif * { font-family: 'Amiri', 'Traditional Arabic', 'Times New Roman', serif !important; }
.h-ltr { direction: ltr; unicode-bidi: isolate; display: inline-block; }
.h-ltr-cell { direction: ltr; unicode-bidi: isolate; }

/* ───── الترويسة ───── */
.h-top { display: grid; grid-template-columns: 1.25fr 1fr 1.1fr; gap: 1.2em; align-items: center;
    border-top: 3px double var(--primary-color); padding-top: .9em; }
.h-org { display: flex; align-items: center; gap: .8em; min-width: 0; }
.h-logo { max-height: 4.4em; max-width: 7em; object-fit: contain; }
.h-org-name { font-size: 1.55em; font-weight: 700; color: var(--primary-color); line-height: 1.3; }
.h-org-id { font-size: .8em; color: #4b5563; }
.h-title-block { text-align: center; }
.h-title { font-size: 2.2em; font-weight: 700; color: var(--primary-color); line-height: 1.2; }
.h-title-en { font-size: .78em; letter-spacing: .22em; text-transform: uppercase; color: #6b7280; margin-top: .2em; }
.h-meta { border-inline-start: 1px solid #d1d5db; padding-inline-start: 1em; font-size: .9em; }
.h-rule { height: 0; border-top: 2px solid var(--primary-color); border-bottom: 1px solid var(--primary-color);
    padding-top: 2px; margin: .8em 0 1.1em; }

/* ───── تسمية : قيمة ───── */
.h-kv { display: flex; gap: .5em; padding: .18em 0; }
.h-k { flex: 0 0 42%; color: #6b7280; }
.h-v { flex: 1; font-weight: 600; color: #111827; word-break: break-word; }
.h-meta .h-k { flex-basis: 45%; }
.h-grid2 { display: grid; grid-template-columns: 1fr 1fr; column-gap: 2.2em; margin-bottom: .6em; }
.h-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); column-gap: 1.6em;
    background: var(--secondary-color); border-radius: .4em; padding: .6em 1em; margin-bottom: 1em; }
.h-grid3 .h-kv { flex-direction: column; gap: 0; }
.h-grid3 .h-k { font-size: .8em; }

/* ───── الأقسام والنصوص ───── */
.h-sec { display: flex; align-items: baseline; gap: .6em; margin: 1.1em 0 .5em;
    border-bottom: 1px solid #d1d5db; padding-bottom: .25em; page-break-after: avoid; }
.h-sec-num { font-size: .8em; font-weight: 700; color: #fff; background: var(--primary-color);
    padding: .1em .7em; border-radius: 1em; white-space: nowrap; }
.h-sec-title { font-size: 1.28em; font-weight: 700; color: var(--primary-color); }
.h-lead { margin: .5em 0 .7em; font-size: 1em; }
.h-text { text-align: justify; line-height: 1.95; margin-bottom: .4em; }
.h-pre { white-space: pre-line; }
.h-words { margin: .5em 0; padding: .45em .9em; border-inline-start: 3px solid var(--primary-color);
    background: var(--secondary-color); font-weight: 600; font-size: .95em; }
.h-note { font-size: .88em; color: #374151; margin-top: .6em; line-height: 1.8; }
.h-conf { color: #6b7280; font-size: .8em; }
.h-muted { color: #9ca3af; text-align: center; }

/* ───── الطرفان (العقد) ───── */
.h-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2em; margin-bottom: .6em; }
.h-party { border: 1px solid #d1d5db; border-top: 3px solid var(--primary-color); border-radius: .3em; padding: .6em .9em; }
.h-party-head { display: flex; align-items: baseline; gap: .5em; margin-bottom: .35em; }
.h-party-head span { font-size: 1.25em; font-weight: 700; color: var(--primary-color); }
.h-party-head small { color: #6b7280; font-size: .8em; }

/* ───── الجداول ───── */
.hr-doc .h-table { margin: .3em 0 .6em; font-size: .95em; }
.h-table th { background: var(--secondary-color); color: var(--primary-color); font-weight: 700;
    border-top: 1.5px solid var(--primary-color); border-bottom: 1.5px solid var(--primary-color);
    padding: .45em .5em; text-align: center; white-space: nowrap; }
.h-table td { padding: .4em .5em; border-bottom: 1px solid #e5e7eb; text-align: center; }
.h-table .h-name { text-align: start; }
.h-table .h-seq { width: 2.6em; color: #6b7280; }
.h-num { font-variant-numeric: tabular-nums; white-space: nowrap; direction: ltr; unicode-bidi: isolate; }
.h-table tfoot td { font-weight: 700; border-bottom: 1px solid #d1d5db; text-align: start; }
.h-table tfoot td.h-num { text-align: center; }
.h-table tfoot tr:first-child td { border-top: 1.5px solid var(--primary-color); }
.h-table tr.h-net-row td { background: var(--secondary-color); color: var(--primary-color); font-size: 1.08em;
    border-bottom: 2px solid var(--primary-color); }
.h-table small { font-size: .7em; font-weight: 500; }

/* ───── قسيمة الراتب ───── */
.h-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 1.4em; margin-bottom: 1em; }
.h-col { border: 1px solid #d1d5db; border-radius: .35em; overflow: hidden; }
.h-col-head { background: var(--secondary-color); color: var(--primary-color); font-weight: 700; font-size: 1.15em;
    padding: .35em .9em; border-bottom: 1.5px solid var(--primary-color); }
.h-ded .h-col-head { color: #9f1239; border-bottom-color: #9f1239; }
.h-lines td { padding: .38em .9em; border-bottom: 1px dotted #d1d5db; }
.h-lines td.h-num { text-align: end; }
.h-lines tfoot td { font-weight: 800; border-top: 1.5px solid #9ca3af; border-bottom: 0; background: #fafafa; }
.h-net, .h-amount { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: .2em 1.4em;
    border: 1.5px solid var(--primary-color); border-radius: .4em; padding: .7em 1.2em; margin: .4em 0 .8em;
    background: var(--secondary-color); page-break-inside: avoid; }
.h-net-label { font-size: 1.3em; font-weight: 700; color: var(--primary-color); }
.h-net-amount { font-size: 1.9em; font-weight: 800; color: var(--primary-color); text-align: end; }
.h-net-amount small { font-size: .45em; font-weight: 600; }
.h-net-words { grid-column: 1 / -1; font-size: .92em; color: #374151; border-top: 1px dashed #9ca3af; padding-top: .35em; }

/* ───── مسير الرواتب ───── */
.h-chips { display: flex; flex-wrap: wrap; gap: .5em 1.4em; margin-bottom: .7em; font-size: .92em; }
.h-chip { display: flex; gap: .4em; }
.h-chip .h-k::after { content: ":"; }
.h-chip .h-k, .h-chip .h-v { flex: none; white-space: nowrap; }
.hr-payroll .h-sigs { margin: 1em 0 .4em; }
.hr-payroll .h-sig-line { height: 2.2em; }
.hr-payroll .h-foot { margin-top: .5em; }
.h-sheet th { font-size: .92em; }
.h-sheet td { padding: .35em .4em; }
.h-sheet tbody tr:nth-child(even) td { background: #f9fafb; }
.h-sheet .h-net-col { background: var(--secondary-color); font-weight: 700; color: var(--primary-color); }
.h-sheet tbody tr:nth-child(even) td.h-net-col { background: var(--secondary-color); }
.h-sheet .h-signcol { width: 8em; }
.h-sheet tbody td.h-signcol { border-bottom: 1px dotted #9ca3af; }
.h-sheet tfoot td { background: #f3f4f6; }
.h-sheet thead { display: table-header-group; }
.h-sheet tr { page-break-inside: avoid; }
.h-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1em; margin: .8em 0; page-break-inside: avoid; }
.h-card { border: 1px solid #d1d5db; border-top: 3px solid var(--primary-color); border-radius: .3em;
    padding: .5em .9em; display: flex; flex-direction: column; gap: .15em; }
.h-card span { font-size: .82em; color: #6b7280; }
.h-card strong { font-size: 1.35em; color: #111827; font-variant-numeric: tabular-nums; }
.h-card-main { background: var(--secondary-color); }
.h-card-main strong { color: var(--primary-color); }
.h-card small { font-size: .55em; font-weight: 500; }

/* ───── التواقيع والتذييل ───── */
.h-sigs { display: flex; gap: 2em; margin: 1.6em 0 .8em; page-break-inside: avoid; }
.h-sig { flex: 1; }
.h-sig-title { font-weight: 700; color: var(--primary-color); border-bottom: 1px solid #d1d5db; padding-bottom: .2em; margin-bottom: .4em; }
.h-sig-name { font-size: .9em; min-height: 1.5em; }
.h-dots { color: #9ca3af; }
.h-sig-line { height: 3em; border-bottom: 1px solid #6b7280; position: relative; margin-bottom: .3em; }
.h-sig-line span { position: absolute; bottom: .2em; inset-inline-start: 0; font-size: .75em; color: #9ca3af; }
.h-sig-date { font-size: .78em; color: #6b7280; }
.h-stamp { width: 7em; height: 7em; border: 1.5px dashed #9ca3af; border-radius: 50%; margin: .8em auto 0;
    display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: .85em; }
.h-foot { display: flex; justify-content: space-between; gap: 1em; border-top: 1px solid var(--primary-color);
    margin-top: 1em; padding-top: .35em; font-size: .75em; color: #6b7280; }
CSS;
    }

    private function hrThermalStyles(): string
    {
        return <<<'CSS'
/* ═════════════ تصميم الموارد البشرية — إيصال حراري ═════════════ */
.hr-rcpt { color: #000; line-height: 1.5; font-size: 1em; }
.hr-doc.hr-doc .hr-serif { font-family: 'Amiri', 'Traditional Arabic', serif !important; }
.hr-rcpt .h-ltr { direction: ltr; unicode-bidi: isolate; }
.t-org { text-align: center; font-size: 1.3em; font-weight: 700; }
.t-sub { text-align: center; font-size: .78em; }
.t-title { text-align: center; font-size: 1.35em; font-weight: 700; margin: .3em 0; border-top: 1px solid #000; border-bottom: 1px solid #000; }
.t-rule { border-top: 1px dashed #000; margin: .4em 0; }
.t-head { font-weight: 800; margin-top: .5em; border-bottom: 1px solid #000; font-size: .9em; }
.t-kv { display: flex; justify-content: space-between; gap: .5em; font-size: .85em; padding: .08em 0; }
.t-kv span:last-child { text-align: end; font-variant-numeric: tabular-nums; }
.t-sum { font-weight: 800; border-top: 1px dotted #000; }
.t-net { display: flex; justify-content: space-between; font-size: 1.2em; font-weight: 800;
    border: 1.5px solid #000; padding: .2em .4em; margin-top: .6em; }
.t-net small { font-size: .6em; }
.t-words { font-size: .78em; text-align: center; margin-top: .3em; }
.t-sig { font-size: .8em; margin-top: 1.2em; }
CSS;
    }
}
