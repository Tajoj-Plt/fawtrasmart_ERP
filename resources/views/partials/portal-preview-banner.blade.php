{{--
    شريط «أنت تعاين بوابة عميل».

    مُضمَّنٌ في قالب البوابة لا في كل شاشةٍ على حدة: عدّةُ شاشاتٍ تمتدّ منه،
    ونسيانُ واحدةٍ يعني موظّفاً يتصفّح بحساب عميلٍ وهو يظنّ نفسه في حسابه —
    وأخطرُ ما في الانتحال أن يُنسى.

    زرّ العودة نموذجُ POST لا رابط: تغييرُ صاحب الجلسة فعلٌ لا قراءة، فلا
    يصحّ أن يقع بزيارة رابطٍ من التاريخ أو من جلبٍ مسبقٍ للمتصفّح.

    مسار `clients.portal.stop` مستثنىً في `ClientAccessMiddleware` — راجعه
    قبل تغيير اسم المسار هنا، وإلّا صار الشريط زرّاً يعيد صاحبَه إلى نفسه.

    ══════════ لماذا لونُ التحذير؟ ══════════

    الشريطُ تحذيرٌ لا زينة: من يراه يكتب باسم غيره. فلونُه **لونُ التحذير في
    «مظهر النظام»** لا برتقاليٌّ مكتوبٌ هنا (كان `#ff9f43` ثابتاً، فيخالف لونَ
    المنشأة ويبقى برتقالياً مهما غُيّرت الألوان). والنصُّ يُحسب على اللون
    نفسِه بـ`readableOn` فيبقى مقروءاً مهما كان التحذيرُ فاتحاً أو داكناً.
--}}
@php
    use App\Support\ColorTools;
    use App\Support\ThemeSettings;

    $previewClientName = session(\Modules\Client\Http\Controllers\ClientPortalPreviewController::SESSION_LABEL);

    $previewBg = ThemeSettings::palette()['--app-warning'] ?? '#ff9f43';
    $previewText = ColorTools::readableOn($previewBg);
    // حرفُ الزرّ بلون التحذير مغمَّقاً على أرضيةٍ من نصِّ الشريط — تباينٌ في الحالتين
    $previewBtnText = ColorTools::darken($previewBg, 0.45);
@endphp

@if (session()->has(\Modules\Client\Http\Controllers\ClientPortalPreviewController::SESSION_KEY))
    <style>
        .portal-preview-bar {
            display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
            margin: 0 0 1rem;
            padding: .7rem 1rem;
            border-radius: var(--app-radius, .5rem);
            background: {{ $previewBg }};
            color: {{ $previewText }};
            box-shadow: 0 2px 10px color-mix(in srgb, {{ $previewBg }} 35%, transparent);
        }

        .portal-preview-bar__text { font-weight: 600; margin: 0; flex: 1 1 auto; min-width: 0; }
        .portal-preview-bar__text small { display: block; font-weight: 400; opacity: .9; }

        .portal-preview-bar .btn {
            background: {{ $previewText }}; color: {{ $previewBtnText }}; border: 0;
            font-weight: 700; white-space: nowrap;
        }

        .portal-preview-bar .btn:hover {
            background: color-mix(in srgb, {{ $previewText }} 92%, {{ $previewBg }});
            color: {{ $previewBtnText }};
        }
    </style>

    <div class="portal-preview-bar" role="status">
        <i class="fa fa-eye" aria-hidden="true"></i>

        <p class="portal-preview-bar__text">
            أنت تعاين البوابة بحساب العميل{{ $previewClientName ? ' «'.$previewClientName.'»' : '' }}.
            <small>هذه هي الشاشة كما يراها هو تماماً — أي تعديلٍ تحفظه يُحفظ باسمه.</small>
        </p>

        <form action="{{ route('clients.portal.stop') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm">
                <i class="fa fa-sign-out-alt"></i> العودة إلى حسابي
            </button>
        </form>
    </div>
@endif
