{{-- resources/views/attendance/barcode-scan.blade.php --}}

@extends('hr::master')

@section('title', 'مسح الباركود - الحضور والانصراف')

@section('content')
    @include('hr::attendance._partials.styles')
{{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
     من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
@include('stock::components.page-head', [
    'title' => 'مسح الباركود',
    'crumbs' => ['الرئيسية' => null, 'مسح الباركود' => null],
])

<div class="content-body">

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-barcode-scan',
        'title' => 'ما هذه الشاشة، وكيف تعمل؟',
        'lead' => 'هذه واجهةُ جهاز الفرع: تُفتَح على تابلت أو حاسوبٍ عند الباب، ويمسح عليها الموظفُ بطاقتَه فيُسجَّل حضورُه أو انصرافُه. وما يقع هنا يكتب في سجلّ الحضور مباشرةً، وعليه يُبنى ما يُحسَم من الراتب.',
        'blocks' => [
            'قبل أوّل استعمال: اقرن الجهاز (إلزامي)' => [
                'أدخِل رمزَ الاقتران مرّة واحدة في بداية الجلسة (الشريط أعلى الشاشة). الرمز من «الإعدادات ← نطاقات المرافق والأجهزة». بعدها كل المسحات في هذه الجلسة تعمل بلا إعادة إدخال الرمز.',
                'الاقتران إلزامي قبل أي مسح، ويُعرِّف الجهاز بفرعه ونطاقه. يُطلب الرمز من جديد فقط بعد إغلاق المتصفح أو الضغط على «فكّ الاقتران».',
            ],
            'ما يفعله الموظف' => [
                'يمسح بطاقتَه على القارئ، أو يُدخل رقمَها في حقل الباركود. لا زرَّ «حضور» ولا «انصراف»: النظامُ يستنتج الاتجاه من سجلّ يومه — فمن لم يُسجَّل دخولُه بعد يُقرأ مسحُه حضوراً، ومن سُجِّل دخولُه يُقرأ انصرافاً، ومن أتمّ الاثنين يُردّ بـ«اليوم مكتمل».',
                'عند الانصراف يُقارَن وقتُ المسح بنهاية وردية الموظف: انصرافٌ مبكر يُحتسب دقائقَه، وانصرافٌ بعد نافذة الوردية يُقبل لإغلاق اليوم ويُقصّ إلى نهايتها.',
            ],
            'ما يفحصه النظام قبل القبول' => [
                'ملفُّ الموظف: أن البطاقة تخصّه، وأنها مُفعَّلة، وأنه على رأس العمل، وأن هذه القناة مسموحةٌ له (مقرّ أو ميدان).',
                'الوردية: أيُّ وردِيّةٍ تخصّه في هذا اليوم بعينه — الوردياتُ المخصَّصة أَولى من ورديته الدائمة — ومنها تُحسَب دقائقُ التأخير عند الحضور ودقائقُ الانصراف المبكر عند الخروج.',
                'الموقع: أن الجهاز داخل نطاق الفرع، ومطابقةُ شبكة المقرّ إن ضُبطت. وتُطلب إذنُ الموقع من المتصفّح، فامنحه مرّةً عند فتح الشاشة.',
                'سجلُّ اليوم: لا حضورَ مرّتين، ولا انصرافَ بلا حضور، ولا تسجيلَ حضورٍ في عطلةٍ أسبوعية (مثل الجمعة من الإعدادات) أو عطلةٍ رسمية أو يوم إجازةٍ معتمدة.',
            ],
            'ما يقع بعد القبول' => [
                'يُكتب ختمٌ خام في «سجلات الحضور» — لا يُعدَّل ولا يُحذف أبداً — ويُحدَّث صفُّ اليوم في «أيام الحضور» بوقته وحالته ودقائق تأخيره.',
                'وانصرافٌ يقع بعد نافذة الوردية يُقبَل ليُغلق اليوم لكنه يُقصّ إلى نهايتها، فلا يُحتسب وقتاً إضافياً. والوقتان معاً يظهران في سجل الأختام.',
            ],
            'والمرفوض يُسجَّل أيضاً' => [
                'كلُّ محاولةٍ تُكتب — مقبولةً كانت أو مردودة — بسببها ووقتها وموقعها. فبطاقةٌ مجهولةٌ تتكرّر، أو مسحٌ من خارج النطاق، واقعةٌ تُقرأ في «سجلات الحضور» ولا تضيع.',
                'ولا يُصحَّح وقتٌ من هنا: التصحيحُ مسارُه «طلبات تصحيح الحضور» ثم اعتمادُها، ليبقى للتغيير طالبٌ ومعتمِدٌ وأثرُ تدقيق.',
            ],
            'إن توقّف شيء' => [
                'المؤشّرُ الأخضر في الشريط يعني أن الجهاز متّصلٌ ونبضُه يصل. واترك هذه الشاشةَ مفتوحةً طوال الدوام: انقطاعُها يظهر في «سجل جلسات الحضور» صمتاً، وجهازٌ صامتٌ نصفَ يومٍ يُنتج يوماً يبدو فيه نصفُ الفرع غائباً.',
            ],
        ],
        'facts' => [
            'المسح وحده يسجّل — بلا زرّ حضور/انصراف',
            'الانصراف يُقارَن بنهاية الوردية',
            'الختمُ يُكتب مرّةً ولا يُعدَّل',
            'التصحيحُ بطلبٍ واعتماد لا من هنا',
        ],
    ])

    {{-- هويّةُ الجهاز: أيُّ فرعٍ هذا، وأيُّ نطاقٍ يخدمه. بدونها لا يعرف
         المشغّلُ أين تُنسَب الأختامُ التي يمسحها، ولا يعرف النظامُ أيَّ
         نطاقٍ يُطابق. --}}
    <div class="kiosk-bar mb-3">
        @if($kiosk)
            <div class="kiosk-bar__identity">
                <span class="kiosk-bar__dot" title="الجهاز مقترن"></span>
                <div>
                    <div class="kiosk-bar__name">{{ $kiosk->name }}</div>
                    <div class="kiosk-bar__meta">
                        {{ $kiosk->branch->name ?? 'بلا فرع' }}
                        @if($geofence)
                            · نطاق «{{ $geofence->name }}» ({{ $geofence->radius_meters }} م)
                        @else
                            · <span class="text-danger">بلا نطاقٍ مُسنَد</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center" style="gap:14px;">
                {{-- حالةُ النظام: مشغّلُ الفرع يحتاج أن يعرف أن الجهاز
                     يسجّل فعلاً، لا أن يكتشف الانقطاع آخر اليوم. --}}
                <div class="kiosk-status" id="kiosk-status">
                    <span class="kiosk-status__led" id="status-led"></span>
                    <span id="status-text">متصل</span>
                </div>
                <div class="kiosk-status">
                    <span class="kiosk-status__led kiosk-status__led--off" id="cam-led"></span>
                    <span id="cam-text">الكاميرا متوقفة</span>
                </div>
                <form method="POST" action="{{ route('barcode.kiosk.unpair') }}" class="m-0">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm">فكّ الاقتران</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('barcode.kiosk.pair') }}" class="kiosk-bar__pair">
                @csrf
                <span class="kiosk-bar__dot kiosk-bar__dot--idle" title="غير مقترن"></span>
                <label class="m-0 text-danger font-weight-bold">اقتران الجهاز مطلوب قبل أي مسح</label>
                <input type="text" name="pairing_code" class="form-control form-control-sm"
                       placeholder="رمز الاقتران" maxlength="16" style="max-width:160px;text-transform:uppercase;" required autofocus>
                <button class="btn btn-primary btn-sm">اقتران</button>
                @unless($hasKiosks)
                    <small class="text-muted">لا توجد أجهزةٌ مُسجَّلة — أنشئها من «إعدادات الحضور ← نطاقات المرافق».</small>
                @endunless
            </form>
        @endif
    </div>

    @unless($kiosk)
        <div class="alert alert-warning mb-3">
            <strong>الشاشة مقفلة.</strong>
            أدخِل رمز الاقتران أعلاه لفتح الكاميرا والمسح — لا يُقبَل تسجيل حضور من جهاز غير مقترن.
        </div>
    @endunless

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    {{--
        بطاقةُ النتيجة الفورية: تملأ الشاشة ثوانيَ معدودة ثم تنصرف من
        نفسها. لا زرَّ «موافق» — الجهازُ في ممرٍّ يمرّ عليه عشرون موظفاً
        في دقيقة، ونافذةٌ تنتظر ضغطةً تُوقف الطابور.
    --}}
    <div id="result-card" class="rc" style="display:none;">
        <div class="rc__inner">
            <div class="rc__icon" id="rc-icon"></div>
            <img id="rc-photo" class="rc__photo" src="" alt="" style="display:none;">
            <div class="rc__name" id="rc-name"></div>
            <div class="rc__dept" id="rc-dept"></div>
            <div class="rc__title" id="rc-title"></div>
            <div class="rc__time" id="rc-time"></div>
            <div class="rc__detail" id="rc-detail"></div>
        </div>
        <div class="rc__bar"><i id="rc-bar"></i></div>
    </div>

    <!-- Current Time Display -->
    <div class="card mb-3">
        <div class="card-body text-center">
            <h3 class="mb-2">الوقت الحالي</h3>
            <h1 id="current-time" class="display-4 text-primary mb-0"></h1>
            <p id="current-date" class="text-muted"></p>
        </div>
    </div>

    {{-- «رمز المكتب» المتغيّر: الموظفُ يمسحه بجواله من «سجّل حضوري» — يتجدّد كل 30 ثانية،
         فصورتُه المرسلةُ لمن خارج المكتب تنتهي قبل أن تُستعمل --}}
    @if($kiosk && in_array($kiosk->office_qr, ['screen', 'both'], true))
        <div class="card mb-3 office-qr" id="office-qr">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-center" style="gap:24px">
                <div class="office-qr__img" id="office-qr-img"><span class="text-muted">جارٍ تجهيز الرمز…</span></div>
                <div style="max-width:420px">
                    <h3 class="mb-1"><i class="feather icon-smartphone"></i> سجّل حضورك بجوالك</h3>
                    <ol class="mb-1 ps-3" style="line-height:2">
                        <li>افتح «سجّل حضوري» في النظام من جوالك.</li>
                        <li>اضغط «امسح رمز المكتب» ووجّه الكاميرا إلى هذا الرمز.</li>
                        <li>يُسجَّل حضورك، أو انصرافك إن كنت حاضراً.</li>
                    </ol>
                    <small class="text-muted">يتغيّر الرمز تلقائياً كل 30 ثانية — <span id="office-qr-countdown"></span></small>
                </div>
            </div>
        </div>
        <style>
            .office-qr__img { width: 280px; height: 280px; display: flex; align-items: center; justify-content: center;
                background: #fff; border-radius: 12px; border: 1px solid var(--app-border, #e5e7eb); }
            .office-qr__img svg { width: 260px; height: 260px; }
        </style>
        <script>
            (function () {
                const img = document.getElementById('office-qr-img');
                const countdown = document.getElementById('office-qr-countdown');
                let left = 0;
                async function refresh() {
                    try {
                        const r = await fetch(@json(route('barcode.office_code')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        const data = await r.json();
                        if (! data.enabled) { document.getElementById('office-qr').hidden = true; return; }
                        img.innerHTML = data.svg;
                        left = data.refresh_in;
                    } catch (e) {
                        img.innerHTML = '<span class="text-danger">تعذّر تحديث الرمز</span>';
                        left = 5;
                    }
                }
                setInterval(function () {
                    left -= 1;
                    countdown.textContent = 'يتجدّد بعد ' + Math.max(0, left) + ' ثانية';
                    if (left <= 0) { left = 99; refresh(); }
                }, 1000);
                refresh();
            })();
        </script>
    @endif

    <!-- Scanner Section -->
    <div class="row">
        <div class="col-md-8">
            {{--
                شريطُ إحصائيات اليوم. نقطةُ barcode.stats كانت موجودةً وتعمل،
                و loadDashboardStats() تناديها عند فتح الصفحة ثم تكتب النتيجة في
                present-count و late-count و absent-count — وهي عناصرُ لم تكن في
                الصفحة أصلاً، فكان كل فتحٍ للشاشة ينتهي بـ
                «Cannot set properties of null» ولا تظهر إحصائيةٌ واحدة.
            --}}
            <div class="att-stats mb-3">
                <div class="att-stat green">
                    <div class="label">حاضرون اليوم</div>
                    <div class="value" id="present-count">—</div>
                </div>
                <div class="att-stat amber">
                    <div class="label">متأخرون</div>
                    <div class="value" id="late-count">—</div>
                </div>
                <div class="att-stat red">
                    <div class="label">لم يسجّلوا بعد</div>
                    <div class="value" id="absent-count">—</div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">كاميرا مسح الباركود</h6>
                    <div class="float-right">
                        <button id="start-camera" class="btn btn-success btn-sm" @unless($kiosk) disabled title="اقتران الجهاز مطلوب" @endunless>
                            <i class="feather icon-camera"></i> تشغيل الكاميرا
                        </button>
                        <button id="stop-camera" class="btn btn-danger btn-sm" style="display: none;">
                            <i class="feather icon-square"></i> إيقاف الكاميرا
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Camera Preview — يجب أن يكون <video> لـ ZXing لا <div> -->
                    <div id="camera-container" class="text-center">
                        <div id="scanner-placeholder" class="border rounded p-5" style="min-height: 300px; background-color: var(--app-body-bg, #f8f9fa);">
                            <i class="feather icon-camera fa-3x text-muted mb-3"></i>
                            @if($kiosk)
                                <p class="text-muted mb-0">اضغط على "تشغيل الكاميرا" لبدء المسح</p>
                            @else
                                <p class="text-danger mb-0 font-weight-bold">اقتران الجهاز مطلوب قبل تشغيل الكاميرا</p>
                            @endif
                            <p id="camera-hint" class="text-danger small mt-2 mb-0" style="display:none;"></p>
                        </div>
                        <video id="scanner" playsinline muted autoplay
                               style="display:none; width:100%; max-width:500px; height:300px; object-fit:cover; border:2px solid var(--app-info, #007bff); border-radius:8px; background:#000; margin:0 auto;"></video>
                    </div>

                    <!-- Manual Barcode Input -->
                    <div class="mt-3">
                        <label>أو أدخل الباركود يدوياً:</label>
                        <div class="input-group">
                            <input type="text" id="manual-barcode" class="form-control" placeholder="امسح أو اكتب الباركود هنا"
                                   @unless($kiosk) disabled @endunless>
                            <div class="input-group-append">
                                <button id="process-manual" class="btn btn-primary" @unless($kiosk) disabled @endunless>معالجة</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Info Panel -->
        <div class="col-md-4">
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">معلومات الموظف</h6>
                </div>
                <div class="card-body">
                    <div id="employee-info" style="display: none;">
                        <div class="text-center mb-3">
                            <img id="employee-photo" src="" alt="صورة الموظف" class="rounded-circle" width="80" height="80" style="object-fit: cover;">
                        </div>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>الاسم:</strong></td>
                                <td id="employee-name"></td>
                            </tr>
                            <tr>
                                <td><strong>القسم:</strong></td>
                                <td id="employee-department"></td>
                            </tr>
                            <tr>
                                <td><strong>الحالة:</strong></td>
                                <td id="attendance-status"></td>
                            </tr>
                        </table>

                        {{-- الوردية المتوقَّعة: المشغّلُ يرى لماذا سيُحتسب هذا
                             المسحُ تأخيراً قبل أن يضغط، لا بعد أن يشكو الموظف. --}}
                        <div id="shift-panel" class="shift-panel" style="display:none;">
                            <div class="shift-panel__row">
                                <span>بداية الوردية</span><strong id="shift-in">—</strong>
                            </div>
                            <div class="shift-panel__row">
                                <span>نهاية الوردية</span><strong id="shift-out">—</strong>
                            </div>
                            <div class="shift-panel__row">
                                <span>مدّة السماح</span><strong id="shift-grace">—</strong>
                            </div>
                            <div id="shift-flags" class="shift-panel__flags"></div>
                        </div>

                        @if($photoTypes->isNotEmpty())
                            {{-- التقاطُ صور الحضور: الأنواعُ تُعرَّف من «إعدادات الحضور V2 ← أنواع الصور»،
                                 والخادمُ هو من يرفض الناقص — هذه الواجهةُ تجمع فقط. --}}
                            <div id="photo-capture" class="mt-3" style="display:none;">
                                <h6 class="mb-2"><i class="feather icon-camera"></i> صور التسجيل</h6>
                                <video id="photo-stream" autoplay playsinline muted
                                    style="width:100%; max-height:200px; background:#000; border-radius:8px; display:none;"></video>
                                <canvas id="photo-canvas" style="display:none;"></canvas>

                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($photoTypes as $type)
                                        <button type="button" class="btn btn-outline-primary btn-sm photo-shot"
                                            data-type-id="{{ $type->id }}" data-phase="{{ $type->phase }}">
                                            <i class="fa {{ $type->icon ?? 'fa-camera' }} me-1"></i>{{ $type->name }}
                                            @if($type->is_required)<span class="text-danger">*</span>@endif
                                        </button>
                                    @endforeach
                                </div>

                                <div id="photo-thumbs" class="d-flex flex-wrap gap-2 mt-2"></div>
                            </div>
                        @endif

                        <!-- حالة اليوم فقط — لا زرّ حضور/انصراف: المسح يختم مباشرة -->
                        <div class="text-center mt-3">
                            <div id="already-complete" class="alert alert-info" style="display: none;">
                                <i class="feather icon-check-circle"></i> تم إكمال الحضور والانصراف لهذا اليوم
                            </div>
                            <p class="text-muted small mb-0" id="scan-hint">امسح البطاقة لتسجيل الحضور أو الانصراف تلقائياً</p>
                        </div>
                    </div>

                    <div id="no-employee" class="text-center text-muted">
                        <i class="fa fa-user-slash fa-3x mb-3"></i>
                        <p>لم يتم مسح أي باركود بعد</p>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card mt-3">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">آخر العمليات</h6>
                </div>
                <div class="card-body">
                    <div id="recent-activity">
                        <p class="text-muted text-center">لا توجد عمليات حديثة</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mt-3">
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 id="barcode-scans">0</h3>
                    <p>مسح بالباركود</p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<!-- مكتبات الباركود — نثبّت الإصدار لتفادي كسر واجهة API من @latest -->
<script src="https://unpkg.com/@zxing/library@0.21.3/umd/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<style>
/* ═══ بطاقةُ النتيجة الفورية ═══ */
.rc { position:fixed; inset:0; z-index:2000; display:flex; flex-direction:column;
      align-items:center; justify-content:center; text-align:center; color:#fff;
      animation: rc-in .18s ease-out; }
.rc--ok     { background: linear-gradient(160deg, #15803d 0%, #166534 100%); }
.rc--late   { background: linear-gradient(160deg, #b45309 0%, #92400e 100%); }
.rc--out    { background: linear-gradient(160deg, #1d4ed8 0%, #1e3a8a 100%); }
.rc--reject { background: linear-gradient(160deg, #b91c1c 0%, #7f1d1d 100%); }
.rc--done   { background: linear-gradient(160deg, #4b5563 0%, #1f2937 100%); }
@keyframes rc-in { from { opacity:0; transform: scale(.97); } to { opacity:1; transform:none; } }

.rc__inner { max-width:640px; padding:24px; }
.rc__icon  { font-size:4rem; line-height:1; margin-bottom:10px; }
.rc__photo { width:132px; height:132px; border-radius:50%; object-fit:cover;
             border:5px solid rgba(255,255,255,.85); margin-bottom:14px; background:rgba(0,0,0,.2); }
.rc__name  { font-size:2.4rem; font-weight:800; line-height:1.15; }
.rc__dept  { font-size:1rem; opacity:.8; margin-top:2px; }
.rc__title { font-size:1.7rem; font-weight:700; margin-top:18px; }
.rc__time  { font-size:3.4rem; font-weight:800; font-variant-numeric: tabular-nums; letter-spacing:1px; margin-top:4px; }
.rc__detail{ font-size:1.15rem; opacity:.95; margin-top:12px; line-height:1.75; max-width:36rem; margin-left:auto; margin-right:auto; padding:0 12px; }
.rc--reject .rc__detail { font-size:1.25rem; font-weight:600; background:rgba(0,0,0,.18); border-radius:10px; padding:12px 16px; margin-top:16px; }

/* شريطُ العدّ التنازلي — يرى الموظفُ متى تنصرف البطاقة */
.rc__bar { position:absolute; bottom:0; left:0; right:0; height:6px; background:rgba(255,255,255,.22); }
.rc__bar i { display:block; height:100%; width:100%; background:rgba(255,255,255,.85); }

/* مؤشّرُ حالة النظام */
.kiosk-status { display:flex; align-items:center; gap:6px; font-size:.75rem; color: var(--app-muted, #6b7280); }
.kiosk-status__led { width:9px; height:9px; border-radius:50%; background: var(--app-success, #16a34a);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--app-success, #16a34a) 22%, transparent); }
.kiosk-status__led--off  { background: var(--app-muted, #9ca3af); box-shadow:0 0 0 3px rgba(156,163,175,.22); }
.kiosk-status__led--down { background: var(--app-danger, #dc2626); box-shadow:0 0 0 3px rgba(220,38,38,.22); }

/* شريطُ هويّة الجهاز */
.kiosk-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
    background: var(--app-surface, #fff); border:1px solid var(--app-border, #e5e7eb); border-radius:10px; padding:10px 16px; }
.kiosk-bar__identity { display:flex; align-items:center; gap:10px; }
.kiosk-bar__pair { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:0; }
.kiosk-bar__name { font-weight:700; }
.kiosk-bar__meta { font-size:.78rem; color: var(--app-muted, #6b7280); }
.kiosk-bar__dot { width:10px; height:10px; border-radius:50%; background: var(--app-success, #16a34a);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--app-success, #16a34a) 22%, transparent); }
.kiosk-bar__dot--idle { background: var(--app-warning, #d97706);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--app-warning, #d97706) 22%, transparent); }

/* لوحةُ الوردية */
.shift-panel { border:1px dashed var(--app-border, #e5e7eb); border-radius:8px; padding:10px 12px; margin-top:10px; font-size:.82rem; }
.shift-panel__row { display:flex; justify-content:space-between; padding:3px 0; }
.shift-panel__row span { color: var(--app-muted, #6b7280); }
.shift-panel__flags { margin-top:6px; display:flex; flex-wrap:wrap; gap:4px; }
.shift-panel__flags .badge { font-size:.68rem; }

.scanner-overlay {
    position: relative;
}

.scanner-overlay::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 200px;
    height: 2px;
    background: #ff0000;
    transform: translate(-50%, -50%);
    animation: scan-line 2s infinite;
}

@keyframes scan-line {
    0%, 100% { opacity: 0; }
    50% { opacity: 1; }
}

.employee-card {
    border: 2px solid var(--app-success, #28a745);
    background: linear-gradient(135deg, var(--app-body-bg, #f8f9fa) 0%, var(--app-border, #e9ecef) 100%);
}

.btn-scan-action {
    font-size: 1.1rem;
    padding: 12px 24px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.btn-scan-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const kioskPaired = @json((bool) $kiosk);
    let codeReader = null;
    let currentEmployee = null;
    let scanning = false;
    let scanControls = null;
    // الباركودُ الذي عُرِّف به الموظف — يُرسَل مع الختم ليُطابقه الخادم.
    let identifiedBarcode = null;
    let lastScannedCode = null;
    let lastScanAt = 0;

    // عناصر DOM
    const startCameraBtn = document.getElementById('start-camera');
    const stopCameraBtn = document.getElementById('stop-camera');
    const scannerVideo = document.getElementById('scanner');
    const scannerPlaceholder = document.getElementById('scanner-placeholder');
    const cameraHint = document.getElementById('camera-hint');
    const manualBarcodeInput = document.getElementById('manual-barcode');
    const processManualBtn = document.getElementById('process-manual');
    const employeeInfo = document.getElementById('employee-info');
    const noEmployee = document.getElementById('no-employee');
    const alreadyComplete = document.getElementById('already-complete');
    const scanHint = document.getElementById('scan-hint');

    // تنسيق الوقت حسب جهاز المستخدم (12/24) بدل فرض 21:35
    function formatDeviceTime(value, withSeconds = false) {
        if (value == null || value === '') return '';
        let date;
        if (value instanceof Date) {
            date = value;
        } else {
            const raw = String(value).trim();
            // أرقام الساعة فقط (من الخادم)؛ إن كان منسّقاً مسبقاً نعيده كما هو
            const match = raw.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
            if (! match) return raw;
            date = new Date();
            date.setHours(Number(match[1]), Number(match[2]), Number(match[3] || 0), 0);
        }
        const opts = { hour: 'numeric', minute: '2-digit' };
        if (withSeconds) opts.second = '2-digit';
        return date.toLocaleTimeString(undefined, opts);
    }

    function updateTime() {
        const now = new Date();
        document.getElementById('current-time').textContent = formatDeviceTime(now, true);
        document.getElementById('current-date').textContent = now.toLocaleDateString(undefined, {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }

    updateTime();
    setInterval(updateTime, 1000);

    loadDashboardStats();
    setInterval(loadDashboardStats, 30000);

    startCameraBtn.addEventListener('click', function() { startScanning(); });
    stopCameraBtn.addEventListener('click', function() { stopScanning(); });

    processManualBtn.addEventListener('click', function() {
        if (! requirePairing()) return;
        const barcode = manualBarcodeInput.value.trim();
        if (! barcode) return;
        manualBarcodeInput.value = '';
        punchByScan(barcode);
    });

    manualBarcodeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') processManualBtn.click();
    });

    function requirePairing() {
        if (kioskPaired) return true;
        showCard('reject', {
            icon: '🔒',
            title: 'الجهاز غير مقترن',
            detail: 'أدخِل رمز الاقتران أعلى الشاشة قبل المسح.',
            ms: 4000,
        });
        return false;
    }

    function showCameraHint(msg) {
        if (! cameraHint) return;
        if (! msg) { cameraHint.style.display = 'none'; cameraHint.textContent = ''; return; }
        cameraHint.style.display = 'block';
        cameraHint.textContent = msg;
    }

    /**
     * بدء مسح الباركود عبر كاميرا الجهاز.
     * العنصر المستهدف يجب أن يكون <video> — ZXing يبثّ عليه ثم يفكّ الرمز.
     */
    async function startScanning() {
        if (! requirePairing()) return;
        if (scanning) return;

        if (! window.ZXing || typeof ZXing.BrowserMultiFormatReader !== 'function') {
            showCameraHint('مكتبة المسح لم تُحمَّل — حدّث الصفحة وأعد المحاولة.');
            showError('تعذّر تحميل مكتبة مسح الباركود');
            return;
        }

        if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
            showCameraHint('المتصفح لا يدعم الكاميرا أو الصفحة ليست على HTTPS/localhost.');
            showError('الكاميرا غير مدعومة في هذا المتصفح');
            return;
        }

        showCameraHint('');
        codeReader = new ZXing.BrowserMultiFormatReader();

        try {
            const devices = await codeReader.listVideoInputDevices();
            if (! devices || ! devices.length) {
                showCameraHint('لم يُعثر على كاميرا متصلة بهذا الجهاز.');
                showError('لا توجد كاميرا متاحة');
                codeReader = null;
                return;
            }

            // فضّل الكاميرا الخلفية على الأجهزة المحمولة إن وُجدت
            let deviceId = devices[0].deviceId;
            const back = devices.find(d => /back|rear|environment|خلف|خلفية/i.test(d.label || ''));
            if (back) deviceId = back.deviceId;

            scannerPlaceholder.style.display = 'none';
            scannerVideo.style.display = 'block';
            startCameraBtn.style.display = 'none';
            stopCameraBtn.style.display = 'inline-block';
            scanning = true;
            setCameraLed(true);

            await codeReader.decodeFromVideoDevice(deviceId, scannerVideo, (result, err) => {
                if (result && result.text) {
                    const code = String(result.text).trim();
                    const now = Date.now();
                    // منع تكرار نفس الرمز خلال ثانيتين (الكاميرا تقرأ عشرات المرات)
                    if (code === lastScannedCode && (now - lastScanAt) < 2000) return;
                    lastScannedCode = code;
                    lastScanAt = now;
                    playSuccessSound();
                    vibrateDevice();
                    punchByScan(code);
                    return;
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.warn('خطأ مسح:', err);
                }
            });
        } catch (e) {
            console.error('فشل تشغيل الكاميرا:', e);
            scanning = false;
            setCameraLed(false);
            scannerVideo.style.display = 'none';
            scannerPlaceholder.style.display = 'block';
            startCameraBtn.style.display = 'inline-block';
            stopCameraBtn.style.display = 'none';
            codeReader = null;

            const name = e && e.name ? e.name : '';
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                showCameraHint('رُفض إذن الكاميرا — اسمح بالوصول من إعدادات المتصفح ثم أعد التشغيل.');
            } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                showCameraHint('لم يُعثر على كاميرا.');
            } else if (name === 'NotReadableError' || name === 'TrackStartError') {
                showCameraHint('الكاميرا مشغولة بتطبيق آخر — أغلقه ثم أعد المحاولة.');
            } else {
                showCameraHint('تعذّر تشغيل الكاميرا: ' + (e.message || name || 'خطأ غير معروف'));
            }
            showError(cameraHint ? cameraHint.textContent : 'تعذّر تشغيل الكاميرا');
        }
    }

    function stopScanning() {
        try {
            if (codeReader) {
                codeReader.reset();
                codeReader = null;
            }
        } catch (e) { console.warn(e); }

        if (scannerVideo && scannerVideo.srcObject) {
            try {
                scannerVideo.srcObject.getTracks().forEach(t => t.stop());
            } catch (e) {}
            scannerVideo.srcObject = null;
        }

        scanning = false;
        setCameraLed(false);
        scannerVideo.style.display = 'none';
        scannerPlaceholder.style.display = 'block';
        startCameraBtn.style.display = 'inline-block';
        stopCameraBtn.style.display = 'none';
    }

    /* ═══════════ مؤشّرُ حالة النظام ═══════════ */

    function setCameraLed(on) {
        const led = document.getElementById('cam-led');
        const txt = document.getElementById('cam-text');
        if (! led) return;
        led.classList.toggle('kiosk-status__led--off', ! on);
        txt.textContent = on ? 'الكاميرا تعمل' : 'الكاميرا متوقفة';
    }

    let serverUp = true;

    function setServerLed(up) {
        serverUp = up;
        const led = document.getElementById('status-led');
        const txt = document.getElementById('status-text');
        if (! led) return;
        led.classList.toggle('kiosk-status__led--down', ! up);
        txt.textContent = up ? 'متصل' : 'انقطع الاتصال بالخادم';
    }

    async function heartbeat() {
        if (! navigator.onLine) { return setServerLed(false); }
        try {
            const r = await fetch('{{ route("barcode.stats") }}', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            setServerLed(r.ok);
        } catch (e) { setServerLed(false); }
    }

    heartbeat();
    setInterval(heartbeat, 20000);
    window.addEventListener('offline', () => setServerLed(false));
    window.addEventListener('online', heartbeat);

    /* ═══════════ بطاقةُ النتيجة الفورية ═══════════ */

    const resultCard = document.getElementById('result-card');
    let cardTimer = null;
    let busyScanning = false;

    function showCard(kind, opts) {
        clearTimeout(cardTimer);

        resultCard.className = 'rc rc--' + kind;
        resultCard.style.display = 'flex';

        document.getElementById('rc-icon').textContent = opts.icon || '';
        document.getElementById('rc-name').textContent = opts.name || '';
        document.getElementById('rc-dept').textContent = opts.dept || '';
        document.getElementById('rc-title').textContent = opts.title || '';
        document.getElementById('rc-time').textContent = opts.time || '';
        document.getElementById('rc-detail').innerHTML = opts.detail || '';

        const photo = document.getElementById('rc-photo');
        if (opts.photo) { photo.src = opts.photo; photo.style.display = ''; }
        else { photo.style.display = 'none'; }

        const ms = opts.ms || 3000;
        const bar = document.getElementById('rc-bar');
        bar.style.transition = 'none';
        bar.style.width = '100%';
        requestAnimationFrame(() => {
            bar.style.transition = `width ${ms}ms linear`;
            bar.style.width = '0%';
        });

        // اضغط البطاقة لإخفائها مبكراً — مهمّ عند رسائل الرفض الطويلة
        resultCard.onclick = () => hideCard();

        cardTimer = setTimeout(hideCard, ms);
    }

    function hideCard() {
        resultCard.style.display = 'none';
        resultCard.onclick = null;
        busyScanning = false;
    }

    const NO_PHOTO = '{{ asset('images/default-avatar.svg') }}';

    /**
     * رابط صورة الموظف — من الخادم وحده.
     *
     * كانت هذه الدالّةُ تبني المسارَ بنفسها عند غياب `photo_url`:
     * `'/storage/' + emp.photo` — ولا صورةَ موظفٍ تحت `storage`؛ الرفعُ
     * إلى `assets/uploads/employee/`. فكانت نسخةً ثالثةً من عُرفٍ يتفرّق
     * كلّما نُسخ. والخادمُ الآن يُرسل عنواناً جاهزاً أو `null`
     * (انظر `Employee::getPhotoUrlAttribute()`)، وهو يتحقّق من وجود
     * الملفّ فعلاً — فلا يصل إلى الشاشة عنوانٌ مكسور.
     */
    function employeePhotoUrl(emp) {
        return (emp && emp.photo_url) ? emp.photo_url : NO_PHOTO;
    }

    /**
     * ملء لوحة «معلومات الموظف» ببيانات حقيقية من استجابة المسح/التعريف.
     */
    function applyEmployeeFromScan(emp, barcode) {
        if (! emp || ! emp.id) {
            // حتى عند الرفض قد تصل بيانات جزئية بلا id — اعرض الاسم على الأقل
            if (emp && emp.name) {
                noEmployee.style.display = 'none';
                employeeInfo.style.display = 'block';
                document.getElementById('employee-name').textContent = emp.name;
                document.getElementById('employee-department').textContent = emp.department || '—';
                document.getElementById('employee-photo').src = employeePhotoUrl(emp);
                document.getElementById('attendance-status').textContent = '—';
                alreadyComplete.style.display = 'none';
                if (scanHint) scanHint.style.display = '';
            }
            return;
        }

        currentEmployee = emp;
        identifiedBarcode = barcode || identifiedBarcode;
        displayEmployeeInfo(emp, emp.attendance_status || {
            is_checked_in: false,
            is_checked_out: false,
        });
    }

    async function punchByScan(barcode) {
        if (! requirePairing()) return;
        if (busyScanning) return;
        busyScanning = true;
        identifiedBarcode = barcode;

        const position = await currentPosition();

        try {
            const response = await fetch('{{ route("barcode.scan.punch") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    barcode: barcode,
                    latitude: position ? position.latitude : null,
                    longitude: position ? position.longitude : null,
                    accuracy: position ? position.accuracy : null,
                    photos: photosPayload(),
                })
            });

            const data = await response.json().catch(() => ({ success: false, message: 'استجابة غير صالحة' }));
            setServerLed(true);

            // انتهت صلاحية الاقتران في منتصف الجلسة — أعد تحميل الصفحة لعرض نموذج الرمز
            if (data.code === 'kiosk_required') {
                showCard('reject', {
                    icon: '🔒',
                    title: data.title || 'الجهاز غير مقترن',
                    detail: data.message || 'أدخِل رمز الاقتران أعلى الشاشة.',
                    ms: 4000,
                });
                setTimeout(() => { window.location.reload(); }, 1500);
                busyScanning = false;
                return;
            }

            // حدّث لوحة الموظف دائماً من بيانات الخادم الحقيقية
            applyEmployeeFromScan(data.employee || null, barcode);
            renderPunch(data);
            if (data.success) resetPhotos();
            loadDashboardStats();
        } catch (e) {
            setServerLed(false);
            showCard('reject', {
                icon: '⚠️', title: 'انقطع الاتصال',
                detail: 'لم تصل المسحة إلى الخادم — أعد المحاولة.', ms: 4000,
            });
            busyScanning = false;
        }
    }

    function renderPunch(data) {
        const emp = data.employee || {};
        const photo = employeePhotoUrl(emp);

        if (! data.success) {
            const reason = (data.message || '').toString().trim();
            const title = (data.title && data.title !== 'تعذّر التسجيل')
                ? data.title
                : (reason || 'تعذّر التسجيل');
            const detailParts = [];
            if (data.stage_label) detailParts.push(data.stage_label);
            // إن اختلفت الرسالة عن العنوان اعرضها كاملةً — هذا هو سبب الرفض
            if (reason && reason !== title) detailParts.push(reason);
            else if (reason && reason === title && data.stage_label) {
                // العنوان من الرسالة؛ الإبقاء على المرحلة فقط كافٍ
            } else if (! reason && data.code) {
                detailParts.push('رمز الرفض: ' + data.code);
            }

            return showCard(data.code === 'day_complete' ? 'done' : 'reject', {
                icon: data.code === 'day_complete' ? '✓' : '✕',
                name: emp.name, dept: emp.department, photo: emp.photo_url || (emp.photo ? photo : null),
                title: title,
                detail: detailParts.join('<br>'),
                ms: Math.min(12000, 5000 + (reason.length * 40)),
            });
        }

        const isIn = data.punch_type === 'in';
        const late = Number(data.late_minutes || 0);
        const early = Number(data.early_leave_minutes || 0);
        const overtime = Number(data.overtime_minutes || 0);
        const expectedOutRaw = (data.expected_check_out || '').toString();
        const expectedInRaw = (data.expected_check_in || '').toString();
        const expectedOut = formatDeviceTime(expectedOutRaw);
        const expectedIn = formatDeviceTime(expectedInRaw);
        const punchTime = formatDeviceTime(data.time);
        // الحالة من الخادم (بعد مهلة السماح) لا مجرّد دقائق خام
        const statusCode = (data.status_code || '').toString();
        const isLateCard = statusCode === 'late' || statusCode === 'absent';
        const kind = ! isIn ? 'out' : (isLateCard ? 'late' : 'ok');

        const parts = [];
        if (isIn) {
            if (expectedIn) parts.push(`بداية الوردية ${expectedIn}`);
            if (data.status) parts.push(data.status);
            if (late > 0 && isLateCard) parts.push(`تأخير ${late} دقيقة`);
            else if (late > 0) parts.push(`خلال السماح (${late} د)`);
        } else {
            if (expectedOut) parts.push(`نهاية الوردية ${expectedOut}`);
            if (early > 0) parts.push(`انصراف مبكر ${early} دقيقة`);
            if (data.check_out_capped) parts.push('اُعتمدت نهاية الوردية (خارج النافذة)');
            if (overtime > 0) parts.push(`إضافي ${overtime} دقيقة`);
            if (data.working_hours) parts.push(`ساعات العمل: ${data.working_hours}`);
        }
        if (data.warnings && data.warnings.length) {
            parts.push(...data.warnings);
        }
        if (data.latitude != null && data.longitude != null) {
            const loc = Number(data.latitude).toFixed(5) + ', ' + Number(data.longitude).toFixed(5);
            const label = data.location ? (data.location + ' · ' + loc) : loc;
            parts.push('الموقع: <a href="https://www.google.com/maps?q=' + data.latitude + ',' + data.longitude + '" target="_blank" rel="noopener">' + label + '</a>');
        } else if (data.location) {
            parts.push('الموقع: ' + data.location);
        }
        const detail = parts.join('<br>');

        showCard(kind, {
            icon: isIn ? '→' : '←',
            name: emp.name, dept: emp.department, photo: emp.photo_url || (emp.photo ? photo : null),
            title: data.title,
            time: punchTime,
            detail: detail,
            ms: detail ? 4500 : 3000,
        });

        addToRecentActivity(isIn ? 'حضور' : 'انصراف', emp.name, punchTime);

        if (data.expected_check_in || data.expected_check_out) {
            displayShift({
                expected_check_in: expectedInRaw || null,
                expected_check_out: expectedOutRaw || null,
                grace_minutes: 0,
            });
        }
    }

    function displayEmployeeInfo(employee, attendanceStatus) {
        noEmployee.style.display = 'none';
        employeeInfo.style.display = 'block';

        document.getElementById('employee-name').textContent = employee.name || '—';
        document.getElementById('employee-department').textContent = employee.department || 'غير محدد';
        document.getElementById('employee-photo').src = employeePhotoUrl(employee);

        updateAttendanceStatus(attendanceStatus || employee.attendance_status || {});

        employeeInfo.classList.add('employee-card');
        setTimeout(() => employeeInfo.classList.remove('employee-card'), 3000);
    }

    function updateAttendanceStatus(attendanceStatus) {
        alreadyComplete.style.display = 'none';
        if (scanHint) scanHint.style.display = '';

        const st = attendanceStatus || {};
        let statusText = '';

        if (! st.is_checked_in && ! st.is_checked_out) {
            statusText = 'لم يسجل حضور — امسح البطاقة';
        } else if (st.is_checked_in && ! st.is_checked_out) {
            statusText = `حاضر منذ ${formatDeviceTime(st.check_in_time) || '—'} — امسح للانصراف`;
        } else if (st.is_checked_in && st.is_checked_out) {
            alreadyComplete.style.display = 'block';
            if (scanHint) scanHint.style.display = 'none';
            statusText = `مكتمل - حضور: ${formatDeviceTime(st.check_in_time) || '—'}, انصراف: ${formatDeviceTime(st.check_out_time) || '—'}`;
        }

        document.getElementById('attendance-status').textContent = statusText;
    }

    function displayShift(shift) {
        const panel = document.getElementById('shift-panel');
        if (! panel) return;

        if (! shift) { panel.style.display = 'none'; return; }

        panel.style.display = '';
        document.getElementById('shift-in').textContent = formatDeviceTime(shift.expected_check_in) || '—';
        document.getElementById('shift-out').textContent = formatDeviceTime(shift.expected_check_out) || '—';
        document.getElementById('shift-grace').textContent = (shift.grace_minutes ?? 0) + ' دقيقة';

        const flags = document.getElementById('shift-flags');
        flags.innerHTML = '';
        const add = (text, tone) => {
            const el = document.createElement('span');
            el.className = 'badge badge-' + tone;
            el.textContent = text;
            flags.appendChild(el);
        };
        if (shift.is_holiday)    add('عطلة رسمية', 'info');
        if (shift.is_weekly_off) add('عطلة أسبوعية', 'secondary');
        if (shift.on_leave)      add('في إجازة معتمدة', 'warning');
    }

    function hideEmployeeInfo() {
        employeeInfo.style.display = 'none';
        noEmployee.style.display = 'block';
        const panel = document.getElementById('shift-panel');
        if (panel) panel.style.display = 'none';
        currentEmployee = null;
        identifiedBarcode = null;
    }

    let lastKnownPosition = null;

    function currentPosition() {
        return new Promise((resolve) => {
            if (! navigator.geolocation) {
                resolve(lastKnownPosition);
                return;
            }

            let settled = false;
            const finish = (value) => {
                if (settled) return;
                settled = true;
                if (value) lastKnownPosition = value;
                resolve(value || lastKnownPosition);
            };

            const timer = setTimeout(() => finish(null), 8000);

            // محاولة دقيقة أولاً، ثم سريعة إن فشلت — حتى لا يُمسح بلا موقع
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    clearTimeout(timer);
                    finish({
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                        accuracy: pos.coords.accuracy,
                    });
                },
                () => {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            clearTimeout(timer);
                            finish({
                                latitude: pos.coords.latitude,
                                longitude: pos.coords.longitude,
                                accuracy: pos.coords.accuracy,
                            });
                        },
                        () => { clearTimeout(timer); finish(null); },
                        { enableHighAccuracy: false, timeout: 4000, maximumAge: 300000 }
                    );
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 60000 }
            );
        });
    }

    // جهّز موقعاً مبكراً قبل أول مسحة — الكاميرا غالباً تستغرق وقتاً أطول من GPS
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                lastKnownPosition = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                };
            },
            () => {},
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
        );
    }

    const capturedPhotos = new Map();
    let photoStream = null;

    async function openPhotoStream() {
        const panel = document.getElementById('photo-capture');
        if (! panel) return;

        panel.style.display = '';
        if (photoStream) return;

        try {
            photoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
            const video = document.getElementById('photo-stream');
            video.srcObject = photoStream;
            video.style.display = '';
        } catch (e) {
            console.warn('تعذّر فتح الكاميرا:', e);
        }
    }

    function closePhotoStream() {
        if (! photoStream) return;
        photoStream.getTracks().forEach(t => t.stop());
        photoStream = null;
        const video = document.getElementById('photo-stream');
        if (video) { video.srcObject = null; video.style.display = 'none'; }
    }

    function shootPhoto(typeId, label) {
        const video = document.getElementById('photo-stream');
        const canvas = document.getElementById('photo-canvas');
        if (! video || ! video.videoWidth) { return false; }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        const dataUri = canvas.toDataURL('image/jpeg', 0.75);
        capturedPhotos.set(String(typeId), dataUri);

        const thumbs = document.getElementById('photo-thumbs');
        let box = thumbs.querySelector('[data-thumb="' + typeId + '"]');
        if (! box) {
            box = document.createElement('div');
            box.dataset.thumb = typeId;
            box.style.cssText = 'text-align:center;font-size:.7rem;';
            thumbs.appendChild(box);
        }
        box.innerHTML = '<img src="' + dataUri + '" style="width:64px;height:48px;object-fit:cover;border-radius:6px;">'
            + '<div>' + label + '</div>';

        return true;
    }

    document.querySelectorAll('.photo-shot').forEach(btn => {
        btn.addEventListener('click', async () => {
            await openPhotoStream();
            const ok = shootPhoto(btn.dataset.typeId, btn.textContent.trim());
            btn.classList.toggle('btn-outline-primary', ! ok);
            btn.classList.toggle('btn-success', ok);
        });
    });

    function photosPayload() {
        return Array.from(capturedPhotos, ([type_id, image]) => ({ type_id: Number(type_id), image }));
    }

    function resetPhotos() {
        capturedPhotos.clear();
        const thumbs = document.getElementById('photo-thumbs');
        if (thumbs) thumbs.innerHTML = '';
        document.querySelectorAll('.photo-shot').forEach(btn => {
            btn.classList.add('btn-outline-primary');
            btn.classList.remove('btn-success');
        });
        closePhotoStream();
    }

    function loadDashboardStats() {
        fetch('{{ route("barcode.stats") }}', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('present-count').textContent = data.employees_present_today || 0;
            document.getElementById('late-count').textContent = data.late_arrivals_today || 0;
            const absent = Math.max(0, (data.total_employees || 0) - (data.total_check_ins_today || 0));
            document.getElementById('absent-count').textContent = absent;
            document.getElementById('barcode-scans').textContent = data.barcode_scans_today || 0;
        })
        .catch(error => {
            console.error('خطأ في تحميل الإحصائيات:', error);
        });
    }

    function addToRecentActivity(action, employeeName, time) {
        const activityDiv = document.getElementById('recent-activity');
        const activityItem = document.createElement('div');
        activityItem.className = 'border-bottom pb-2 mb-2';
        activityItem.innerHTML = `
            <small class="stk-help">${formatDeviceTime(new Date(), true)}</small><br>
            <strong>${employeeName || '—'}</strong><br>
            <span class="badge badge-${action === 'حضور' ? 'success' : 'warning'}">${action}</span>
            <span class="text-muted">${time || ''}</span>
        `;

        if (activityDiv.children.length === 0 || activityDiv.children[0].tagName === 'P') {
            activityDiv.innerHTML = '';
        }
        activityDiv.insertBefore(activityItem, activityDiv.firstChild);

        while (activityDiv.children.length > 5) {
            activityDiv.removeChild(activityDiv.lastChild);
        }
    }

    function playSuccessSound() {
        try {
            const audio = new Audio('/sounds/beep-success.mp3');
            audio.play().catch(() => {});
        } catch (e) {}
    }

    function vibrateDevice() {
        if ('vibrate' in navigator) navigator.vibrate(200);
    }

    function showLoading(message) {
        Swal.fire({
            title: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });
    }

    function hideLoading() { Swal.close(); }

    function showSuccess(message, data = null, warnings = null) {
        let html = `<p>${message}</p>`;
        if (data) {
            html += `
                <div class="text-right mt-3">
                    <strong>الموظف:</strong> ${data.employee_name}<br>
                    <strong>الوقت:</strong> ${formatDeviceTime(data.check_out_time || data.check_in_time)}<br>
                    <strong>الحالة:</strong> ${data.status || '—'}
                    ${data.late_minutes ? ` (تأخير ${data.late_minutes} دقيقة)` : ''}<br>
                    ${data.location ? `<strong>الموقع:</strong> ${data.location}${data.distance_meters !== null && data.distance_meters !== undefined ? ` — ${data.distance_meters} م` : ''}<br>` : ''}
                    ${data.working_hours ? `<strong>ساعات العمل:</strong> ${data.working_hours}<br>` : ''}
                    ${data.overtime_minutes ? `<strong>إضافي:</strong> ${data.overtime_minutes} دقيقة<br>` : ''}
                    ${data.early_leave_minutes ? `<strong>انصراف مبكر:</strong> ${data.early_leave_minutes} دقيقة` : ''}
                </div>
            `;
        }

        if (warnings && warnings.length) {
            html += '<div class="alert alert-warning text-right mt-2 mb-0" style="font-size:.82rem;">'
                + warnings.map(w => `• ${w}`).join('<br>') + '</div>';
        }

        Swal.fire({
            icon: 'success',
            title: 'تم بنجاح!',
            html: html,
            confirmButtonText: 'موافق',
        });
    }

    function showError(message, stage = null) {
        const stages = {
            employee: 'ملف الموظف',
            shift: 'الوردية',
            location: 'الموقع الجغرافي',
            day: 'سجل اليوم',
            leave: 'الإجازات',
            photos: 'صور التسجيل',
        };

        Swal.fire({
            icon: 'error',
            title: stage ? `رُدّ عند: ${stages[stage] || stage}` : 'خطأ!',
            text: message,
            confirmButtonText: 'موافق',
        });
    }

    window.addEventListener('beforeunload', function() {
        stopScanning();
    });
});
</script>
@endsection
