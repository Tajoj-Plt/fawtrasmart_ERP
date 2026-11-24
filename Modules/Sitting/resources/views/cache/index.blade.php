@extends('sitting::master')

@section('title', 'مسح الذاكرة المؤقتة')

@section('content')
    @include('sitting::components._settings_styles')

    @php
        // حجمٌ يُقرأ: «١٢٫٤ م.ب» لا «13002342»
        $human = function (int $bytes) {
            if ($bytes <= 0) {
                return '0';
            }
            $units = ['بايت', 'ك.ب', 'م.ب', 'ج.ب'];
            $i = (int) floor(log($bytes, 1024));
            $i = min($i, count($units) - 1);

            return number_format($bytes / (1024 ** $i), $i > 1 ? 1 : 0).' '.$units[$i];
        };

        $totalBytes = $status['app']['bytes'] + $status['views']['bytes'];
        $totalFiles = $status['app']['files'] + $status['views']['files'];
        $bootstrapOn = count(array_filter($status['bootstrap']));
    @endphp

    <style>
        .cc-out {
            direction: ltr; text-align: left; unicode-bidi: embed;
            font-family: Consolas, Monaco, 'Courier New', monospace; font-size: .78rem;
            background: #1e2430; color: #dfe4ec; border-radius: 10px;
            padding: .9rem 1.1rem; margin: 0; max-height: 300px; overflow: auto;
            white-space: pre-wrap; word-break: break-word;
        }
        .cc-item { display: flex; align-items: flex-start; gap: .9rem; padding: .95rem .2rem; border-bottom: 1px solid #f2f4f7; }
        .cc-item:last-child { border-bottom: 0; }
        .cc-item > .ic {
            flex: 0 0 auto; width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            background: #eaf1fb; color: #2b6cb0; font-size: 1.05rem;
        }
        .cc-item .txt { min-width: 0; flex: 1; }
        .cc-item .txt b { display: block; font-weight: 600; font-size: .94rem; color: #1b2430; }
        .cc-item .txt .sym { margin: .3rem 0 0; font-size: .82rem; color: #7d5f0e; line-height: 1.7; }
        .cc-item .txt .sym i { font-size: .9em; }
        .cc-item .txt .hint { margin: .25rem 0 0; font-size: .8rem; color: #6b7280; line-height: 1.75; }
        .cc-item .txt code { background: #f2f4f7; color: #384250; padding: .05rem .3rem; border-radius: 4px; direction: ltr; display: inline-block; }
        .cc-item .act { flex: 0 0 auto; padding-top: .2rem; }
        .cc-state { display: flex; flex-wrap: wrap; gap: .45rem; }
        .cc-guide summary { cursor: pointer; font-weight: 700; color: #23557f; list-style: none; }
        .cc-guide summary::-webkit-details-marker { display: none; }
        .cc-guide summary::before { content: '\25C0'; font-size: .7em; margin-left: .4rem; display: inline-block; transition: transform .15s; }
        .cc-guide[open] summary::before { transform: rotate(-90deg); }
        .cc-guide ul { margin: .7rem 0 0; padding-inline-start: 1.2rem; }
        .cc-guide li { font-size: .85rem; margin-bottom: .45rem; line-height: 1.75; }
        .cc-busy { pointer-events: none; opacity: .6; }

        .cc-overlay {
            display: none; position: fixed; inset: 0; z-index: 2000;
            background: rgba(17, 24, 39, .55); align-items: center; justify-content: center;
        }
        .cc-overlay.is-on { display: flex; }
        .cc-overlay__box {
            background: #fff; border-radius: 14px; padding: 1.8rem 2.2rem;
            text-align: center; max-width: 340px; box-shadow: 0 18px 50px rgba(0,0,0,.25);
        }
        .cc-overlay__box h5 { margin: .8rem 0 .4rem; font-weight: 700; color: #111827; }
        .cc-overlay__box p { margin: 0; font-size: .85rem; color: #6b7280; line-height: 1.8; }
        .cc-spin {
            width: 42px; height: 42px; margin: 0 auto;
            border: 4px solid #e5e7eb; border-top-color: #2b6cb0;
            border-radius: 50%; animation: cc-rot .9s linear infinite;
        }
        @keyframes cc-rot { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .cc-spin { animation-duration: 3s; } }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-refresh-cw"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">مسح الذاكرة المؤقتة</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">مسح الذاكرة المؤقتة</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- مخرَج آخر أمرٍ شُغِّل — يُعرَض كاملاً: خطوةٌ سقطت خبرٌ لا يُلخَّص
             في سطر تنبيه --}}
        @if (session('cache_output'))
            <div class="st-card">
                <div class="head">
                    <i class="feather icon-terminal" style="color:#2b6cb0"></i>
                    <h4>مخرَج آخر تشغيل</h4>
                </div>
                <div class="body">
                    <pre class="cc-out">{{ session('cache_output') }}</pre>
                </div>
            </div>
        @endif

        {{-- ── دليل الشاشة ── --}}
        <div class="st-note info cc-guide">
            <i class="feather icon-help-circle mt-1"></i>
            <details style="flex:1">
                <summary>ما هذه الشاشة ومتى تُستعمَل؟</summary>
                <ul>
                    <li>
                        <b>الذاكرة المؤقتة (الكاش)</b> نسخةٌ ثانية يحفظها النظام من أشياء حسابُها
                        بطيء — إعداداتٌ من القاعدة، قوالبُ مترجَمة، جدول المسارات — ليقرأها فوراً
                        بدل إعادة بنائها في كل طلب.
                    </li>
                    <li>
                        المشكلة أن النسخة قد <b>تَقدُم</b>: تُعدّل إعداداً أو تَرفع تحديثاً إلى
                        الخادم، فيبقى النظام يعمل بالنسخة القديمة. مسحُها هو الحلّ، وبعده يُعاد
                        بناؤها تلقائياً من المصدر.
                    </li>
                    <li>
                        <b>لا خطر على بياناتك.</b> الكاش نسخةٌ ثانية لا أصل — لا فاتورةَ فيه ولا
                        عميلَ ولا رصيد. أسوأ ما يقع بمسحه بطءُ أوّل طلبٍ بعده حتى يُعاد بناؤه.
                    </li>
                    <li>
                        <b>ابدأ بزرّ «مسح كل الذاكرة المؤقتة»</b> أدناه — هو المقصود في عامّة
                        الحالات. والأنواع المفردة لمن يعرف أيّها يريد.
                    </li>
                    <li>
                        الأثر على كل المستخدمين معاً: أوّل طلبٍ بعد المسح أبطأ من عادته لثوانٍ.
                        لا يُخرَج أحدٌ من جلسته ولا تُفقَد صفحةٌ مفتوحة.
                    </li>
                </ul>
            </details>
        </div>

        {{-- ══════════ حالة الكاش الآن ══════════ --}}
        <div class="st-stats">
            <div class="st-stat">
                <div class="ic primary"><i class="feather icon-hard-drive"></i></div>
                <div>
                    <div class="val">{{ $human($totalBytes) }}</div>
                    <div class="lbl">حجم الملفّات المؤقتة</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-database"></i></div>
                <div>
                    <div class="val">{{ number_format($status['app']['files']) }}</div>
                    <div class="lbl">مدخلات كاش التطبيق</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-layout"></i></div>
                <div>
                    <div class="val">{{ number_format($status['views']['files']) }}</div>
                    <div class="lbl">قوالب مترجَمة</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic {{ $bootstrapOn ? 'warning' : 'success' }}">
                    <i class="feather icon-{{ $bootstrapOn ? 'archive' : 'check' }}"></i>
                </div>
                <div>
                    <div class="val">{{ $bootstrapOn }}</div>
                    <div class="lbl">ملفّات إقلاع مبنيّة مسبقاً</div>
                </div>
            </div>
        </div>

        {{-- ══════════ الزرّ الرئيس ══════════ --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-zap" style="color:#2b6cb0"></i>
                <h4>مسح كل الذاكرة المؤقتة</h4>
                <span class="spacer"></span>
                @permission('settings.cache.clear')
                    <form method="POST" action="{{ route('SettingsCache.clearAll') }}" class="cc-form" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm"
                            data-confirm="سيُمسَح {{ count($targets) }} نوعاً من الذاكرة المؤقتة. لا تُمَسّ بياناتك، وأوّل طلبٍ بعد المسح أبطأ من عادته لثوانٍ. متابعة؟">
                            <i class="feather icon-trash-2"></i> مسح كل الذاكرة المؤقتة
                        </button>
                    </form>
                @endpermission
            </div>
            <div class="body">
                <p style="font-size:.85rem;color:#4b5563;line-height:1.9;margin:0">
                    يشغّل الأنواع {{ count($targets) }} كلَّها بالترتيب. هذا هو الزرّ المقصود
                    <b>بعد رفع ملفّات تحديثٍ إلى الخادم</b>، أو حين لا تعرف أيَّ نوعٍ سبّب العطل.
                    وخطوةٌ تسقط لا توقف بقيّة الخطوات — نتيجة كلٍّ منها تظهر أعلى الصفحة.
                </p>
                <div class="st-note info" style="margin:.9rem 0 0">
                    <i class="feather icon-shield mt-1"></i>
                    <div>
                        <strong>لا يمسّ بياناتك ولا إعداداتك.</strong>
                        <p>
                            لا يُمسَح هنا إلا ما يستطيع النظام إعادةَ بنائه وحدَه من المصدر.
                            لتغيير بنية القاعدة أو زراعة بيانات تحديثٍ جديد فالمكان
                            <a href="{{ route('SystemUpdate.index') }}">شاشة تحديث النظام</a>،
                            ولنسخ البيانات <a href="{{ route('Backup.index') }}">شاشة النسخ الاحتياطي</a>.
                        </p>
                    </div>
                </div>
                <div class="st-note warn" style="margin:.7rem 0 0">
                    <i class="feather icon-info mt-1"></i>
                    <div>
                        <strong>استثناءٌ واحد يستحقّ الذكر.</strong>
                        <p>
                            «كاش التطبيق» يضمّ أيضاً طوابيرَ مؤقتة — كاستهلاك الباقة المنتظر رفعَه إلى
                            فوترة. مسحُه يُسقطها، والدورة الدورية تعيد حسابها لاحقاً؛ فلا شيء يضيع
                            نهائياً، لكن الرقم قد يتأخّر ساعةً في لوحة الاشتراك.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ الأنواع مفردةً ══════════ --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-list" style="color:#2b6cb0"></i>
                <h4>الأنواع مفردةً</h4>
                <span class="st-badge info">{{ count($targets) }}</span>
            </div>
            <div class="body">
                <p style="font-size:.85rem;color:#6b7280;line-height:1.8;margin:0 0 .5rem">
                    اقرأ سطر <span style="color:#7d5f0e">«العَرَض»</span> تحت كل نوع: إن طابق
                    ما تراه، فهذا زرُّك.
                </p>

                @foreach ($targets as $key => $target)
                    @php
                        $extra = match ($key) {
                            'app' => number_format($status['app']['files']).' مدخلة · '.$human($status['app']['bytes']),
                            'views' => number_format($status['views']['files']).' ملفّ · '.$human($status['views']['bytes']),
                            'config' => $status['bootstrap']['config'] ? 'مبنيّ الآن' : 'غير مبنيّ',
                            'routes' => $status['bootstrap']['routes'] ? 'مبنيّ الآن' : 'غير مبنيّ',
                            'events' => $status['bootstrap']['events'] ? 'مبنيّ الآن' : 'غير مبنيّ',
                            'compiled' => $status['bootstrap']['services'] ? 'مبنيّ الآن' : 'غير مبنيّ',
                            default => null,
                        };
                    @endphp
                    <div class="cc-item">
                        <div class="ic"><i class="feather icon-{{ $target['icon'] }}"></i></div>
                        <div class="txt">
                            <b>
                                {{ $target['label'] }}
                                @if ($extra)
                                    <span class="st-badge off" style="margin-inline-start:.4rem">{{ $extra }}</span>
                                @endif
                            </b>
                            <p class="sym">
                                <i class="feather icon-alert-circle"></i>
                                <b>العَرَض:</b> {{ $target['symptom'] }}
                            </p>
                            <p class="hint">{!! $target['hint'] !!}</p>
                        </div>
                        <div class="act">
                            @permission('settings.cache.clear')
                                <form method="POST" action="{{ route('SettingsCache.clear', $key) }}" class="cc-form" style="margin:0">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                        <i class="feather icon-trash-2"></i> مسح
                                    </button>
                                </form>
                            @endpermission
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="foot">
                <span style="font-size:.8rem;color:#8a93a0">
                    مخزن الكاش الحالي: <code style="direction:ltr;display:inline-block">{{ $status['driver'] }}</code>
                </span>
            </div>
        </div>
    </div>

    <div class="cc-overlay" id="ccOverlay" role="alert" aria-live="assertive">
        <div class="cc-overlay__box">
            <div class="cc-spin"></div>
            <h5>جارٍ المسح…</h5>
            <p>
                ثوانٍ معدودة عادةً. لا تُغلق الصفحة ولا تُحدّثها —
                إغلاقُها يُضيع تقرير النتيجة.
            </p>
        </div>
    </div>

    <script>
        // تأكيدٌ ثم قفلُ النموذج: الأمر يستغرق ثوانيَ بلا أثرٍ في الشاشة،
        // والنقرة الثانية تشغّله مرّتين
        (function () {
            var overlay = document.getElementById('ccOverlay');
            var running = false;

            document.querySelectorAll('.cc-form').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (running) {
                        event.preventDefault();
                        return;
                    }

                    var button = form.querySelector('button[type="submit"]');
                    var message = button && button.dataset.confirm;

                    if (message && !window.confirm(message)) {
                        event.preventDefault();
                        return;
                    }

                    running = true;

                    if (button) {
                        button.classList.add('cc-busy');
                        button.disabled = true;
                    }

                    if (overlay) {
                        overlay.classList.add('is-on');
                    }
                });
            });

            /* العودة بزرّ «رجوع» تعرض الصفحة من ذاكرة المتصفّح بالغطاء ظاهراً
               كما تُرك، فيبدو النظام معلَّقاً وهو ليس كذلك */
            window.addEventListener('pageshow', function () {
                running = false;
                if (overlay) {
                    overlay.classList.remove('is-on');
                }
            });
        })();
    </script>
@endsection
