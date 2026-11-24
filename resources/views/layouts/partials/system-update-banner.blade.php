{{--
    شريط «يوجد تحديثٌ معلَّق» — يظهر في أعلى كل شاشة.

    مصدر `$systemUpdatePending` هو `App\Http\Middleware\CheckSystemUpdates`
    الذي يشاركه مع كل قالب. فمتى غاب المتغيّر (شاشةٌ خارج مجموعة `web`،
    أو زائرٌ غير مسجَّل) لا يظهر شيء — ولا يُستدعى فحصٌ من القالب.

    ومتى ظهر أصلاً؟ الوسيط يحوّل إلى شاشة التحديث مباشرة، فلا يبقى هذا
    الشريط إلا لمن ضغط «متابعة الآن» ومهلتُه سارية — تذكيرٌ بأن التأجيل
    تأجيل، لا إلغاء.
--}}
@if (! empty($systemUpdatePending) && \Illuminate\Support\Facades\Route::has('SystemUpdate.index'))
    <div class="sysup-bar">
        <i class="feather icon-alert-triangle"></i>
        <span class="sysup-txt">
            <b>يوجد تحديثٌ للنظام لم يُنفَّذ.</b>
            {{ $systemUpdatePending }} ترحيلاً بانتظار التشغيل — قد تُعطي الشاشات الجديدة
            أخطاءً أو تُخفي حقولاً حتى يُشغَّل التحديث.
        </span>
        <a href="{{ route('SystemUpdate.index') }}" class="sysup-btn">
            <i class="feather icon-download"></i> تشغيل التحديث
        </a>
    </div>

    <style>
        .sysup-bar {
            display: flex; align-items: center; gap: .7rem; flex-wrap: wrap;
            margin: .6rem 1.5rem 0; padding: .7rem 1rem;
            background: #fdf7e8; border: 1px solid #f0dcae; border-radius: 10px;
            color: #7d5f0e; font-size: .86rem; line-height: 1.7;
        }
        .sysup-bar .sysup-txt { flex: 1; min-width: 12rem; }
        .sysup-bar b { font-weight: 700; }
        .sysup-btn {
            flex: 0 0 auto; background: #7d5f0e; color: #fff !important;
            border-radius: 8px; padding: .35rem .85rem; font-size: .82rem;
            font-weight: 600; text-decoration: none; white-space: nowrap;
        }
        .sysup-btn:hover { background: #644b09; color: #fff; }
    </style>
@endif
