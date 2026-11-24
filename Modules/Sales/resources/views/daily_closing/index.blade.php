{{--
    شاشة الإقفال اليومي.

    **التخطيط أصليّ، والكسوة على نظام المبيعات.**

    نُقلت الشاشة من الموارد البشرية، وأُعيد بناؤها مرّةً بناءً كاملاً على
    نظام أنماط التقارير فخرجت مكدَّسةً ثقيلة، فرُدَّ التخطيط الأصلي كما هو
    بطلب صاحب النظام. ثم طُلب التصميم مرّةً ثانية — فصار الفصل بين
    الاثنين صريحاً:

      • **ما تغيّر (الكسوة)**: ترويسة الصفحة صارت `sales::components.page-head`
        بعنوانها وأيقونتها ومسارها وإجراءاتها الخمسة، وبطاقةُ التصفية
        وبطاقةُ الجدول والأزرار والشارات والحالة الفارغة صارت أصناف `sl-`،
        ونظامُ الألوان الخاصّ — تدرّجاتٌ بنفسجية وخضراء وبرتقالية مكتوبةٌ
        بالسداسي — رُفع إلى متغيّرات `--app-*`. فما يُضبط في «مظهر النظام»
        يسري على هذه الشاشة كما يسري على جارتها.

      • **ما لم يتغيّر (التخطيط)**: ترتيبُ حقول النموذج وأعمدتُه، وأعمدةُ
        جدول السجلّات الثلاثةَ عشرَ وإجمالياتُه، والنوافذُ الثلاث وسلوكُ
        الحساب التلقائي فيها، ومعرِّفاتُ العناصر التي يقرؤها السكربت.

      • **الكثافة مقصودة**: مسافةُ الجداول في نظام `sl-` مقيسةٌ على جداول
        من ثمانية أعمدة. وهذا الجدول ثلاثةَ عشرَ عموداً، وجدولُ الجرد داخل
        نافذة — فمسافتُهما تُضيَّق في ورقة الأنماط أدناه. وذلك بالضبط ما
        يمنع تكرار «المكدَّسة الثقيلة».

    وما وراء الشاشة من النقل الأول:

      • رمز العملة من «العملات» (`App\Support\CurrencySettings`) بدل «ر.س»
        المكتوبة نصّاً في القالب وفي السكربت — تبديل العملة يسري عليها الآن.
      • مسار الحذف من `route()` بدل «/hr/daily-closing/» المكتوب في السكربت،
        وكان سيكسر الحذف فور تغيّر بادئة المسار — وقد تغيّرت فعلاً.
      • السجلّات الثلاثة، والحسابات من شاشة «الحسابات المحاسبية»، وبناءُ
        التفاصيل الموحَّد في `DailyClosingLedger`.
--}}
@extends('sales::master')

@section('title')
    الإقفال اليومي
@stop

@section('css')
    <style>
        /*
            ما بقي من أنماط هذه الشاشة وحدها.

            كانت الشاشة تحمل نظام ألوانها كاملاً: تدرّجاتٌ بنفسجية وخضراء
            وبرتقالية مكتوبةٌ بالسداسي، وزوايا `20px`، وظلالٌ ثقيلة — فما
            يُضبط في «مظهر النظام» (اللون الأساسي، والخطّ، واستدارة الحواف،
            والوضع الليلي) لا يسري عليها، وتبقى وحدها على مظهرٍ لا يشبه
            جارتها في القائمة.

            كلُّ ما له نظيرٌ في نظام `sl-` رُفع إلى هناك — الترويسة
            والبطاقات والأزرار والجداول والشارات والحالة الفارغة. وما بقي
            هنا لا نظير له: صناديق الإجماليات، وترويسة النوافذ، وجدول
            الجرد، ونتائج الإقفال الجماعي. أُعيدت صياغته على متغيّرات
            `--app-*` نفسِها، فلا لون مثبَّت إلّا احتياطياً بعد الفاصلة.

            والكثافة مقصودة: جدول السجلّات أربعةَ عشرَ عموداً، وجدول الجرد
            داخل نافذة. فمسافةُ الجداول العامّة — وهي مقيسةٌ على جداول من
            ثمانية أعمدة — تُضيَّق لهذين وحدهما بدل أن تُترك فتخرج الشاشة
            عريضةً مكدَّسة.
        */

        /* كان معرَّفاً داخل وسم `<script>` فلا يقرؤه المتصفّح أنماطاً —
           ومؤشّرات التحميل الأربعة في الشاشة كلُّها تدور عليه، فلم يدر
           منها شيء */
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* ═══════════════ صناديق الإجماليات ═══════════════ */

        .total-box {
            --dc-tone: var(--app-primary, #7367f0);
            background: color-mix(in srgb, var(--dc-tone) 8%, var(--app-surface, #fff));
            border: 1px solid color-mix(in srgb, var(--dc-tone) 32%, transparent);
            border-radius: calc(var(--app-radius, 6px) + 4px);
            padding: .7rem;
            text-align: center;
        }
        .total-box .total-label {
            font-size: .78rem; font-weight: 600;
            color: var(--app-muted, #6e6b7b);
        }
        .total-box .total-value {
            font-size: 1.2rem; font-weight: 800; line-height: 1.3;
            color: var(--dc-tone);
            font-variant-numeric: tabular-nums;
        }

        /* ثلاثةُ ألوانٍ دلالية بدل ستّةَ عشرَ لوناً سداسياً كانت مكتوبةً
           في `style=` داخل الوسم نفسِه */
        .total-box--success { --dc-tone: var(--app-success, #28c76f); }
        .total-box--info    { --dc-tone: var(--app-info, #00cfe8); }
        .total-box--warning { --dc-tone: var(--app-warning, #ff9f43); }
        .total-box--danger  { --dc-tone: var(--app-danger, #ea5455); }

        /* ═══════════════ ترويسة النوافذ ═══════════════ */

        .closing-form-modal .modal-content {
            border: 1px solid var(--sl-line, var(--app-border, #e4e7ec));
            border-radius: calc(var(--app-radius, 6px) + 6px);
            overflow: hidden;
        }

        .closing-form-modal .form-header {
            --dc-head: var(--app-primary, #7367f0);
            position: relative;
            background: var(--dc-head);
            color: var(--app-on-primary, #fff);
            /* شريطٌ من سطرٍ واحد: كانت الترويسة أيقونةً كبيرة فوق عنوانٍ فوق
               وصف — نحو ١١٠px قبل أوّل حقل */
            padding: .55rem 1rem;
            padding-inline-start: 3rem;
            display: flex; align-items: center; flex-wrap: wrap; gap: .1rem .6rem;
            text-align: start;
        }
        /* النغمة تُكتب بنفس تخصيص القاعدة التي تضبط `--dc-head` أعلاه
           (`.closing-form-modal .form-header`) وإلّا غلبتها الأساسية:
           المتغيّراتُ المخصَّصة تتبع التتالي كبقيّة الخصائص، فالصنفُ
           المفرد يخسر أمام صنفين مهما تأخّر في الملفّ */
        .closing-form-modal .form-header--success { --dc-head: var(--app-success, #28c76f); }
        .closing-form-modal .form-header--warning { --dc-head: var(--app-warning, #ff9f43); }

        .closing-form-modal .form-header > i { font-size: 1.05rem; }
        .closing-form-modal .form-header .modal-title { font-size: .95rem; font-weight: 700; margin: 0; }
        .closing-form-modal .form-header p { font-size: .74rem; opacity: .85; flex-basis: 100%; }

        /* ═══════════════ النوافذ المضغوطة ═══════════════
           نافذةُ الإقفال اليدوي كانت أطولَ من الشاشة مرّتين: حقلان في
           الصفّ، وتلميحٌ تحت كلّ حقل، وفاصلٌ بين كلّ قسمين، وصناديقُ
           إجمالياتٍ بارتفاع ٧٠px. الآن ثلاثةُ حقولٍ في الصفّ، والتلميحُ في
           `title` التسمية، والجسمُ يُمرَّر داخل النافذة فيبقى زرُّ التأكيد
           ظاهراً. */
        .closing-form-modal .modal-body {
            padding: .75rem 1rem !important;
            max-height: calc(100vh - 190px); overflow-y: auto;
        }
        .closing-form-modal .modal-footer { padding: .5rem 1rem; }
        .closing-form-modal .form-group { margin-bottom: .55rem; }
        .closing-form-modal .form-group > label { margin-bottom: .2rem; font-size: .8rem; }
        .closing-form-modal hr { margin: .5rem 0; }
        .closing-form-modal h6 { font-size: .85rem; margin: .1rem 0 .4rem; }

        .closing-form-modal .modal-body .form-control,
        .closing-form-modal .modal-body .input-group-text { height: 34px; padding-block: 0; font-size: .85rem; }

        .closing-form-modal .total-box {
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            padding: .35rem .7rem; text-align: start;
        }
        .closing-form-modal .total-box .total-value { font-size: .95rem; }
        .closing-form-modal .row > [class*="col-"] > .total-box { height: 100%; }

        .closing-form-modal .stock-table { max-height: 210px; }
        .closing-form-modal .dc-visits .dc-visits__list { max-height: 150px; }
        .closing-form-modal .dc-visits .table th,
        .closing-form-modal .dc-visits .table td { padding: .3rem .45rem; font-size: .78rem; }

        /* زرُّ الإغلاق: كان يحمل عشرَ خصائصَ في `style=` مكرّرةً في ثلاث
           نوافذ. والجهة `inline-start` لا `left`، فينقلب مع اتجاه اللغة */
        .closing-form-modal .form-header .close {
            position: absolute; top: 50%; transform: translateY(-50%); inset-inline-start: .8rem;
            width: 28px; height: 28px; padding: 0; margin: 0;
            display: flex; align-items: center; justify-content: center;
            border: 0; border-radius: 50%;
            background: rgba(0, 0, 0, .18);
            color: var(--app-on-primary, #fff);
            font-size: 1.3rem; line-height: 1; opacity: 1;
            cursor: pointer; transition: background .15s ease;
        }
        .closing-form-modal .form-header .close:hover { background: rgba(0, 0, 0, .32); }

        /* ═══════════════ جدول الجرد داخل النوافذ ═══════════════ */

        .dc-visits .dc-visits__list { max-height: 200px; overflow-y: auto; }
        .dc-visits .dc-visits__list .table { margin-bottom: 0; }
        .stock-table { max-height: 350px; overflow-y: auto; }
        .stock-table table { margin-bottom: 0; }
        .stock-table .form-control { text-align: center; }

        /* أضيقُ من مسافة الجداول العامّة: جدولٌ داخل نافذة لا شاشةٌ كاملة */
        .sl-scope .stock-table .table th,
        .sl-scope .stock-table .table td { padding: .4rem .55rem; font-size: .8rem; }

        .stock-total-row {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12)) !important;
            font-weight: 700;
        }
        .stock-loading {
            text-align: center; padding: 1.25rem;
            color: var(--app-primary, #7367f0);
        }

        /* ═══════════════ جدول السجلّات ═══════════════ */

        /* أربعةَ عشرَ عموداً: مسافةُ الجداول العامّة تدفعه إلى تمريرٍ أفقي */
        .sl-scope #closingTable th,
        .sl-scope #closingTable td { padding: .6rem .7rem; font-size: .82rem; white-space: nowrap; }

        /* ═══════════════ نتائج الإقفال الجماعي ═══════════════ */

        .bulk-summary-box { display: flex; gap: .75rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .bulk-summary-box .bs-card {
            --dc-tone: var(--app-muted, #6e6b7b);
            flex: 1; min-width: 100px; text-align: center;
            padding: .75rem .5rem;
            border-radius: calc(var(--app-radius, 6px) + 4px);
            background: color-mix(in srgb, var(--dc-tone) 8%, var(--app-surface, #fff));
            border: 1px solid color-mix(in srgb, var(--dc-tone) 32%, transparent);
        }
        .bs-card.bs-success { --dc-tone: var(--app-success, #28c76f); }
        .bs-card.bs-skipped { --dc-tone: var(--app-warning, #ff9f43); }
        .bs-card.bs-failed  { --dc-tone: var(--app-danger, #ea5455); }
        .bs-card .bs-num {
            font-size: 1.5rem; font-weight: 800; line-height: 1.2;
            color: var(--dc-tone); font-variant-numeric: tabular-nums;
        }
        .bs-card .bs-lbl { font-size: .74rem; font-weight: 600; color: var(--app-muted, #6e6b7b); }

        .bulk-results-list { max-height: 400px; overflow-y: auto; padding: .25rem; }

        .bulk-result-item {
            --dc-tone: var(--app-muted, #6e6b7b);
            display: flex; align-items: center; justify-content: space-between;
            gap: .5rem;
            padding: .5rem .75rem; margin-bottom: .4rem;
            border-radius: var(--app-radius, 6px);
            background: color-mix(in srgb, var(--dc-tone) 7%, var(--app-surface, #fff));
            border-inline-start: 4px solid var(--dc-tone);
            font-size: .85rem;
        }
        .bulk-result-item.result-success { --dc-tone: var(--app-success, #28c76f); }
        .bulk-result-item.result-skipped { --dc-tone: var(--app-warning, #ff9f43); }
        .bulk-result-item.result-failed  { --dc-tone: var(--app-danger, #ea5455); }

        .bulk-result-item .emp-name { font-weight: 700; color: var(--app-text, #4b4b4b); }
        .bulk-result-item .emp-msg { font-size: .78rem; color: var(--app-muted, #6e6b7b); }

        /* الشارة ترث نغمة صفّها، فلا تُكتب الألوان الثلاثة مرّتين */
        .bulk-result-item .emp-badge {
            padding: .1rem .6rem; border-radius: 999px;
            font-size: .72rem; font-weight: 700; white-space: nowrap;
            background: color-mix(in srgb, var(--dc-tone) 18%, transparent);
            color: var(--dc-tone);
        }
    </style>
@endsection

@section('content')
@php
    $cur = \App\Support\CurrencySettings::symbol();

    /* بوّابات الأزرار — صلاحيةٌ لكلّ فعل، لا اسمَ دور.
       `$seesAll` نطاقٌ لا فعل: يقرّر ما يُعرَض من صفوف وأعمدة (إقفالات
       الجميع أم إقفالاتي)، بينما هذه الأربع تقرّر ما يُسمح بفعله. وكان
       الاثنان مخلوطين في `$isManager` واحد، فزرُّ «إقفال تلقائي» — وهو
       يُقفل يومَ الضاغط نفسِه لا يومَ غيره — كان مخفياً عن الموظّف الذي
       أُنشئ من أجله، وظاهراً للمدير وحده.

       ثم صار لكلّ زرٍّ صلاحيتُه — تسعُ بوّابات لتسعة أفعال. ونافذةُ كلِّ
       زرٍّ تُرسَم معه أو لا تُرسَم، فلا يبقى في الصفحة نموذجٌ لا زرَّ له. */
    $canCreate      = hasPermission('sales.daily_closing.create');       // إقفال يدوي
    $canAutoClose   = hasPermission('sales.daily_closing.auto_close');   // إقفال تلقائي
    $canReview      = hasPermission('sales.daily_closing.review');       // مراجعة ومطابقة النظام
    $canBulk        = hasPermission('sales.daily_closing.bulk');         // إقفال جماعي
    $canBulkReport  = hasPermission('sales.daily_closing.bulk_report');  // تقرير جماعي
    $canPrint       = hasPermission('sales.daily_closing.print');        // طباعة السطر
    $canPrintReport = hasPermission('sales.daily_closing.print_report'); // طباعة PDF
    $canDelete      = hasPermission('sales.daily_closing.delete');       // حذف

    // الإقفال التلقائي صار زرّاً واحداً يفتح نافذة المراجعة — يكفيه أيٌّ من الصلاحيتين
    $canAutoCloseAny = $canAutoClose || $canReview;
@endphp
    {{-- نظام تصميم شاشات المبيعات — مرفوعٌ إلى أوّل القسم لأن الترويسة
         والبطاقات والجداول أدناه صارت كلُّها على أصنافه. وحارسُ `@once`
         فيه يمنع تكرار الوسم إن ضمّت الشاشة قالباً يستدعيه أيضاً. --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    {{-- Daily Closing Modal --}}
    @if ($canCreate)
    <div class="modal fade closing-form-modal sl-scope text-left" id="closingModal" tabindex="-1" role="dialog" aria-labelledby="closingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="form-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="feather icon-lock"></i>
                    <h4 class="modal-title text-white" id="closingModalLabel">نموذج الإقفال اليومي</h4>
                    <p class="mb-0">أدخل البيانات الفعلية لمطابقتها مع النظام - لن يتم القبول إلا إذا كانت مطابقة</p>
                </div>
                <form action="{{ route('daily-closing.store') }}" method="POST">
                    @csrf
                    <div class="modal-body p-3">
                        {{-- ثلاثةُ حقولٍ في الصفّ، والتلميحُ في `title` التسمية لا
                             سطراً تحت الحقل. الأسماءُ والمعرِّفات كما كانت — يقرؤها
                             السكربت و`DailyClosingController::store()`. --}}
                        <div class="row">
                            <div class="col-md-4 col-6">
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="feather icon-calendar"></i> تاريخ الإقفال</label>
                                    <input type="date" name="date" class="form-control" value="{{ old('date', $today) }}" required>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="feather icon-shopping-cart"></i> المبيعات</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="reported_sales" class="form-control" placeholder="0.00" value="{{ old('reported_sales') }}" required>
                                        <div class="input-group-append"><span class="input-group-text">{{ $cur }}</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="feather icon-minus-circle"></i> المصروفات</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="reported_expenses" class="form-control" placeholder="0.00" value="{{ old('reported_expenses') }}" required>
                                        <div class="input-group-append"><span class="input-group-text">{{ $cur }}</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 col-6">
                                <div class="form-group">
                                    <label class="font-weight-bold" title="مجموع الفواتير المرتجعة + الإشعارات الدائنة"><i class="feather icon-rotate-ccw"></i> المرتجعات</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="reported_returns" class="form-control" placeholder="0.00" value="{{ old('reported_returns') }}" required>
                                        <div class="input-group-append"><span class="input-group-text">{{ $cur }}</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="form-group">
                                    <label class="font-weight-bold" title="التحصيل من المبيعات النقدية المباشرة"><i class="feather icon-dollar-sign"></i> المباع نقدي</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="reported_cash_sales" id="cash_sales" class="form-control collection-input" placeholder="0.00" value="{{ old('reported_cash_sales') }}" required>
                                        <div class="input-group-append"><span class="input-group-text">{{ $cur }}</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="form-group">
                                    <label class="font-weight-bold" title="التحصيل من سندات القبض (ديون العملاء)"><i class="feather icon-file-text"></i> سندات القبض</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="reported_receipts" id="receipts" class="form-control collection-input" placeholder="0.00" value="{{ old('reported_receipts') }}" required>
                                        <div class="input-group-append"><span class="input-group-text">{{ $cur }}</span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- المرتجع النقدي بندٌ مستقل: كان يُطرح من «المباع نقدي» فيخرج سالباً في يومٍ بلا بيعٍ نقدي --}}
                            <div class="col-md-4 col-6">
                                <div class="form-group">
                                    <label class="font-weight-bold" title="{{ __('sales_daily_closing.fields.cash_refunds_hint') }}"><i class="feather icon-corner-up-left"></i> {{ __('sales_daily_closing.fields.cash_refunds') }}</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" name="reported_cash_refunds" id="cash_refunds" class="form-control collection-input" placeholder="0.00" value="{{ old('reported_cash_refunds', 0) }}" required>
                                        <div class="input-group-append"><span class="input-group-text">{{ $cur }}</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8 col-12">
                                <div class="form-group">
                                    <label class="font-weight-bold d-none d-md-block">&nbsp;</label>
                                    <div class="total-box" title="{{ __('sales_daily_closing.fields.collections_formula') }}">
                                        <div class="total-label">إجمالي التحصيل</div>
                                        <div class="total-value" id="totalCollections">0.00 {{ $cur }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex align-items-center justify-content-between flex-wrap mb-50">
                            <h6 class="text-primary font-weight-bold mb-0"><i class="feather icon-package"></i> جرد المخزون في مستودعك</h6>
                            <div class="total-box">
                                <div class="total-label">الإجمالي</div>
                                <div class="total-value" id="totalStock">0 قطعة</div>
                            </div>
                        </div>
                        <div id="stockProductsContainer">
                            <div class="stock-loading">
                                <i class="feather icon-loader" style="animation: spin 1s linear infinite;"></i>
                                جاري تحميل المنتجات...
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-primary font-weight-bold"><i class="feather icon-map-pin"></i> زيارات اليوم</h6>
                        <div id="closingVisits" class="dc-visits"></div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">
                            تأكيد الإقفال <i class="feather icon-send ml-50"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- System Review Modal --}}
    @if ($canAutoCloseAny)
    <div class="modal fade closing-form-modal sl-scope text-left" id="systemReviewModal" tabindex="-1" role="dialog" aria-labelledby="systemReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="form-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="feather icon-check-square"></i>
                    <h4 class="modal-title text-white" id="systemReviewModalLabel">الإقفال التلقائي</h4>
                    <p class="mb-0">راجع أرقام النظام لليوم ثم أكّد الإقفال</p>
                </div>
                <div class="modal-body p-3">
                    <div class="form-group">
                        <label class="font-weight-bold"><i class="feather icon-calendar"></i> تاريخ الإقفال</label>
                        <input type="date" id="systemReviewDate" class="form-control" value="{{ $today }}" required>
                    </div>
                    <hr>

                    <div id="systemDataLoading" style="display:none; text-align:center; padding:20px;">
                        <i class="feather icon-loader" style="animation: spin 1s linear infinite; font-size: 2rem; color: var(--app-primary, #7367f0)"></i>
                        <p class="mt-2 text-muted">جاري جلب البيانات من النظام...</p>
                    </div>

                    <div id="systemDataResult" style="display:none;">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <div class="total-box total-box--success">
                                    <div class="total-label">المبيعات</div>
                                    <div class="total-value" id="sysReviewSales">0.00 {{ $cur }}</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="total-box total-box--success">
                                    <div class="total-label">المصروفات</div>
                                    <div class="total-value" id="sysReviewExpenses">0.00 {{ $cur }}</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="total-box total-box--success">
                                    <div class="total-label">المرتجعات</div>
                                    <div class="total-value" id="sysReviewReturns">0.00 {{ $cur }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <div class="total-box total-box--info">
                                    <div class="total-label">المباع نقدي</div>
                                    <div class="total-value" id="sysReviewCashSales">0.00 {{ $cur }}</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="total-box total-box--info">
                                    <div class="total-label">سندات القبض</div>
                                    <div class="total-value" id="sysReviewReceipts">0.00 {{ $cur }}</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="total-box total-box--warning">
                                    <div class="total-label">{{ __('sales_daily_closing.fields.cash_refunds') }}</div>
                                    <div class="total-value" id="sysReviewCashRefunds">0.00 {{ $cur }}</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="total-box">
                                    <div class="total-label">إجمالي التحصيل</div>
                                    <div class="total-value" id="sysReviewCollections">0.00 {{ $cur }}</div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-primary font-weight-bold"><i class="feather icon-package"></i> المخزون في مستودعك</h6>
                        <div class="total-box total-box--warning mt-2 mb-2">
                            <div class="total-label">إجمالي كمية المخزون</div>
                            <div class="total-value" id="sysReviewStockTotal">0 قطعة</div>
                        </div>
                        <div id="sysReviewStockProducts" class="stock-table" style="max-height: 250px;"></div>

                        <hr>
                        <h6 class="text-primary font-weight-bold"><i class="feather icon-map-pin"></i> زيارات اليوم</h6>
                        <div id="sysReviewVisits" class="dc-visits"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmSystemClose" style="display:none">
                        تأكيد الإقفال التلقائي <i class="feather icon-send ml-50"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk Auto-Close Results Modal --}}
    @if ($canBulk)
    <div class="modal fade closing-form-modal sl-scope text-left" id="bulkResultsModal" tabindex="-1" role="dialog" aria-labelledby="bulkResultsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="form-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="feather icon-users"></i>
                    <h4 class="modal-title text-white" id="bulkResultsModalLabel">الإقفال الجماعي لجميع الموظفين</h4>
                    <p class="mb-0" id="bulkModalSubtitle">اختر التاريخ لإقفال جميع الموظفين تلقائياً</p>
                </div>
                <div class="modal-body p-3">
                    {{-- Date Selection --}}
                    <div id="bulkDateSection">
                        <div class="form-group">
                            <label class="font-weight-bold"><i class="feather icon-calendar"></i> تاريخ الإقفال الجماعي</label>
                            <input type="date" id="bulkCloseDate" class="form-control" value="{{ $today }}" required>
                        </div>
                    </div>

                    {{-- Loading --}}
                    <div id="bulkLoading" style="display:none; text-align:center; padding:30px;">
                        <i class="feather icon-loader" style="animation: spin 1s linear infinite; font-size: 2.5rem; color: var(--app-primary, #7367f0)"></i>
                        <p class="mt-2 font-weight-bold text-primary">جاري إقفال جميع الموظفين...</p>
                        <p class="text-muted">يرجى الانتظار، قد تستغرق العملية بعض الوقت</p>
                    </div>

                    {{-- Results --}}
                    <div id="bulkResultsContainer" style="display:none;">
                        <div class="bulk-summary-box" id="bulkSummaryBox"></div>
                        <h6 class="font-weight-bold mb-2"><i class="feather icon-list"></i> تفاصيل الإقفال لكل موظف</h6>
                        <div class="bulk-results-list" id="bulkResultsList"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmBulkClose">
                        <i class="feather icon-zap"></i> تنفيذ الإقفال الجماعي
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="content-body sl-scope">

        {{-- الإجراءات تُسجَّل قبل `page-head` — فـ`yieldContent` تقرأ ما
             سُجّل حتى لحظة استدعائها لا ما بعده. وكانت هذه الخمسة محشورةً
             في `breadcrumb-right` بأحجامٍ ومسافاتٍ مكتوبةٍ في `style=`. --}}
        @section('page-actions')
            @if ($canBulk)
                <button type="button" class="btn btn-outline-primary" id="btnBulkAutoClose">
                    <i class="feather icon-users"></i> إقفال جماعي
                </button>
            @endif

            {{-- زرٌّ واحد للإقفال التلقائي. كان اثنين يُرسلان إلى المسار نفسِه
                 (`daily-closing.auto-store`): «إقفال تلقائي» بحقل تاريخٍ في
                 الترويسة يُقفل بلا عرض، و«مراجعة ومطابقة النظام» يعرض أرقام
                 النظام ثم يُقفل. بقي الثاني — يُري الموظفَ ما سيُحفَظ قبل أن
                 يُحفَظ — وتاريخُه في نافذته. --}}
            @if ($canAutoCloseAny)
                <button type="button" class="btn btn-primary" id="btnOpenSystemReview">
                    <i class="feather icon-zap"></i> إقفال تلقائي
                </button>
            @endif

            @if ($canCreate)
                <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#closingModal">
                    <i class="feather icon-edit-3"></i> إقفال يدوي
                </button>
            @endif
        @endsection

        @include('sales::components.page-head', [
            'title' => 'الإقفال اليومي',
            'icon' => 'lock',
            'sub' => 'مطابقة المُدخَل الفعلي مع ما سجّله النظام، يوماً بيوم',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'تقارير المبيعات' => route('salesReports.index'),
                'الإقفال اليومي' => null,
            ],
        ])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-daily-closing.index',
            'open' => false,
            'title' => __('sales_daily_closing.guide.title'),
            'lead' => __('sales_daily_closing.guide.lead'),
            'blocks' => [
                __('sales_daily_closing.guide.what_is.title') => [
                    __('sales_daily_closing.guide.what_is.a'),
                    __('sales_daily_closing.guide.what_is.b'),
                    __('sales_daily_closing.guide.what_is.c'),
                ],
                __('sales_daily_closing.guide.scope.title') => [
                    __('sales_daily_closing.guide.scope.a'),
                    __('sales_daily_closing.guide.scope.b'),
                    __('sales_daily_closing.guide.scope.c'),
                ],
                __('sales_daily_closing.guide.effects.title') => [
                    __('sales_daily_closing.guide.effects.a'),
                    __('sales_daily_closing.guide.effects.b'),
                    __('sales_daily_closing.guide.effects.c'),
                ],
                __('sales_daily_closing.guide.settings.title') => [
                    __('sales_daily_closing.guide.settings.a'),
                    __('sales_daily_closing.guide.settings.b'),
                    __('sales_daily_closing.guide.settings.c'),
                ],
            ],
        ])

        {{-- ══════════ الفلترة ══════════
             نفس هيكل بطاقة الفلترة في بقيّة شاشات المبيعات: ترويسةٌ باسمها
             وشارةُ «فلاتر مفعّلة»، والحقول، وذيلٌ فيه أزرار التنفيذ. وترتيب
             الأعمدة كما كان — ثلاثةُ حقول في صفّ واحد. --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-filter"></i> التصفية
                    </h6>

                    @if (request()->hasAny(['employee_id', 'from_date', 'to_date']))
                        <span class="sl-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form action="{{ route('daily-closing.index') }}" method="GET">
                    <div class="row">
                        @if($seesAll)
                            <div class="col-md-4 col-12">
                                <div class="form-group">
                                    <label>الموظف</label>
                                    <select name="employee_id" class="form-control">
                                        <option value="">-- جميع الموظفين --</option>
                                        @foreach($employees as $emp)
                                            <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="sl-help">إقفالات هذا الموظف وحده.</small>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-4 col-6">
                            <div class="form-group">
                                <label>من تاريخ</label>
                                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="form-group">
                                <label>إلى تاريخ</label>
                                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                                <small class="sl-help">بتاريخ الإقفال، والطرفان مشمولان.</small>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        @if($canPrintReport)
                            <button type="button" id="btnPrintReport" class="btn btn-outline-primary" title="طباعة التقرير كامل PDF">
                                <i class="feather icon-file-text"></i> طباعة PDF
                            </button>
                        @endif
                        @if($canBulkReport)
                            <button type="button" id="btnBulkPrintReport" class="btn btn-outline-primary" title="تقرير طباعة جميع الموظفين بدون تخزين">
                                <i class="feather icon-users"></i> تقرير جماعي
                            </button>
                        @endif
                        <a href="{{ route('daily-closing.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> مسح الفلاتر
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                {{-- تنبيهاتُ النظام بلا تدرّجاتٍ مكتوبة: اللونان يأتيان الآن
                     من «مظهر النظام» كما في بقيّة الشاشات. ورسالةُ الخطأ
                     تبقى متعدّدة الأسطر — تفاوتُ المطابقة يُسرَد بنداً بنداً. --}}
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="feather icon-check-circle mr-50"></i> {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="white-space: pre-line">
                        <i class="feather icon-x-circle mr-50"></i> {!! nl2br(e(session('error'))) !!}
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                @endif

                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-list"></i> سجلّات الإقفال
                            <span class="sl-count">{{ $closings->total() }}</span>
                        </h6>
                    </div>

                    <div class="card-content">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="closingTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>التاريخ</th>
                                            @if($seesAll)
                                                <th>الموظف</th>
                                            @endif
                                            <th>صافي المبيعات</th>
                                            <th>المباع نقدي</th>
                                            <th>سندات القبض</th>
                                            <th>إجمالي التحصيل</th>
                                            <th>المرتجعات</th>
                                            <th>المصروفات</th>
                                            <th>المخزون</th>
                                            <th>الحالة</th>
                                            @if($canPrint)<th>طباعة</th>@endif
                                            @if($canDelete)<th>حذف</th>@endif
                                        </tr>
                                    </thead>
                                    <tbody id="closingTableBody">
                                        @php $counter = ($closings->currentPage() - 1) * $closings->perPage(); @endphp
                                        @forelse($closings as $closing)
                                            @php $counter++; @endphp
                                            <tr>
                                                <td>{{ $counter }}</td>
                                                <td><strong>{{ $closing->date }}</strong></td>
                                                @if($seesAll)
                                                    <td>{{ optional($closing->employee)->name ?? '-' }}</td>
                                                @endif
                                                <td>{{ number_format($closing->reported_sales, 2) }}</td>
                                                <td>{{ number_format($closing->reported_cash_sales, 2) }}</td>
                                                <td>{{ number_format($closing->reported_receipts, 2) }}</td>
                                                <td class="text-primary font-weight-bold">{{ number_format($closing->reported_collections, 2) }}</td>
                                                <td class="text-warning font-weight-bold">
                                                    {{ number_format($closing->reported_returns, 2) }}
                                                    {{-- المرتجع النقدي المصروف — يظهر حين وُجد وحده، وهو مطروحٌ من إجمالي التحصيل --}}
                                                    @if ((float) ($closing->reported_cash_refunds ?? 0) > 0)
                                                        <small class="d-block text-muted font-weight-normal" title="{{ __('sales_daily_closing.fields.cash_refunds_hint') }}">{{ __('sales_daily_closing.fields.cash_refunds') }}: {{ number_format($closing->reported_cash_refunds, 2) }}</small>
                                                    @endif
                                                </td>
                                                <td class="text-danger">{{ number_format($closing->reported_expenses, 2) }}</td>
                                                <td>{{ number_format($closing->reported_stock_quantity, 0) }}</td>
                                                <td><span class="sl-state sl-state--on"><i class="feather icon-check"></i> مطابقة</span></td>
                                                @if($canPrint)
                                                    <td>
                                                        <a href="{{ route('daily-closing.print', $closing->id) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="طباعة التفاصيل">
                                                            <i class="feather icon-printer"></i>
                                                        </a>
                                                    </td>
                                                @endif
                                                @if($canDelete)
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-closing" data-id="{{ $closing->id }}" data-date="{{ $closing->date }}" title="حذف الإقفال">
                                                            <i class="feather icon-trash-2"></i>
                                                        </button>
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                {{-- عشرةُ أعمدةٍ ثابتة، والباقي بحسب النطاق والصلاحية --}}
                                                <td colspan="{{ 10 + (int) $seesAll + (int) $canPrint + (int) $canDelete }}">
                                                    <div class="sl-empty">
                                                        <div class="sl-empty__icon"><i class="feather icon-inbox"></i></div>
                                                        <p class="sl-empty__title">لا يوجد سجلات إقفال حالياً</p>
                                                        <p class="sl-empty__hint">أقفِل يومك من أزرار الترويسة، أو وسّع نطاق التاريخ في التصفية.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if($closings->count() > 0)
                                        <tfoot id="closingTableFoot">
                                            <tr class="table-secondary">
                                                <td colspan="{{ $seesAll ? 3 : 2 }}"><strong>الإجمالي</strong></td>
                                                <td><strong>{{ number_format($closings->sum('reported_sales'), 2) }}</strong></td>
                                                <td><strong>{{ number_format($closings->sum('reported_cash_sales'), 2) }}</strong></td>
                                                <td><strong>{{ number_format($closings->sum('reported_receipts'), 2) }}</strong></td>
                                                <td class="text-primary"><strong>{{ number_format($closings->sum('reported_collections'), 2) }}</strong></td>
                                                <td class="text-warning"><strong>{{ number_format($closings->sum('reported_returns'), 2) }}</strong></td>
                                                <td class="text-danger"><strong>{{ number_format($closings->sum('reported_expenses'), 2) }}</strong></td>
                                                <td><strong>-</strong></td>
                                                <td></td>
                                                @if($canPrint)<td></td>@endif
                                                @if($canDelete)<td></td>@endif
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                            <div class="mt-2 text-center">
                                {{ $closings->appends(request()->query())->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {

            // مسار الحذف ورمز العملة من الخادم — لا مكتوبَين في السكربت
            var DESTROY_URL = @json(route('daily-closing.destroy', ['id' => '__ID__']));

            /** مبلغٌ بالعملة المضبوطة في «العملات» — `window.AppCurrency` من `sales::master` */
            function money(value) {
                return window.AppCurrency
                    ? window.AppCurrency.format(value)
                    : (Number(value) || 0).toFixed(2);
            }
            /**
             * زيارات يوم الإقفال — مخرج `DailyClosingLedger::visits()`.
             * للعرض وحده: لا تدخل في مطابقة الإقفال.
             */
            function renderVisits($container, visits, work) {
                visits = visits || { completed: [], missed: [], completed_count: 0, missed_count: 0, success_count: 0, negative_count: 0, total_duration: null };
                var esc = function (v) { return $('<span>').text(v == null || v === '' ? '—' : v).html(); };

                // دوامُ اليوم: الصافي مقابل المطلوب، والوقتُ عند العملاء وبينهم
                var html = '';
                if (work) {
                    var tone = { matched: 'success', short: 'danger', open: 'warning', no_attendance: 'danger' }[work.state] || 'info';
                    var box = function (label, value, cls) {
                        return '<div class="col-6 col-md-3 mb-2"><div class="total-box total-box--' + (cls || 'info') + '"><div class="total-label">' + label + '</div><div class="total-value">' + esc(value) + '</div></div></div>';
                    };
                    html += '<div class="alert alert-' + tone + ' py-50 mb-2"><i class="feather icon-clock"></i> <strong>دوام اليوم:</strong> ' + esc(work.state_label)
                        + ' — المطلوب ' + esc(work.expected || work.required) + ' · الحضور ' + esc(work.check_in) + ' · الانصراف ' + esc(work.check_out) + '</div>'
                        + '<div class="row">'
                        + box('صافي الدوام', work.net, tone)
                        + box('الاستراحة (المسموح ' + work.break_allowed + ' د)', work.breaks, work.break_minutes > work.break_allowed ? 'danger' : 'info')
                        + box('عند العملاء', work.clients, 'success')
                        + box('بين عميل وآخر', work.travel, 'info')
                        + box('قبل أول زيارة', work.before_first, (work.before_first_minutes || 0) > 60 ? 'warning' : 'info')
                        + box('بعد آخر زيارة', work.after_last, (work.after_last_minutes || 0) > 60 ? 'warning' : 'info')
                        + box('النقص عن المطلوب', work.short_minutes > 0 ? work.short : '—', work.short_minutes > 0 ? 'danger' : 'success')
                        + (work.outside_visits > 0 ? box('زيارات خارج الدوام', work.outside_visits, 'danger') : '')
                        + '</div>';
                }

                // مكتملة / فائتة، ثم نتيجةُ المكتملة: ناجحة (فاتورة أو تحصيل) / سلبية، ومجموعُ الوقت عند العملاء
                html += '<div class="row">'
                    + '<div class="col-6 col-md-3 mb-2"><div class="total-box total-box--success"><div class="total-label">{{ __('sales_daily_closing.fields.visits_completed') }}</div><div class="total-value">' + (visits.completed_count || 0) + '</div></div></div>'
                    + '<div class="col-6 col-md-3 mb-2"><div class="total-box total-box--warning"><div class="total-label">{{ __('sales_daily_closing.fields.visits_missed') }}</div><div class="total-value">' + (visits.missed_count || 0) + '</div></div></div>'
                    + '<div class="col-6 col-md-3 mb-2"><div class="total-box total-box--success"><div class="total-label">{{ __('sales_daily_closing.fields.visits_success') }}</div><div class="total-value">' + (visits.success_count || 0) + '</div></div></div>'
                    + '<div class="col-6 col-md-3 mb-2"><div class="total-box total-box--danger"><div class="total-label">{{ __('sales_daily_closing.fields.visits_negative') }}</div><div class="total-value">' + (visits.negative_count || 0) + '</div></div></div>'
                    + '</div>';

                if (visits.total_duration) {
                    html += '<div class="total-box total-box--info mb-2"><div class="total-label">{{ __('sales_daily_closing.fields.visits_total_duration') }}</div><div class="total-value">' + esc(visits.total_duration) + '</div></div>';
                }

                var outcomeBadge = function (v) {
                    var ok = v.outcome === 'success';
                    return '<span class="badge ' + (ok ? 'badge-success' : 'badge-danger') + '">' + esc(v.outcome_label) + '</span>'
                        + (v.unplanned ? ' <small class="text-muted">{{ __('sales_daily_closing.fields.unplanned_visit') }}</small>' : '');
                };

                var sections = [
                    {
                        key: 'completed',
                        title: 'المكتملة',
                        heads: ['{{ __('sales_daily_closing.fields.completed_at') }}', '{{ __('sales_daily_closing.fields.visit_duration') }}', '{{ __('sales_daily_closing.fields.visit_outcome') }}'],
                        cells: function (v) { return [esc(v.time), esc(v.duration), outcomeBadge(v)]; }
                    },
                    {
                        key: 'missed',
                        title: 'الفائتة',
                        heads: ['ملاحظة'],
                        cells: function (v) { return [esc(v.note)]; }
                    }
                ];

                sections.forEach(function (s) {
                    var rows = visits[s.key] || [];
                    if (!rows.length) {
                        return;
                    }
                    html += '<div class="font-weight-bold small mt-1 mb-50">' + s.title + '</div>';
                    html += '<div class="dc-visits__list"><table class="table table-bordered table-sm"><thead class="thead-light"><tr>'
                        + '<th class="text-center" style="width:6%;">#</th><th>العميل</th><th class="text-center" style="width:14%;">الكود</th>';
                    s.heads.forEach(function (h) { html += '<th class="text-center">' + h + '</th>'; });
                    html += '</tr></thead><tbody>';
                    rows.forEach(function (v, i) {
                        html += '<tr><td class="text-center">' + (i + 1) + '</td><td>' + esc(v.client) + '</td><td class="text-center">' + esc(v.code) + '</td>';
                        s.cells(v).forEach(function (c) { html += '<td class="text-center">' + c + '</td>'; });
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                });

                if (!visits.completed_count && !visits.missed_count) {
                    html += '<div class="alert alert-info text-center mb-0">لا توجد زيارات مخطّطة لهذا اليوم</div>';
                }

                $container.html(html);
            }

            const $cashInput = $('#cash_sales');
            const $receiptsInput = $('#receipts');
            const $refundsInput = $('#cash_refunds');
            const $totalDisplay = $('#totalCollections');

            // إجمالي التحصيل = المباع نقدي + سندات القبض − المرتجع النقدي — كما في `DailyClosingLedger::systemTotals()`
            function updateTotal() {
                const cash = parseFloat($cashInput.val()) || 0;
                const receipts = parseFloat($receiptsInput.val()) || 0;
                const refunds = parseFloat($refundsInput.val()) || 0;
                const total = cash + receipts - refunds;
                $totalDisplay.text(money(total));
                $totalDisplay.fadeOut(100).fadeIn(100);
            }

            $(document).on('input keyup change', '#cash_sales, #receipts, #cash_refunds', function() {
                updateTotal();
            });

            // تحميل منتجات المخزون عند فتح المودال
            function loadStockProducts() {
                var container = $('#stockProductsContainer');
                container.html('<div class="stock-loading"><i class="feather icon-loader" style="animation: spin 1s linear infinite;"></i> جاري تحميل المنتجات...</div>');

                $.ajax({
                    url: '{{ route("daily-closing.stock-products") }}',
                    type: 'GET',
                    data: { date: $('#closingModal input[name="date"]').val() },
                    dataType: 'json',
                    success: function(data) {
                        renderVisits($('#closingVisits'), data.visits, data.work);

                        if (data.products.length === 0) {
                            container.html('<div class="alert alert-info text-center"><i class="feather icon-info"></i> لا توجد منتجات في مستودعك</div>');
                            return;
                        }

                        var html = '<div class="stock-table"><table class="table table-bordered table-sm">';
                        html += '<thead class="thead-light"><tr>';
                        html += '<th class="text-center" style="width:5%;">#</th>';
                        html += '<th>المنتج</th>';
                        html += '<th class="text-center" style="width:25%;">الكمية الفعلية</th>';
                        html += '</tr></thead><tbody>';

                        data.products.forEach(function(product, index) {
                            html += '<tr>';
                            html += '<td class="text-center">' + (index + 1) + '</td>';
                            html += '<td>' + $('<span>').text(product.product_name).html() + '</td>';
                            html += '<td>';
                            html += '<input type="hidden" name="stock_products[' + index + '][product_id]" value="' + product.product_id + '">';
                            html += '<input type="number" step="0.01" name="stock_products[' + index + '][reported_quantity]" class="form-control form-control-sm stock-qty-input text-center" placeholder="0" value="" required>';
                            html += '</td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table></div>';
                        container.html(html);
                        updateStockTotal();
                    },
                    error: function() {
                        container.html('<div class="alert alert-danger text-center"><i class="feather icon-alert-circle"></i> حدث خطأ في تحميل المنتجات</div>');
                    }
                });
            }

            // حساب إجمالي المخزون
            function updateStockTotal() {
                var total = 0;
                $('.stock-qty-input').each(function() {
                    total += parseFloat($(this).val()) || 0;
                });
                $('#totalStock').text(total + ' قطعة');
            }

            $(document).on('input keyup change', '.stock-qty-input', function() {
                updateStockTotal();
            });

            // تغيّر تاريخ الإقفال: تُعاد الزيارات وحدها، فلا تُمسح كمياتُ الجرد المُدخلة
            $(document).on('change', '#closingModal input[name="date"]', function () {
                $.getJSON('{{ route("daily-closing.stock-products") }}', { date: $(this).val() }, function (data) {
                    renderVisits($('#closingVisits'), data.visits, data.work);
                });
            });

            $('#closingModal').on('shown.bs.modal', function () {
                updateTotal();
                loadStockProducts();
            });

            $(document).on('click', '#closingModal .close', function() {
                $('#closingModal').modal('hide');
            });

            $(document).on('click', '#systemReviewModal .close, #systemReviewModal [data-dismiss="modal"]', function() {
                $('#systemReviewModal').modal('hide');
            });

            // طباعة التقرير الكامل PDF
            $('#btnPrintReport').on('click', function () {
                var params = new URLSearchParams();
                @if($seesAll)
                    var empId = $('select[name="employee_id"]').val();
                    if (empId) params.set('employee_id', empId);
                @endif
                var fromDate = $('input[name="from_date"]').val();
                var toDate   = $('input[name="to_date"]').val();
                if (fromDate) params.set('from_date', fromDate);
                if (toDate)   params.set('to_date', toDate);
                var url = '{{ route("daily-closing.print-report") }}';
                if (params.toString()) url += '?' + params.toString();
                window.open(url, '_blank');
            });

            {{-- الخطأ هنا رفضُ مطابقةٍ من الإقفال اليدوي — نافذتُه تُرسَم لمن يملكه وحده --}}
            @if(session('error') && $canCreate)
                $('#closingModal').modal({ backdrop: true, keyboard: true, show: true });
                updateTotal();
                loadStockProducts();
            @endif

            // زر حذف الإقفال
            $(document).on('click', '.btn-delete-closing', function () {
                var btn = $(this);
                var closingId = btn.data('id');
                var closingDate = btn.data('date');

                if (!confirm('هل أنت متأكد من حذف إقفال يوم ' + closingDate + '؟\n\nسيتم حذف الإقفال وجميع تفاصيل المخزون المرتبطة به نهائياً.')) {
                    return;
                }

                btn.prop('disabled', true).html('<i class="feather icon-loader" style="animation: spin 1s linear infinite;"></i>');

                $.ajax({
                    url: DESTROY_URL.replace('__ID__', closingId),
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        }
                    },
                    error: function(xhr) {
                        var msg = 'حدث خطأ غير متوقع';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                        btn.prop('disabled', false).html('<i class="feather icon-trash-2"></i>');
                    }
                });
            });

            // الإقفال التلقائي: تُعرَض أرقام النظام أوّلاً ثم يُؤكَّد الإقفال
            $('#btnOpenSystemReview').on('click', function() {
                $('#systemReviewModal').modal('show');
                const dateVal = $('#systemReviewDate').val();
                if(dateVal) {
                    fetchSystemDataView(dateVal);
                }
            });

            $('#systemReviewDate').on('change', function() {
                const dateVal = $(this).val();
                if(dateVal) {
                    fetchSystemDataView(dateVal);
                }
            });

            function fetchSystemDataView(date) {
                const container = $('#systemDataResult');
                const btnConfirm = $('#btnConfirmSystemClose');
                const loading = $('#systemDataLoading');

                container.hide();
                btnConfirm.hide();
                loading.show();

                $.ajax({
                    url: '{{ route("daily-closing.system-data") }}',
                    type: 'GET',
                    data: { date: date },
                    dataType: 'json',
                    success: function(response) {
                        loading.hide();
                        if(response.success) {
                            const data = response.data;
                            $('#sysReviewSales').text(money(data.sales));
                            $('#sysReviewCashSales').text(money(data.cash_sales));
                            $('#sysReviewReceipts').text(money(data.receipts));
                            $('#sysReviewCashRefunds').text(money(data.cash_refunds || 0));
                            $('#sysReviewReturns').text(money(data.returns));
                            $('#sysReviewExpenses').text(money(data.expenses));
                            $('#sysReviewCollections').text(money(data.collections));
                            $('#sysReviewStockTotal').text(data.stock_total + ' قطعة');

                            // Stock products table
                            if(data.stock_products && data.stock_products.length > 0) {
                                let html = '<table class="table table-bordered table-sm"><thead class="thead-light"><tr><th class="text-center" style="width:5%;">#</th><th>المنتج</th><th class="text-center" style="width:25%;">الكمية بالنظام</th></tr></thead><tbody>';
                                data.stock_products.forEach(function(sp, idx) {
                                    html += '<tr><td class="text-center">' + (idx+1) + '</td><td>' + $('<span>').text(sp.product_name).html() + '</td><td class="text-center">' + sp.system_quantity + '</td></tr>';
                                });
                                html += '</tbody></table>';
                                $('#sysReviewStockProducts').html(html);
                            } else {
                                $('#sysReviewStockProducts').html('<div class="alert alert-info text-center">لا توجد منتجات في مستودعك</div>');
                            }

                            renderVisits($('#sysReviewVisits'), data.visits, data.work);

                            container.show();
                            btnConfirm.show();
                        }
                    },
                    error: function(xhr) {
                        loading.hide();
                        var msg = 'حدث خطأ في جلب البيانات من النظام';
                        if(xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                    }
                });
            }

            $('#btnConfirmSystemClose').on('click', function() {
                var btn = $(this);
                var selectedDate = $('#systemReviewDate').val();

                if (!selectedDate) {
                    alert('الرجاء اختيار تاريخ الإقفال');
                    return;
                }

                if (!confirm('هل أنت متأكد من تأكيد الإقفال والمطابقة ليوم ' + selectedDate + '؟')) {
                    return;
                }

                btn.prop('disabled', true).html('<i class="feather icon-loader" style="animation: spin 1s linear infinite;"></i> جاري الإقفال...');

                $.ajax({
                    url: '{{ route("daily-closing.auto-store") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        date: selectedDate
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        }
                    },
                    error: function(xhr) {
                        var msg = 'حدث خطأ غير متوقع';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                        btn.prop('disabled', false).html('تأكيد الإقفال التلقائي <i class="feather icon-send ml-50"></i>');
                    }
                });
            });
            // ============ تقرير طباعة جماعي ============
            @if($canBulkReport)
            $('#btnBulkPrintReport').on('click', function () {
                var selectedDate = $('input[name="from_date"]').val();
                // fallback: use today if no date is entered in the filter
                if (!selectedDate) {
                    selectedDate = '{{ $today }}';
                }
                var url = '{{ route("daily-closing.bulk-print-report") }}?date=' + selectedDate;
                window.open(url, '_blank');
            });
            @endif

            // ============ الإقفال الجماعي ============
            $('#btnBulkAutoClose').on('click', function () {
                // Reset modal state
                $('#bulkDateSection').show();
                $('#bulkLoading').hide();
                $('#bulkResultsContainer').hide();
                $('#btnConfirmBulkClose').show().prop('disabled', false).html('<i class="feather icon-zap"></i> تنفيذ الإقفال الجماعي');
                $('#bulkModalSubtitle').text('اختر التاريخ لإقفال جميع الموظفين تلقائياً');
                $('#bulkResultsModal').modal('show');
            });

            $('#btnConfirmBulkClose').on('click', function () {
                var btn = $(this);
                var selectedDate = $('#bulkCloseDate').val();

                if (!selectedDate) {
                    alert('الرجاء اختيار تاريخ الإقفال');
                    return;
                }

                if (!confirm('هل أنت متأكد من إقفال جميع الموظفين ليوم ' + selectedDate + '؟\n\nسيتم جلب بيانات كل موظف من النظام وحفظها تلقائياً.')) {
                    return;
                }

                // Show loading
                btn.prop('disabled', true).html('<i class="feather icon-loader" style="animation: spin 1s linear infinite;"></i> جاري التنفيذ...');
                $('#bulkDateSection').hide();
                $('#bulkLoading').show();
                $('#bulkResultsContainer').hide();

                $.ajax({
                    url: '{{ route("daily-closing.bulk-auto-store") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        date: selectedDate
                    },
                    dataType: 'json',
                    timeout: 120000, // 2 minutes timeout
                    success: function(response) {
                        $('#bulkLoading').hide();
                        $('#bulkResultsContainer').show();
                        btn.hide();
                        $('#bulkModalSubtitle').text('نتائج الإقفال الجماعي ليوم ' + selectedDate);

                        var r = response.results;

                        // Summary boxes
                        var summaryHtml = '';
                        summaryHtml += '<div class="bs-card bs-success"><div class="bs-num">' + r.success_count + '</div><div class="bs-lbl">✅ نجح</div></div>';
                        summaryHtml += '<div class="bs-card bs-skipped"><div class="bs-num">' + r.skipped_count + '</div><div class="bs-lbl">⏩ تم تخطيه</div></div>';
                        summaryHtml += '<div class="bs-card bs-failed"><div class="bs-num">' + r.failed_count + '</div><div class="bs-lbl">❌ فشل</div></div>';
                        $('#bulkSummaryBox').html(summaryHtml);

                        // Details list
                        var listHtml = '';
                        r.details.forEach(function(d) {
                            var cls = 'result-' + d.status;
                            var badgeCls = 'badge-' + d.status;
                            var badgeText = d.status === 'success' ? 'نجح' : (d.status === 'skipped' ? 'تم تخطيه' : 'فشل');
                            var extraInfo = '';
                            if (d.status === 'success' && d.sales !== undefined) {
                                extraInfo = ' | المبيعات: ' + d.sales + ' | التحصيل: ' + d.collections;
                            }
                            listHtml += '<div class="bulk-result-item ' + cls + '">';
                            listHtml += '  <div><span class="emp-name">' + $('<span>').text(d.employee).html() + '</span><br><span class="emp-msg">' + $('<span>').text(d.message + extraInfo).html() + '</span></div>';
                            listHtml += '  <span class="emp-badge ' + badgeCls + '">' + badgeText + '</span>';
                            listHtml += '</div>';
                        });
                        $('#bulkResultsList').html(listHtml);
                    },
                    error: function(xhr) {
                        $('#bulkLoading').hide();
                        $('#bulkDateSection').show();
                        var msg = 'حدث خطأ غير متوقع';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                        btn.prop('disabled', false).html('<i class="feather icon-zap"></i> تنفيذ الإقفال الجماعي');
                    }
                });
            });

            $(document).on('click', '#bulkResultsModal .close, #bulkResultsModal [data-dismiss="modal"]', function() {
                // If results were shown, reload the page
                if ($('#bulkResultsContainer').is(':visible')) {
                    window.location.reload();
                }
            });
        });
    </script>
@endsection
