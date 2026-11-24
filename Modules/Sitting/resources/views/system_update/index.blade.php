{{-- الزائر لا يملك هيدراً ولا قائمة جانبية تعمل بلا مستخدم — انظر القالب المستقلّ --}}
@extends(($guest ?? false) ? 'sitting::system_update.standalone' : 'sitting::master')

@section('title', 'تحديث النظام')

@section('content')
    @include('sitting::components._settings_styles')

    @php
        $pending = $migrations['pending'];
        $pendingCount = count($pending);
        // ما يشغّله «التحديث الشامل» — الموسوم additive في السجلّ
        $additiveCount = count(array_filter($tasks, fn ($t) => ! empty($t['additive'])));
        // اسم ملفّ الترحيل: التاريخ في صدره والباقي وصفُه. فصلُهما يجعل
        // القائمة تُقرَأ، وإلا صارت جدارَ نصٍّ لاتيني في صفحة عربية
        $splitName = function (string $name) {
            return preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_(.+)$/', $name, $m)
                ? ['stamp' => str_replace('_', '-', substr($m[1], 0, 10)), 'body' => str_replace('_', ' ', $m[2])]
                : ['stamp' => null, 'body' => $name];
        };

        // فحصُ ما ستفعله الترحيلات المعلّقة بالبيانات — `MigrationSafetyScanner`
        $safe = $safety['counts']['safe'];
        $altering = $safety['counts']['altering'];
        $destructive = $safety['counts']['destructive'];
        // درجةُ الملفّ الواحد ← لونُه ووسمُه في القائمة
        $levelMeta = [
            'safe' => ['label' => 'يضيف فقط', 'color' => '#1a7f5a', 'bg' => '#e8f6f0'],
            'altering' => ['label' => 'يعدّل بنية', 'color' => '#8a6100', 'bg' => '#fdf4e0'],
            'destructive' => ['label' => 'يحذف بيانات', 'color' => '#a52121', 'bg' => '#fdecec'],
        ];
    @endphp

    <style>
        .su-out {
            direction: ltr; text-align: left; unicode-bidi: embed;
            font-family: Consolas, Monaco, 'Courier New', monospace; font-size: .78rem;
            background: #1e2430; color: #dfe4ec; border-radius: 10px;
            padding: .9rem 1.1rem; margin: 0; max-height: 340px; overflow: auto;
            white-space: pre-wrap; word-break: break-word;
        }
        .su-task { display: flex; align-items: flex-start; gap: .9rem; padding: .85rem .2rem; border-bottom: 1px solid #f2f4f7; }
        .su-task:last-child { border-bottom: 0; }
        .su-task .txt { min-width: 0; flex: 1; }
        .su-task .txt b { display: block; font-weight: 600; font-size: .93rem; color: #1b2430; }
        .su-task .txt p { margin: .2rem 0 0; font-size: .8rem; color: #6b7280; line-height: 1.6; }
        .su-task .txt .caution { color: #7d5f0e; }
        .su-task .txt .readonly { color: #4a5568; }
        .su-task .act { flex: 0 0 auto; padding-top: .1rem; }
        .su-mig { display: flex; gap: .5rem; align-items: baseline; padding: .4rem .2rem; border-bottom: 1px dashed #eef0f4; font-size: .82rem; }
        .su-mig:last-child { border-bottom: 0; }
        .su-mig .stamp { flex: 0 0 auto; color: #8a93a0; font-variant-numeric: tabular-nums; direction: ltr; }
        .su-mig .body { direction: ltr; text-align: left; unicode-bidi: embed; color: #384250; word-break: break-word; }
        .su-mig .lvl { flex: 0 0 auto; font-size: .7rem; font-weight: 700; padding: .1rem .45rem; border-radius: 5px; }
        .su-mig.destructive { border-bottom-style: solid; border-bottom-color: #f7d4d4; }

        /* حصيلةُ الفحص — ثلاثة أرقام في سطر */
        .su-scan { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .8rem; }
        .su-chip { font-size: .78rem; padding: .3rem .7rem; border-radius: 999px; border: 1px solid transparent; }
        .su-chip b { font-variant-numeric: tabular-nums; margin-inline-end: .2rem; }
        .su-chip.safe { color: #1a7f5a; background: #e8f6f0; border-color: #c6e8da; }
        .su-chip.alter { color: #8a6100; background: #fdf4e0; border-color: #f0dfb4; }
        .su-chip.destroy { color: #a52121; background: #fdecec; border-color: #f3cccc; }

        /* سطرُ العملية تحت ملفّها — منزاحٌ ليُقرأ تابعاً لا مستقلّاً */
        .su-op {
            font-size: .78rem; color: #6b7280; line-height: 1.8;
            padding: .15rem .2rem .35rem; margin-inline-start: 1.4rem;
            border-inline-start: 2px solid #eef0f4; padding-inline-start: .7rem;
        }
        .su-op.destructive { color: #a52121; border-inline-start-color: #f3cccc; }
        .su-op.altering { color: #8a6100; border-inline-start-color: #f0dfb4; }
        .su-op code { direction: ltr; unicode-bidi: embed; background: #f4f6f8; padding: 0 .25rem; border-radius: 3px; color: inherit; }
        .su-op em { display: block; font-style: normal; opacity: .8; }

        /* كتلةُ التشغيل: الخياران ثم الزرّ */
        .su-run { margin: 1.1rem 0 0; padding-top: .9rem; border-top: 1px solid #eef0f4; }
        .su-opt {
            display: flex; gap: .6rem; align-items: flex-start; cursor: pointer;
            margin: 0 0 .6rem; padding: .6rem .8rem; border-radius: 8px;
            background: #f7f9fb; border: 1px solid #e6eaef; font-weight: 400;
        }
        .su-opt input { margin-top: .25rem; flex: 0 0 auto; }
        .su-opt b { display: block; font-size: .85rem; color: #1b2430; font-weight: 600; }
        .su-opt small { display: block; font-size: .77rem; color: #6b7280; line-height: 1.7; }
        .su-opt.danger { background: #fdecec; border-color: #f3cccc; }
        .su-opt.danger b { color: #8c1c1c; }
        /* ليست خانةً تُعلَّم بل بيانُ قاعدة — فلا مؤشّر يدٍ ولا مظهرُ اختيار */
        .su-opt.locked { background: #eaf7f1; border-color: #bfe4d4; color: #17603f; cursor: default; }
        .su-opt.locked b { color: #17603f; }

        /* «كل المعلَّق يضيف فقط» — الطمأنة تستحقّ لوناً خاصّاً بها.
           `_settings_styles` فيها info/warn/dang ولا أخضر فيها، والأخضر هنا
           ليس زينة: هو الفرق بين «اقرأ» و«امضِ» في أوّل نظرة على الشاشة */
        .st-note.good { background: #eaf7f1; border-color: #bfe4d4; color: #17603f; }
        .su-guide summary { cursor: pointer; font-weight: 700; color: #23557f; list-style: none; }
        .su-guide summary::-webkit-details-marker { display: none; }
        .su-guide summary::before { content: '\25C0'; font-size: .7em; margin-left: .4rem; display: inline-block; transition: transform .15s; }
        .su-guide[open] summary::before { transform: rotate(-90deg); }
        .su-guide ul { margin: .7rem 0 0; padding-inline-start: 1.2rem; }
        .su-guide li { font-size: .85rem; margin-bottom: .4rem; line-height: 1.7; }
        .su-busy { pointer-events: none; opacity: .6; }

        /* ترتيبُ الشاشة */
        .su-lead { font-size: .88rem; line-height: 1.9; margin: 0 0 1rem; color: var(--app-text, #4b5563); }
        .su-more { margin: 1rem 0 0; }
        .su-more .st-note { margin: .6rem 0; }
        .su-miglist {
            max-height: 320px; overflow-y: auto; padding: .25rem .75rem;
            border: 1px solid var(--sl-line, #e4e7ec); border-radius: var(--sl-r, 8px);
            background: var(--sl-tint, #fafbfc); margin-bottom: 1rem;
        }
        /* خيارُ النسخة الاحتياطية: المربّعُ ونصُّه في سطرٍ واحد. الثيمُ
           المشترك يفرض على كل `label` هيئةَ الكتلة، فكان المربّعُ فوق نصِّه. */
        .sl-scope label.su-opt, .sl-scope .su-opt { display: flex; align-items: flex-start; gap: .6rem; }
        .su-run { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; }
        .su-run .su-opt { flex: 1 1 320px; margin: 0; }
        .su-run > .btn { flex: 0 0 auto; }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-download-cloud"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">تحديث النظام</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">تحديث النظام</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        {{-- «أُوقف قبل التنفيذ» ليس نجاحاً ولا عطلاً: حارسُ الحذف منع التشغيل،
             وبلا هذا السطر تسقط رسالتُه صامتةً ويبدو الزرّ كأنه لم يفعل شيئاً --}}
        @include('layouts.alerts.warning')

        {{-- مخرَج آخر أمرٍ شُغِّل — يُعرَض كاملاً: ترحيلٌ سقط في المنتصف
             خبرٌ لا يُلخَّص في سطر تنبيه --}}
        @if (session('update_output'))
            <div class="st-card">
                <div class="head">
                    <i class="feather icon-terminal" style="color:#2b6cb0"></i>
                    <h4>مخرَج آخر تشغيل</h4>
                </div>
                <div class="body">
                    <pre class="su-out">{{ session('update_output') }}</pre>
                </div>
            </div>
        @endif

        {{-- ══════════ الحالة في ثلاثة أرقام ══════════
             أوّلُ ما يُقرأ: كم نُفِّذ، وكم ينتظر، وكم مهمّة. كانت تحت بطاقة
             «التحديث الشامل» وشروحها، فلا تُرى إلّا بالتمرير. --}}
        <div class="st-stats">
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-check-circle"></i></div>
                <div>
                    <div class="val">{{ number_format($migrations['ran']) }}</div>
                    <div class="lbl">ترحيلات منفَّذة</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic {{ $pendingCount ? 'warning' : 'success' }}">
                    <i class="feather icon-{{ $pendingCount ? 'clock' : 'check' }}"></i>
                </div>
                <div>
                    <div class="val">{{ number_format($pendingCount) }}</div>
                    <div class="lbl">ترحيلات معلّقة</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic info"><i class="feather icon-package"></i></div>
                <div>
                    <div class="val">{{ number_format(count($tasks)) }}</div>
                    <div class="lbl">مهامّ تحديث بيانات</div>
                </div>
            </div>
        </div>

        {{-- ── دليل الشاشة ── --}}
        <details class="sl-guide su-guide">
            <summary><i class="feather icon-help-circle" aria-hidden="true"></i> ما هذه الشاشة ومتى تُستعمَل؟</summary>
            <div class="sl-guide__body">
                <ul>
                    <li>
                        بعد رفع ملفّات تحديثٍ جديدة إلى الخادم، تبقى قاعدة البيانات على بنيتها
                        القديمة حتى تُشغَّل <b>الترحيلات</b>. أعراض إهمالها: شاشةٌ جديدة تُعطي
                        خطأ «عمود غير موجود»، أو حقلٌ يُحفظ ولا يظهر.
                    </li>
                    <li>
                        <b>تحديث البيانات</b> يزرع ما أضافه التحديث من صفوفٍ ثابتة — صلاحيةٌ جديدة،
                        مفتاح إعداد، قالب مستند. ما يكتبه المستخدم بنفسه (فواتير، عملاء، أرصدة)
                        لا تمسّه هذه الشاشة أبداً.
                    </li>
                    <li>
                        كل مهمّةٍ هنا يُعاد تشغيلها بلا ضرر إلا ما وُسِم بتحذير أصفر، وإلا ما كان
                        في قسم «بيانات أوّلية» — وأزرارُ ذلك القسم تُقفَل تلقائياً متى امتلأ جدولها.
                    </li>
                    <li>
                        <b>خُذ نسخةً احتياطية قبل تشغيل الترحيلات.</b> الترحيل يغيّر بنية الجداول،
                        ولا زرَّ تراجعٍ عنه في هذه الشاشة —
                        <a href="{{ route('Backup.index') }}">شاشة النسخ الاحتياطي</a>.
                    </li>
                    <li>
                        لا تُغلق الصفحة أثناء التنفيذ. الأمر الطويل يستمرّ على الخادم، لكنك لن ترى
                        مخرَجه إن غادرت.
                    </li>
                </ul>
            </div>
        </details>

        {{-- سبب وصول المستخدم إلى هنا حين حوّله الوسيط — لا يظهر حين
             يفتح الشاشة بنفسه --}}
        @if (session('update_required'))
            <div class="st-note warn">
                <i class="feather icon-alert-triangle mt-1"></i>
                <div style="flex:1">
                    <strong>النظام بحاجة إلى تحديث قبل المتابعة.</strong>
                    <p>{{ session('update_required') }}</p>
                    {{-- التأجيل لمن له عملٌ يتابعه — والزائر ليس منهم، والمتحكّم يرفضه له --}}
                    @unless ($guest ?? false)
                        <form method="POST" action="{{ route('SystemUpdate.snooze') }}" style="margin:.6rem 0 0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="feather icon-clock"></i> متابعة العمل الآن وتأجيل التحديث
                            </button>
                            <span style="font-size:.78rem;color:#8a6d1e;margin-inline-start:.5rem">
                                تأجيلٌ لنصف ساعة فقط — الترحيلات تبقى معلّقة.
                            </span>
                        </form>
                    @endunless
                </div>
            </div>
        @endif

        {{-- ══════════ التحديث الشامل ══════════ --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-zap" style="color:#2b6cb0"></i>
                <h4>التحديث الشامل</h4>
                <span class="st-badge info">زرٌّ واحد</span>
            </div>
            <div class="body">
                {{-- ما يفعله الزرُّ في فقرة، ثمّ الزرُّ نفسُه. كان بينهما تنبيهان
                     وقائمةٌ من أربعة بنود، فيُمرَّر نصفُ شاشةٍ قبل بلوغ الزرّ. --}}
                <p class="su-lead">
                    يشغّل <b>الترحيلات المعلّقة</b> أوّلاً (الأعمدة والجداول والحقول الجديدة)، ثم
                    <b>المهامّ الإضافية</b>: الصلاحيات الجديدة كي تظهر شاشاتها، ومفاتيح الإعدادات
                    الجديدة بقيمها المبدئية، والمهامّ المجدولة الجديدة. هذا هو الزرّ المقصود بعد
                    رفع ملفّات تحديث إلى الخادم.
                </p>

                @unless ($guest ?? false)
                    @include('sitting::system_update._run_controls', [
                        'action' => route('SystemUpdate.runAll'),
                        'label' => 'تشغيل التحديث كاملاً',
                        'icon' => 'zap',
                        'destructive' => $safety['destructive'],
                        'confirm' => $pendingCount.' ترحيلاً ثم '.$additiveCount.' مهمّة تُضيف الناقص فقط، بلا كتابة فوق أي قيمة قائمة. متابعة؟',
                    ])
                @endunless

                <div class="st-note warn">
                    <i class="feather icon-clock mt-1"></i>
                    <div>
                        <strong>يستغرق من ١٥ ثانية إلى دقيقة — والصفحة تبدو ساكنةً طوال ذلك.</strong>
                        <p>
                            لا تُحدِّث الصفحة ولا تُغلقها ولا تضغط الزرّ ثانية. تشغيل عشرات المهامّ
                            يستغرق وقتاً ولا يُرسل الخادم شيئاً قبل انتهائها كلِّها، فيبدو الأمر
                            كأنه لم يحدث. تظهر النتيجة أعلى الصفحة خطوةً خطوة حين ينتهي.
                        </p>
                    </div>
                </div>

                {{-- التفصيلُ لمن أراده: مطويٌّ كلوحات الإرشاد في بقيّة النظام --}}
                <details class="sl-guide su-more">
                    <summary><i class="feather icon-shield" aria-hidden="true"></i> ماذا يفعل هذا الزرّ بالتفصيل، وما الذي لا يمسّه؟</summary>
                    <div class="sl-guide__body">
                <div class="st-note info">
                    <i class="feather icon-shield mt-1"></i>
                    <div>
                        <strong>لا يكتب فوق بياناتك.</strong>
                        <p>
                            لا يُشغَّل في هذا الزرّ إلا ما فُحِص وثبت أنه <b>يُضيف الناقص فقط</b>
                            ({{ $additiveCount }} مهمّة من {{ count($tasks) }}). وكل مهمّة فيها احتمال الكتابة فوق قيمة
                            قائمة — إعادة تفعيل تطبيق عطّلتَه، أو اسم وحدة أو سعر شحن حرّرته، أو
                            ربط الحسابات المحاسبية — <b>مستبعَدة منه</b> وتبقى بزرّها اليدوي أدناه.
                        </p>
                    </div>
                </div>
                <ul>
                    <li>ما تكتبه بنفسك — فواتير، عملاء، أرصدة، إعدادات اخترتها — لا يُمَس.</li>
                    <li>الصلاحيات تُضاف ولا تُسحَب: المنح إضافيٌ لا مزامنة، فلا يفقد دورٌ صلاحية منحتَها.</li>
                    <li>إن سقط الترحيل تُوقَف بقيّة الخطوات — لا تُزرَع بيانات في قاعدة نصفِ مهجّرة.</li>
                    <li>تُؤخذ <b>نسخة قاعدة بيانات تلقائياً</b> قبل الترحيل، وتظهر في
                        <a href="{{ route('Backup.index') }}">شاشة النسخ الاحتياطي</a> باسم «أمان قبل التحديث».</li>
                </ul>
                    </div>
                </details>
            </div>
        </div>

        {{-- ══════════ الترحيلات ══════════ --}}
        @unless ($migrations['repository'])
            <div class="st-note dang">
                <i class="feather icon-alert-triangle mt-1"></i>
                <div>
                    <strong>جدول الترحيلات غير موجود.</strong>
                    <p>هذه قاعدة بيانات لم تُهجَّر قطّ. تشغيل الترحيلات سينشئ بنية النظام كاملةً.</p>
                </div>
            </div>
        @endunless

        <div class="st-card">
            <div class="head">
                <i class="feather icon-git-merge" style="color:#2b6cb0"></i>
                <h4>ترحيلات قاعدة البيانات</h4>
                @if ($pendingCount)
                    <span class="st-badge warn">{{ $pendingCount }} معلّق</span>
                @else
                    <span class="st-badge on">محدَّثة</span>
                @endif
                @if ($destructive)
                    <span class="st-badge dang">{{ $destructive }} يحذف بيانات</span>
                @endif
            </div>
            <div class="body">
                @if ($pendingCount === 0)
                    <div class="st-empty" style="padding:1.6rem 1rem">
                        <i class="feather icon-check-circle"></i>
                        بنية قاعدة البيانات مطابقة للشفرة — لا شيء ينتظر التنفيذ.
                    </div>
                @else
                    {{-- ── حصيلةُ الفحص: ثلاثة أرقام تُقرأ قبل أي شيء آخر ── --}}
                    <div class="su-scan">
                        <span class="su-chip safe"><b>{{ $safe }}</b> يضيف فقط</span>
                        <span class="su-chip alter"><b>{{ $altering }}</b> يعدّل بنية عمود</span>
                        <span class="su-chip destroy"><b>{{ $destructive }}</b> يحذف بيانات</span>
                    </div>

                    @if ($destructive)
                        <div class="st-note dang">
                            <i class="feather icon-alert-octagon mt-1"></i>
                            <div>
                                <strong>{{ $destructive }} من الترحيلات المعلّقة يحذف بياناتٍ موجودةً فعلاً في قاعدتك — ولن تُشغَّل.</strong>
                                <p>
                                    هذه الشاشة لا تحذف عموداً ولا جدولاً ولا صفّاً بحال، ولا خيارَ لتجاوز ذلك.
                                    يُنفَّذ المعلَّق بالترتيب حتى <b>أوّل</b> ترحيلٍ يحذف، ثم يقف عنده — وما بعده
                                    يبقى معلَّقاً كذلك، لأن تشغيل اللاحق قبل السابق يبني القاعدة على أساسٍ لم يوضع.
                                    <br>
                                    التفصيل تحت كل ملفٍّ في القائمة أدناه: الجدولُ المقصود وعدد صفوفه. والأعداد
                                    مقيسةٌ على قاعدتك أنت — ما كان بلا أثرٍ هنا لا يُحسب.
                                    وإن كان الحذف مقصوداً من التحديث فهو قرارُ من كتبه، يُراجَع معه ويُشغَّل من
                                    سطر الأوامر بعد نسخةٍ احتياطية.
                                </p>
                            </div>
                        </div>
                    @elseif ($altering)
                        <div class="st-note warn">
                            <i class="feather icon-alert-triangle mt-1"></i>
                            <div>
                                <strong>لا حذفَ في المعلَّق — لكنّ {{ $altering }} منها يعيد تعريف عمودٍ قائم.</strong>
                                <p>
                                    تغييرُ نوع عمودٍ أو طوله أو اسمه لا يحذف صفّاً، لكن تضييق النوع
                                    يقصّ ما لا يتّسع فيه. النسخة الاحتياطية تُؤخذ تلقائياً قبل التشغيل.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="st-note good">
                            <i class="feather icon-shield mt-1"></i>
                            <div>
                                <strong>كل المعلَّق يُضيف فقط.</strong>
                                <p>
                                    لا حذفَ عمودٍ ولا جدولٍ ولا صفوف، ولا إعادةَ تعريفٍ لعمودٍ قائم —
                                    إنشاءُ جداولَ وإضافةُ أعمدةٍ وفهارس. بياناتك المخزَّنة لا تُمَسّ.
                                </p>
                            </div>
                        </div>
                    @endif

                    <p class="su-lead" style="margin:.9rem 0 .6rem">
                        الملفّات التالية لم تُنفَّذ بعد، وستُنفَّذ بترتيبها هذا:
                    </p>

                    {{-- إطارٌ بتمريرٍ داخليّ: خمسون ملفّاً معلَّقاً كانت تدفع زرَّ
                         التشغيل شاشتَين إلى الأسفل --}}
                    <div class="su-miglist">

                    @foreach ($safety['files'] as $file)
                        @php
                            $parts = $splitName($file['name']);
                            $meta = $levelMeta[$file['level']];
                            // ما ثبت أنه بلا أثرٍ على هذه القاعدة لا يُعرَض تحذيراً:
                            // تحذيرٌ لا يقابله فقدٌ هو ما يُعلِّم تجاهُلَ التحذيرات
                            $notable = array_filter($file['operations'], fn ($op) => $op['applies']);
                        @endphp
                        <div class="su-mig {{ $file['level'] }}">
                            <span class="lvl" style="color:{{ $meta['color'] }};background:{{ $meta['bg'] }}">
                                {{ $meta['label'] }}
                            </span>
                            @if ($parts['stamp'])
                                <span class="stamp">{{ $parts['stamp'] }}</span>
                            @endif
                            <span class="body">{{ $parts['body'] }}</span>
                        </div>

                        @if ($file['unreadable'])
                            <div class="su-op">تعذّرت قراءة الملفّ — لم يُفحَص محتواه.</div>
                        @endif

                        @foreach ($notable as $op)
                            @php
                                // النصّ يُركَّب هنا لا في القالب: تركيبه بـ@if
                                // متداخلة يُدخل سطراً وإزاحةً بين كل جزأين،
                                // فيخرج السطر مقطَّعاً بفراغاتٍ عريضة
                                $line = $op['label'];

                                if ($op['columns']) {
                                    $line .= ' «'.implode('»، «', $op['columns']).'»';
                                }
                            @endphp
                            <div class="su-op {{ $op['level'] }}">
                                {{ $line }}@if ($op['table']) في الجدول <code>{{ $op['table'] }}</code>@endif
                                @if ($op['rows'] !== null){{ $op['scope'] === 'filtered'
                                    ? '— الصفوف المطابقة لشرطٍ فيه (الجدول فيه '.number_format($op['rows']).' صفّاً)'
                                    : '— وفيه '.number_format($op['rows']).' صفّاً' }}@endif
                                @if ($op['note'])<em>{{ $op['note'] }}</em>@endif
                            </div>
                        @endforeach
                    @endforeach
                    </div>

                    {{-- [إقفال-تحديث-النظام] كان مغلَّفاً بـ
                         @permission('settings.system_update.migrate') --}}
                    @include('sitting::system_update._run_controls', [
                        'action' => route('SystemUpdate.migrate'),
                        'label' => 'تشغيل الترحيلات',
                        'icon' => 'play',
                        'destructive' => $safety['destructive'],
                        'confirm' => $safety['destructive']
                            ? 'سيُنفَّذ المعلَّق حتى أوّل ترحيلٍ يحذف بيانات ثم يقف عنده. متابعة؟'
                            : 'سيُشغَّل '.$pendingCount.' ترحيلاً — كلُّها بلا حذف. متابعة؟',
                    ])
                @endif
            </div>
        </div>

        {{-- ══════════ تحديث البيانات ══════════ --}}
        {{-- [إقفال-تحديث-النظام] كان: hasPermission('settings.system_update.seed') --}}
        {{-- والزائر (فُتحت له الشاشة لأن ترحيلاً معلّق) يشغّل الترحيلات وحدها --}}
        {{-- مهامُّ البيانات أُعيدت خلف صلاحيتها (2026-10-06): المتحكّمُ يرفضها
             لمن لا يحملها، فلا يُعرض له زرٌّ يُردّ عنده --}}
        @php
            $canSeed = ! ($guest ?? false) && hasPermission('settings.system_update.seed');
        @endphp

        @if ($guest ?? false)
            <div class="st-note info">
                <i class="feather icon-log-in mt-1"></i>
                <div>
                    <strong>مهامّ تحديث البيانات تتطلّب تسجيل الدخول.</strong>
                    <p>
                        شغّل «الترحيلات» أعلاه أوّلاً — بها تُضاف الأعمدة والجداول الجديدة ويعود
                        النظام إلى العمل. ثم ادخل بحسابك وأعد فتح هذه الشاشة لإكمال بقيّة التحديث.
                    </p>
                </div>
            </div>
        @endif

        @foreach ($groups as $groupKey => $group)
            @php
                $groupTasks = array_filter($tasks, fn ($t) => ($t['group'] ?? null) === $groupKey);
            @endphp

            @continue($groupTasks === [])

            <div class="st-card">
                <div class="head">
                    <i class="feather icon-{{ $group['icon'] }}" style="color:#2b6cb0"></i>
                    <h4>{{ $group['label'] }}</h4>
                    <span class="count">{{ count($groupTasks) }}</span>
                </div>
                <div class="body">
                    <p style="font-size:.82rem;color:#6b7280;margin:0 0 .3rem">{{ $group['hint'] }}</p>

                    @foreach ($groupTasks as $key => $task)
                        @php
                            $guardTable = $task['guard_table'] ?? null;
                            $guardRows = $guardTable ? ($guards[$guardTable] ?? null) : null;
                            // `null` يعني جدولاً غير موجود — ترحيلٌ لم يُشغَّل بعد،
                            // لا جدولاً ممتلئاً. الزرّ يبقى مفتوحاً والسيدر يشرح
                            $locked = $guardTable !== null && $guardRows !== null && $guardRows > 0;
                        @endphp

                        <div class="su-task">
                            <div class="txt">
                                <b>
                                    {{ $task['label'] }}
                                    @if ($task['recommended'] ?? false)
                                        <span class="st-badge info">مُوصى به</span>
                                    @endif
                                    @if ($locked)
                                        <span class="st-badge off">مزروعة</span>
                                    @endif
                                    @if (! empty($task['additive']))
                                        <span class="st-badge on">تُضيف الناقص فقط</span>
                                    @endif
                                </b>
                                <p>{{ $task['description'] }}</p>
                                @if (! empty($task['caution']))
                                    <p class="caution"><i class="feather icon-alert-triangle"></i> {{ $task['caution'] }}</p>

                                @elseif (! empty($task['readonly']))
                                    {{-- خطوةٌ تقرأ ولا تكتب: تحذيرُ «قد تُعيد قيمةً إلى المصنع» كذبٌ عليها --}}
                                    <p class="readonly">
                                        <i class="feather icon-eye"></i>
                                        عرضٌ فقط — لا تكتب في قاعدة البيانات شيئاً. ولذلك هي خارج «التحديث الشامل».
                                    </p>
                                @elseif (empty($task['additive']) && empty($task['guard_table']))
                                    {{-- بلا وسم additive: فيها كتابة فوق صفّ قائم. يُقال صراحةً
                                         بدل أن يُترَك المستخدم يخمّن لماذا لم يشغّلها الشامل --}}
                                    <p class="caution">
                                        <i class="feather icon-edit-2"></i>
                                        قد تُعيد قيمة غيّرتَها إلى قيمة المصنع — لذلك لا تعمل ضمن
                                        «التحديث الشامل»، وتشغيلها قرار منفرد.
                                    </p>
                                @endif
                                @if ($locked)
                                    <p class="caution">
                                        <i class="feather icon-lock"></i>
                                        جدول <span class="st-mono">{{ $guardTable }}</span> فيه
                                        {{ number_format($guardRows) }} صفّاً — إعادة التشغيل تُكرّرها لا تحدّثها.
                                    </p>
                                @endif
                            </div>
                            <div class="act">
                                @if ($canSeed)
                                    <form method="POST" action="{{ route('SystemUpdate.seed') }}" class="su-form" style="margin:0">
                                        @csrf
                                        <input type="hidden" name="task" value="{{ $key }}">
                                        <button type="submit" class="btn btn-outline-primary btn-sm" {{ $locked ? 'disabled' : '' }}
                                            data-confirm="تشغيل «{{ $task['label'] }}»؟{{ ! empty($task['caution']) ? ' تنبيه: ' . $task['caution'] : '' }}">
                                            <i class="feather icon-refresh-cw"></i> تشغيل
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endsection

@section('scripts')
    <style>
        /* غطاءُ انتظارٍ يملأ الشاشة.

           كان قفلُ الزرّ وتبديلُ نصّه كلَّ ما يقع، والطلب يستغرق قرابة نصف
           دقيقة (عشرات السيدرات) لا يُرسل الخادم في أثنائها بايتاً واحداً.
           فالصفحة تبقى كما هي بلا حراك، فيستنتج المستخدم أن الزرّ «لا يسوّي
           شيئاً» — ثم يُحدّث الصفحة أو ينتقل عنها، فيُقطع الطلبُ وتضيع رسالةُ
           النتيجة (فلاش) معه. لا عطلَ في المنفّذ إذن، بل عطلٌ في الإخبار. */
        .su-overlay {
            position: fixed; inset: 0; z-index: 2000;
            display: none; align-items: center; justify-content: center;
            background: rgba(17, 24, 39, .55);
            backdrop-filter: blur(2px);
        }

        .su-overlay.is-on { display: flex; }

        .su-overlay__box {
            background: #fff; border-radius: .75rem;
            padding: 1.6rem 2rem; max-width: 420px; text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .3);
        }

        .su-overlay__box h5 { margin: .8rem 0 .4rem; font-weight: 700; color: #111827; }
        .su-overlay__box p { margin: 0; font-size: .85rem; color: #6b7280; line-height: 1.8; }

        .su-spin {
            width: 42px; height: 42px; margin: 0 auto;
            border: 4px solid #e5e7eb; border-top-color: #2b6cb0;
            border-radius: 50%; animation: su-rot .9s linear infinite;
        }

        @keyframes su-rot { to { transform: rotate(360deg); } }

        @media (prefers-reduced-motion: reduce) {
            .su-spin { animation-duration: 3s; }
        }
    </style>

    <div class="su-overlay" id="suOverlay" role="alert" aria-live="assertive">
        <div class="su-overlay__box">
            <div class="su-spin"></div>
            <h5>جارٍ التنفيذ…</h5>
            <p>
                قد يستغرق حتى دقيقة. لا تُغلق الصفحة ولا تُحدّثها —
                إغلاقُها يقطع التنفيذ ويُضيع تقرير النتيجة.
            </p>
        </div>
    </div>

    <script>
        // تأكيدٌ واحد لكل زرّ، ثم قفلُ النموذج: أمرُ الترحيل يستغرق ثوانيَ
        // بلا أثرٍ في الشاشة، والنقرة الثانية تشغّله مرّتين
        (function () {
            var overlay = document.getElementById('suOverlay');
            var running = false;

            document.querySelectorAll('.su-form').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    // نموذجٌ ثانٍ بينما الأوّل يعمل يقطع الأوّل — والمستخدم
                    // لا يرى أن شيئاً يجري أصلاً، فالمنع هنا لا في وعيه
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
                        button.classList.add('su-busy');
                        button.disabled = true;
                        button.innerHTML = '<i class="feather icon-loader"></i> جارٍ التنفيذ…';
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

            // تحذيرُ المتصفّح عند محاولة الإغلاق أثناء التنفيذ
            window.addEventListener('beforeunload', function (event) {
                if (running) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        })();
    </script>
@endsection
