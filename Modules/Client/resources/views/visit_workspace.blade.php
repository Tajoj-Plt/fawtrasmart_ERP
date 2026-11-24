@extends('client::master')

@section('title')
    إتمام الزيارة — {{ $client->trade_name }}
@stop

@section('content')
    @include('client::partials.settings-theme')
    @include('components.hint-tips')

    @php
        // إظهار/إخفاء أقسام هذه الشاشة — إعدادات وحدات العملاء ← الزيارات
        // ← «أقسام مساحة عمل الزيارة». الافتراض ظاهرٌ للجميع.
        $wsShow = fn (string $key) => \App\Helpers\ClientSettingsHelper::visitWorkspaceSectionVisible($key);

        // روابط الفاتورة/السند/الإشعار هنا إلزاميّة الظهور — نفس معاملة
        // clients.show (`client_show_actions_settings` لم تعد تحكمها).

        // نفس صلاحية راوت `clients.visits.storeNote` (أُضيفت له تواً؛ كانت
        // غائبةً فيضيف أيّ متتبّع زياراتٍ ملاحظةً بلا صلاحية الملاحظات نفسها) —
        // أزرار فتح المودال هنا يجب أن تختفي لنفس من لا يملكها، لا الميدلوير وحده.
        $canAddNote = hasPermission('client_follow_up_add_notes_attachments_appointments_all')
            || hasPermission('client_follow_up_add_notes_attachments_appointments_assigned')
            || hasPermission('client_follow_up_add_notes_attachments_appointments_own')
            || hasPermission('clients_add_employee_note');
    @endphp

    <style>
        {{--
            إعادة تصميم — الإطار فقط، لا البنية: البطاقاتُ الإحدى عشرة
            والتبويباتُ الستة والمودالاتُ الستة تبقى كما هي حرفياً؛ ما تغيّر
            هنا هو مصدر كل لونٍ وظلٍّ وزاوية.

            `--ws-*` أسماءُ المتغيّرات نفسُها من قبل — كل الأسطر التي
            تستعملها أدناه بلا تغيير — لكنّ قيمها الآن أسماءٌ مستعارة
            لرموز `--app-*`/`--cl-*` من `client::partials.settings-theme`
            بدل ألوانٍ مثبَّتة، على نفس نمط `public/assets/css/show_client.css`
            («صارت أسماءً مستعارةً لرموز المظهر — بلا تغييرٍ في أيّ قاعدةٍ
            تقرؤها»).

            بطاقات هذه الصفحة (.card) وأزرارها (.btn) لم تعد تُنسَّق هنا
            إطلاقاً: `client::master` يُدرج الصفحة كلَّها داخل `.cl-scope`
            (راجع تعليقه: «هذا موضعُ نقل settings-theme لبقية شاشات
            العملاء») — فقواعد `.cl-scope .card`/`.cl-scope .btn` نفسها
            تُطبَّق تلقائياً هنا، وتكرارُها محلياً كان يطغى عليها بلا داع.
            ما بقي أدناه عناصرُ هذه الشاشة فقط: المؤقّت والخطوات والتبويبات
            وقائمة الإجراءات السريعة وبطاقة الملاحظة وجدول الأصول وشبكة
            الصور وخيارات النتيجة ولوحة التوقيع — لا مقابل جاهز لها في
            النظام المشترك.
        --}}
        :root {
            --ws-primary: var(--app-primary, #4f46e5);
            --ws-primary-dark: color-mix(in srgb, var(--app-primary, #4f46e5) 82%, #000);
            --ws-success: var(--app-success, #16a34a);
            --ws-danger: var(--app-danger, #dc2626);
            --ws-warning: var(--app-warning, #d97706);
            --ws-border: var(--cl-line, #e5e7eb);
            --ws-muted: var(--app-muted, #6b7280);
            --ws-bg: var(--app-body-bg, #f8fafc);
        }
        .visit-ws { background: var(--ws-bg); margin: -1.5rem; padding: 1.5rem; }

        /* ===== رأس الزيارة ===== */
        .visit-ws .ws-header-card { background: var(--app-surface, #fff); border-radius: var(--cl-r-lg, 14px); padding: 1.1rem 1.4rem; box-shadow: var(--cl-e2); margin-bottom: 1rem; }
        .visit-ws .ws-header-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
        .visit-ws .ws-badge { font-weight: 600; }
        /* حقول الملفّ المخفيّة (كاميرا الملاحظة/مسؤول العميل/صورة المنافس) —
           `d-none` (`display:none`) كانت تجعل `input.click()` البرمجيّ لا يفتح
           منتقي الملفّ/الكاميرا أحياناً على متصفّحات جوّالٍ معيّنة (خاصّةً
           إصدارات سفاري القديمة): تجاهلٌ صامتٌ بلا أي خطأ ظاهر. هذا النمط
           («مخفيٌّ بصرياً» لا `display:none`) أكثر اعتماديّةً عبر المتصفّحات. */
        /* بلا بادئة `.visit-ws`: المودالات (الملاحظة، مسؤول العميل، رصد المنافسين) خارج حاويتها. */
        .ws-hidden-file-input { position: absolute !important; width: 1px !important; height: 1px !important; padding: 0 !important; margin: -1px !important; overflow: hidden !important; clip: rect(0,0,0,0) !important; white-space: nowrap !important; border: 0 !important; }
        /* شارات ومعلومات الرأس: مجموعاتٌ متجاورة (شارةٌ وزرّ تغييرها معاً)،
           تفصلها عن شارات الأدوات (رقم الزيارة، الرصيد، الهاتف، الخريطة) خطٌّ فاصل. */
        .visit-ws .ws-header-badges { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .visit-ws .ws-badge-group { display: flex; align-items: center; flex-wrap: wrap; gap: .35rem; }
        .visit-ws .ws-header-divider { width: 1px; align-self: stretch; min-height: 22px; background: var(--ws-border); }
        .visit-ws .ws-action-chip { font-size: .72rem; font-weight: 700; border-radius: 999px; padding: .2rem .6rem; border-width: 1.5px; display: inline-flex; align-items: center; gap: .3rem; }
        .visit-ws .ws-open-client { font-size: .78rem; color: var(--ws-primary); text-decoration: none; }
        .visit-ws .ws-open-client:hover { text-decoration: underline; }
        .visit-ws .ws-timer-block { text-align: center; }
        .visit-ws .ws-timer { font-size: 2rem; font-weight: 800; color: var(--ws-success); font-variant-numeric: tabular-nums; }
        .visit-ws .ws-stepper { display: flex; align-items: center; gap: .5rem; margin-top: 1.4rem; }
        .visit-ws .ws-step { display: flex; align-items: center; gap: .4rem; flex: 1; position: relative; }
        .visit-ws .ws-step:not(:last-child)::after { content: ''; flex: 1; height: 2px; background: var(--ws-border); margin: 0 .5rem; }
        .visit-ws .ws-step-dot { width: 26px; height: 26px; border-radius: 50%; background: var(--ws-border); color: var(--app-muted, #94a3b8); display: flex; align-items: center; justify-content: center; font-size: .75rem; flex-shrink: 0; }
        .visit-ws .ws-step.is-done .ws-step-dot { background: var(--ws-success); color: var(--app-on-primary, #fff); }
        .visit-ws .ws-step.is-current .ws-step-dot { background: var(--ws-primary); color: var(--app-on-primary, #fff); box-shadow: 0 0 0 4px var(--app-primary-soft, rgba(79,70,229,.15)); }
        .visit-ws .ws-step-label { font-size: .8rem; white-space: nowrap; color: var(--app-text, #475569); font-weight: 600; }

        /* ===== تبويبات الزيارة ===== */
        .visit-ws .ws-tabs { display: flex; gap: .25rem; background: var(--app-surface, #fff); border-radius: var(--cl-r-lg, 12px); padding: .4rem; margin-bottom: 1rem; flex-wrap: wrap; box-shadow: var(--cl-e2); }
        .visit-ws .ws-tabs button { border: none; background: transparent; padding: .55rem 1rem; border-radius: var(--cl-r, 8px); font-weight: 600; font-size: .85rem; color: var(--app-muted, #64748b); white-space: nowrap; transition: background .15s ease, color .15s ease; }
        .visit-ws .ws-tabs button:hover { background: var(--ws-bg); color: var(--ws-primary); }
        .visit-ws .ws-tabs button.active { background: var(--ws-primary); color: var(--app-on-primary, #fff); box-shadow: var(--cl-e2); }
        .visit-ws .ws-tabs .badge-new { background: var(--ws-warning); color: var(--app-on-primary, #fff); font-size: .65rem; margin-inline-start: .3rem; }

        /* ===== إجراءات سريعة ===== */
        /* `.cl-scope .card` تفرض `overflow: hidden` (لقصّ الزوايا الدائرية).
           كانت تقصّ قائمة «مستند جديد للعميل» المنسدلة؛ المستندات صارت أزراراً
           مباشرة، والاستثناء باقٍ لأيّ قائمةٍ منبثقة تُضاف لهذه البطاقة لاحقاً. */
        .visit-ws .ws-actions-card { overflow: visible; }
        /* وللسبب نفسه بطاقتا «مستندات هذه الزيارة»: قائمة «طباعة ▾» كانت
           تُقصّ داخل البطاقة شريطاً ضيّقاً بشريط تمرير بدل أن تطفو. */
        .visit-ws .ws-docs-card, .visit-ws .ws-docs-card .card-body { overflow: visible; }
        .visit-ws .ws-quick-list { list-style: none; margin: 0; padding: 0; }
        .visit-ws .ws-quick-list li { border-bottom: 1px solid var(--cl-line-soft); }
        .visit-ws .ws-quick-list li:last-child { border-bottom: none; }
        .visit-ws .ws-quick-list button, .visit-ws .ws-quick-list a { display: flex; align-items: center; gap: .6rem; width: 100%; padding: .65rem .4rem; border: none; border-radius: var(--cl-r, 8px); background: transparent; text-align: right; color: var(--app-text, #334155); font-size: .85rem; text-decoration: none; transition: background .15s ease, color .15s ease, padding-inline-start .15s ease; }
        .visit-ws .ws-quick-list button:hover, .visit-ws .ws-quick-list a:hover { background: var(--ws-bg); color: var(--ws-primary); padding-inline-start: .6rem; }
        .visit-ws .ws-quick-list i { width: 22px; text-align: center; color: var(--ws-primary); }
        .visit-ws .ws-quick-list button:disabled { opacity: .55; cursor: not-allowed; }
        .visit-ws .ws-quick-list button:disabled:hover { background: transparent; color: var(--app-text, #334155); padding-inline-start: .4rem; }
        .visit-ws .ws-quick-subhead { display: flex; align-items: center; gap: .4rem; margin-top: .6rem; padding: .35rem .4rem .15rem; border-top: 1px solid var(--cl-line-soft); font-size: .74rem; font-weight: 700; color: var(--app-muted, #6b7280); }

        /* ===== الموقع ===== */
        .visit-ws .ws-map { width: 100%; height: 130px; border-radius: var(--cl-r, 10px); background: var(--ws-bg); margin-bottom: .6rem; }
        .visit-ws .ws-loc-facts div { display: flex; justify-content: space-between; font-size: .8rem; padding: .15rem 0; }

        /* ===== الملاحظات ===== */
        .visit-ws .ws-note-card { border: 1px solid var(--ws-border); border-radius: var(--cl-r, 10px); padding: .6rem .75rem; margin-bottom: .6rem; transition: border-color .15s ease, background .15s ease; }
        .visit-ws .ws-note-card:hover { border-color: color-mix(in srgb, var(--app-primary, #4f46e5) 35%, transparent); background: var(--cl-tint); }
        .visit-ws .ws-note-card:last-child { margin-bottom: 0; }
        .visit-ws .ws-note-badge { background: var(--app-primary-soft, rgba(79,70,229,.12)); color: var(--ws-primary); font-size: .68rem; font-weight: 700; padding: .15rem .5rem; border-radius: var(--cl-r, 6px); }

        /* ===== شبكة الصور (تبويب المرفقات) ===== */
        .visit-ws .ws-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(85px, 1fr)); gap: .5rem; }
        .visit-ws .ws-photo-item { position: relative; display: block; border-radius: var(--cl-r, 8px); overflow: hidden; border: 1px solid var(--ws-border); transition: box-shadow .15s ease; }
        .visit-ws .ws-photo-item:hover { box-shadow: var(--cl-e3); }
        .visit-ws .ws-photo-item img { width: 100%; height: 75px; object-fit: cover; transition: transform .2s ease; }
        .visit-ws .ws-photo-item:hover img { transform: scale(1.06); }
        {{-- الأسود الشفّاف استثناءٌ صريح — نفس ما تسمح به settings-theme.blade.php («لا لون مثبَّت هنا إلا الأسود الشفّاف في الظلال») --}}
        .visit-ws .ws-photo-label { position: absolute; bottom: 0; inset-inline: 0; background: rgba(0,0,0,.55); color: #fff; font-size: .62rem; padding: .1rem .3rem; text-align: center; }

        /* ===== مسؤول العميل / التوقيع ===== */
        .visit-ws .ws-contact-avatar { width: 42px; height: 42px; border-radius: 50%; background: var(--ws-primary); color: var(--app-on-primary, #fff); display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; box-shadow: 0 2px 6px var(--app-primary-glow, rgba(79,70,229,.25)); }
        .visit-ws .ws-details-list div { display: flex; justify-content: space-between; font-size: .8rem; padding: .28rem 0; border-bottom: 1px dashed var(--cl-line-soft); }
        .visit-ws .ws-details-list div:last-child { border-bottom: none; }
        .visit-ws .ws-sig-pad { width: 100%; height: 90px; border: 2px dashed var(--ws-border); border-radius: var(--cl-r, 8px); background: var(--app-surface, #fff); touch-action: none; transition: border-color .15s ease; }
        .visit-ws .ws-sig-pad:hover { border-color: var(--ws-primary); }
        .visit-ws .ws-thumb { width: 70px; height: 70px; object-fit: cover; border-radius: var(--cl-r, 8px); border: 1px solid var(--ws-border); transition: transform .15s ease; }
        .visit-ws .ws-thumb:hover { transform: scale(1.05); }
        .visit-ws .empty-state { padding: 2.5rem 1rem; text-align: center; color: var(--app-muted, #94a3b8); }
        .visit-ws .empty-state i { font-size: 2.2rem; margin-bottom: .6rem; display: block; color: color-mix(in srgb, var(--app-muted, #94a3b8) 60%, transparent); }

        /* ===== بدء الزيارة (قبل بدء المؤقّت) ===== */
        .visit-ws #startVisitBtn { border-radius: var(--cl-r, 8px); font-weight: 700; transition: opacity .15s ease, filter .15s ease; }
        .visit-ws #startVisitBtn:disabled { opacity: .55; }
        .visit-ws #startLocationStatus { font-size: .78rem; }

        /* ===== القفل قبل بدء الزيارة =====
           لا إجراءَ في هذه الشاشة قبل الضغط على «بدء الزيارة» من موقع
           العميل: البيانات هنا (ملاحظة، جرد عهدة، رصد منافس، نتيجة) لا
           معنى لها بلا موقعٍ يسندها، و`started_at` هو الدليل الوحيد على
           ذلك الموقع. التعطيل الفعلي في JS أسفل الصفحة (`wsApplyLock`) —
           هذه القواعد تجعله مرئياً فقط. البوّابة الحقيقية في الخادم:
           `VisitWorkspaceController::blockUntilStarted()`. */
        .visit-ws .ws-lock-banner { display: flex; align-items: center; gap: .6rem; background: color-mix(in srgb, var(--ws-warning) 12%, var(--app-surface, #fff)); border: 1px solid color-mix(in srgb, var(--ws-warning) 45%, transparent); border-radius: var(--cl-r-lg, 12px); padding: .75rem 1rem; margin-bottom: 1rem; font-size: .85rem; font-weight: 600; color: var(--app-text, #334155); }
        .visit-ws .ws-lock-banner i { font-size: 1.15rem; color: var(--ws-warning); }
        .visit-ws.is-locked .ws-tab-pane .card-header { color: var(--ws-muted); }
        .visit-ws .ws-locked-el { pointer-events: none; cursor: not-allowed; opacity: .6; text-decoration: none; }

        /* شريط النتيجة المحسوبة أسفل الخطوات، وقفلُ الخطوات غير المفتوحة
           بعد — كلاهما بألوان `--ws-*` نفسها لا بألوانٍ مثبَّتة. */
        .visit-ws .ws-outcome-preview { display: flex; align-items: center; gap: .55rem; margin-top: .75rem; padding: .55rem .85rem; border-radius: var(--cl-r-lg, 12px); font-size: .82rem; font-weight: 600; border: 1px solid transparent; }
        .visit-ws .ws-outcome-preview i { font-size: 1.05rem; }
        .visit-ws .ws-outcome-preview.is-success { background: color-mix(in srgb, var(--ws-success) 12%, var(--app-surface, #fff)); border-color: color-mix(in srgb, var(--ws-success) 40%, transparent); color: var(--app-text, #334155); }
        .visit-ws .ws-outcome-preview.is-success i { color: var(--ws-success); }
        .visit-ws .ws-outcome-preview.is-negative { background: color-mix(in srgb, var(--ws-warning) 12%, var(--app-surface, #fff)); border-color: color-mix(in srgb, var(--ws-warning) 40%, transparent); color: var(--app-text, #334155); }
        .visit-ws .ws-outcome-preview.is-negative i { color: var(--ws-warning); }

        /* بطاقةٌ لخطوةٍ لم يحن دورها بعد: مرئيّةٌ ليعرف المندوب ما ينتظره،
           معطّلةٌ فعلاً بـ`inert` لا بالمظهر وحده. */
        .visit-ws .ws-step-locked { position: relative; opacity: .55; }
        .visit-ws .ws-step-locked::after { content: ''; position: absolute; inset: 0; border-radius: inherit; background: transparent; }
        .visit-ws .ws-step-badge { display: inline-flex; align-items: center; justify-content: center; width: 1.35rem; height: 1.35rem; border-radius: 50%; background: var(--ws-primary); color: #fff; font-size: .72rem; font-weight: 700; margin-inline-end: .35rem; }
        .visit-ws .ws-step-badge.is-done { background: var(--ws-success); }
        .visit-ws .ws-step-badge.is-locked { background: var(--ws-muted); }
        .visit-ws .ws-contact-row { display: flex; align-items: center; gap: .5rem; padding: .45rem 0; border-bottom: 1px dashed color-mix(in srgb, var(--ws-muted) 35%, transparent); }
        .visit-ws .ws-contact-row:last-child { border-bottom: 0; }
        /* شريط «يجب» وإبرازُ البطاقة الناقصة — بديلا نافذة التنبيه. */
        .visit-ws .ws-required-banner { position: sticky; top: .5rem; z-index: 20; display: flex; align-items: center; gap: .6rem; background: color-mix(in srgb, var(--ws-danger) 12%, var(--app-surface, #fff)); border: 1px solid color-mix(in srgb, var(--ws-danger) 50%, transparent); border-radius: var(--cl-r-lg, 12px); padding: .7rem 1rem; margin-bottom: 1rem; font-size: .86rem; font-weight: 700; color: var(--app-text, #334155); box-shadow: var(--cl-e3, 0 4px 14px rgba(0,0,0,.08)); }
        .visit-ws .ws-required-banner i { font-size: 1.15rem; color: var(--ws-danger); }

        .visit-ws .ws-needs-action { outline: 2px solid var(--ws-danger); outline-offset: 3px; animation: wsNeedsPulse 1.1s ease-in-out 3; }
        @keyframes wsNeedsPulse {
            0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--ws-danger) 45%, transparent); }
            50% { box-shadow: 0 0 0 .5rem color-mix(in srgb, var(--ws-danger) 0%, transparent); }
        }

        .visit-ws .ws-doc-row { display: flex; align-items: center; gap: .5rem; padding: .45rem 0; border-bottom: 1px solid var(--app-border, #eef0f3); }
        .visit-ws .ws-doc-row:last-child { border-bottom: 0; }
        /* تبويب «تسليم واستلام الفواتير» */
        .visit-ws .ws-wt-summary { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
        .visit-ws .ws-wt-summary > div { flex: 1 1 140px; background: var(--ws-bg); border-radius: var(--cl-r, 8px); padding: .55rem .75rem; display: flex; flex-direction: column; gap: .15rem; }
        .visit-ws .ws-wt-summary span { font-size: .75rem; color: var(--app-muted, #64748b); }
        .visit-ws .ws-wt-summary strong { font-size: .95rem; }
        .visit-ws .ws-wt-summary .is-warn { background: color-mix(in srgb, var(--ws-warning) 14%, transparent); }
        .visit-ws .ws-wt-table tr.is-mine td:first-child { border-inline-start: 3px solid var(--ws-primary); }
        .visit-ws .ws-wt-banner { display: flex; align-items: center; gap: .85rem; padding: .85rem 1rem; margin-bottom: 1rem; border-radius: var(--cl-r-lg, 12px); background: color-mix(in srgb, var(--ws-warning) 14%, var(--app-surface, #fff)); border: 1px solid color-mix(in srgb, var(--ws-warning) 45%, transparent); }
        .visit-ws .ws-wt-banner > i { font-size: 1.4rem; color: var(--ws-warning); }
        .visit-ws .ws-wt-banner strong { display: block; }
        .visit-ws .ws-wt-banner span { font-size: .85rem; color: var(--app-muted, #64748b); }
        .visit-ws .ws-wt-inbox { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; padding: .85rem 1rem; margin-bottom: 1rem; border-radius: var(--cl-r, 8px); border: 1px solid color-mix(in srgb, var(--ws-warning) 45%, transparent); border-inline-start: 4px solid var(--ws-warning); background: color-mix(in srgb, var(--ws-warning) 8%, transparent); }
        .visit-ws .ws-wt-inbox__text { flex: 1 1 280px; }
        .visit-ws .ws-wt-inbox__text strong { display: block; margin-bottom: .2rem; }
        .visit-ws .ws-wt-inbox__text span { font-size: .85rem; color: var(--app-muted, #64748b); }
        .visit-ws .ws-wt-inbox__actions { display: flex; gap: .5rem; }
        .visit-ws .ws-wt-pending { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }
        .visit-ws .ws-print-menu { max-height: 60vh; overflow-y: auto; min-width: 16rem; }
        .visit-ws .ws-doc-icon { width: 30px; height: 30px; flex: 0 0 30px; border-radius: 8px; display: grid; place-items: center; background: color-mix(in srgb, var(--ws-primary) 12%, transparent); color: var(--ws-primary); font-size: .85rem; }

        .visit-ws .ws-return-box { border: 1px dashed color-mix(in srgb, var(--ws-primary) 45%, transparent); border-radius: var(--cl-r, 10px); padding: .6rem .7rem; font-size: .82rem; background: color-mix(in srgb, var(--ws-primary) 6%, var(--app-surface, #fff)); }

        /* إشعارات الإدارة عن العميل — بطاقةٌ فوق الخطوات لا شارةٌ في تبويب:
           هي توجيهٌ يُقرأ قبل الدخول على العميل، لا سجلٌّ يُراجَع بعده. */
        .visit-ws .ws-notif-card { border: 1px solid var(--app-border, #e5e7eb); border-radius: var(--cl-r, 12px); background: var(--app-surface, #fff); padding: .85rem 1rem; margin-bottom: 1rem; }
        .visit-ws .ws-notif-card.has-unread { border-color: #f59e0b; background: color-mix(in srgb, #f59e0b 7%, var(--app-surface, #fff)); }
        .visit-ws .ws-notif-head { display: flex; align-items: center; gap: .5rem; font-weight: 700; font-size: .9rem; margin-bottom: .6rem; }
        .visit-ws .ws-notif-item { border-top: 1px solid color-mix(in srgb, var(--app-border, #e5e7eb) 70%, transparent); padding: .6rem 0; }
        .visit-ws .ws-notif-item:first-of-type { border-top: 0; padding-top: 0; }
        .visit-ws .ws-notif-item.is-unread .ws-notif-title { color: #b45309; }
        .visit-ws .ws-notif-title { font-weight: 700; font-size: .86rem; margin-bottom: .15rem; }
        .visit-ws .ws-notif-meta { font-size: .74rem; color: var(--app-muted, #6b7280); margin-bottom: .3rem; }
        .visit-ws .ws-notif-body { font-size: .85rem; white-space: pre-wrap; word-break: break-word; }
        .visit-ws .ws-notif-reply { font-size: .78rem; border-inline-start: 3px solid var(--ws-primary); padding-inline-start: .5rem; margin-top: .4rem; color: var(--app-muted, #6b7280); }
        .visit-ws .ws-notif-actions { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .45rem; }
        /* ===== نوافذ مضغوطة: موظف العميل، الملاحظة، رصد المنافسين =====
           طلب المستخدم (2026-09-15): «قلّصها ورتّب تصميم مودال المنافس». */
        .ws-compact-modal .modal-content { border-radius: 14px; border: 1px solid var(--ws-border); overflow: hidden; }
        /* نافذةٌ قابلة للتمرير و`<form>` يلفّ رأسها وجسمها وتذييلها: بلا هذا لا يرث الجسم حدَّ
           الارتفاع فيُقصّ المحتوى بلا تمرير — على الجوال بعد رفع المرفق يختفي زرّ «حفظ». */
        .ws-compact-modal .modal-dialog-scrollable { max-height: calc(100% - 1rem); }
        .ws-compact-modal .modal-dialog-scrollable .modal-content { max-height: calc(100dvh - 1rem); }
        .ws-compact-modal .modal-dialog-scrollable .modal-content > form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; max-height: 100%; }
        .ws-compact-modal .modal-dialog-scrollable .modal-header,
        .ws-compact-modal .modal-dialog-scrollable .modal-footer { flex-shrink: 0; }
        .ws-compact-modal .modal-dialog-scrollable .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
        .ws-compact-modal .modal-header { padding: .6rem 1rem; border-bottom: 1px solid var(--ws-border); background: var(--app-surface, #fff); }
        .ws-compact-modal .modal-title { font-size: .98rem; font-weight: 800; display: flex; align-items: center; gap: .35rem; }
        .ws-compact-modal .modal-body { padding: .8rem 1rem; }
        .ws-compact-modal .modal-footer { padding: .5rem 1rem; gap: .4rem; background: var(--ws-bg); border-top: 1px solid var(--ws-border); }
        .ws-compact-modal .modal-footer > * { margin: 0; }
        .ws-compact-modal .form-label { font-size: .8rem; font-weight: 600; margin-bottom: .2rem; }
        .ws-compact-modal .form-control, .ws-compact-modal .form-select { font-size: .88rem; min-height: 36px; padding-block: .3rem; }
        .ws-compact-modal .form-control-sm, .ws-compact-modal .form-select-sm { min-height: 31px; font-size: .82rem; }
        .ws-compact-modal .cl-help { font-size: .72rem; margin-top: .15rem; }
        /* الشرح الطويل تحت قائمة الدوام (من `partials.shift-select`) يُخفى هنا فقط — القائمة تبحث بالكتابة */
        .ws-compact-modal .cl-shift-select ~ small.text-muted { display: none !important; }
        .ws-compact-modal .ws-upload-btn { cursor: pointer; margin: 0; display: flex; align-items: center; justify-content: center; gap: .45rem; width: 100%; padding: .5rem; border: 1.5px dashed color-mix(in srgb, var(--ws-primary) 45%, transparent); border-radius: 10px; background: color-mix(in srgb, var(--ws-primary) 5%, var(--app-surface, #fff)); color: var(--ws-primary); font-weight: 700; font-size: .85rem; }
        .ws-compact-modal .ws-upload-btn small { color: var(--ws-muted); font-weight: 500; }
        .ws-compact-modal .ws-upload-btn:hover { background: color-mix(in srgb, var(--ws-primary) 10%, var(--app-surface, #fff)); }

        /* رصد المنافسين: صفٌّ واحد لكل منافس بأعمدةٍ ثابتة (منافس، عهدة، صورة، حذف) */
        .ws-comp-intro { font-size: .8rem; color: var(--ws-muted); margin: 0 0 .6rem; }
        .ws-comp-head, .ws-competitor-row { display: grid; grid-template-columns: minmax(0, 1fr) 70px 46px 32px; gap: .4rem; align-items: center; }
        .ws-comp-head { padding: 0 .5rem .2rem; font-size: .72rem; font-weight: 700; color: var(--ws-muted); }
        .ws-comp-head:has(+ #wsCompetitorRows:empty) { display: none; }
        .ws-competitor-row { padding: .4rem .5rem; margin-bottom: .35rem; border: 1px solid var(--ws-border); border-radius: 10px; background: var(--app-surface, #fff); }
        .ws-competitor-row input[type="number"] { text-align: center; }
        .ws-comp-photo { display: block; }
        .ws-comp-photo-btn { width: 100%; height: 31px; padding: 0 2px; display: grid; place-items: center; }
        .ws-comp-remove { width: 32px; height: 31px; padding: 0; color: var(--ws-danger); background: transparent; border: 1px solid transparent; }
        .ws-comp-remove:hover { background: color-mix(in srgb, var(--ws-danger) 10%, transparent); color: var(--ws-danger); }
        .ws-comp-tools { display: flex; gap: .4rem; flex-wrap: wrap; margin: .15rem 0 .6rem; }
        .ws-comp-tools .btn { border: 1px dashed var(--ws-border); font-size: .8rem; }
        .ws-comp-none { display: flex; align-items: flex-start; gap: .55rem; margin: 0; padding: .55rem .7rem; border: 1px solid var(--ws-border); border-radius: 10px; background: var(--ws-bg); cursor: pointer; }
        .ws-comp-none input { margin-top: .2rem !important; flex: none; }
        .ws-comp-none strong { display: block; font-size: .84rem; }
        .ws-comp-none small { display: block; font-size: .72rem; color: var(--ws-muted); }
        .ws-comp-none:has(input:checked) { border-color: var(--ws-success); background: color-mix(in srgb, var(--ws-success) 8%, var(--app-surface, #fff)); }
        #wsNoteCompetitorSection:has(#wsNoCompetitors:checked) #wsCompetitorRows,
        #wsNoteCompetitorSection:has(#wsNoCompetitors:checked) .ws-comp-head { opacity: .45; }
    </style>

    @php
        // قبل «بدء الزيارة» لا شيء في هذه الشاشة قابلٌ للاستعمال إلا زرّ
        // البدء نفسه وزرّ إلغاء الزيارة — راجع تعليق `.ws-lock-banner`
        // في CSS أعلاه و`blockUntilStarted()` في المتحكّم.
        $wsLocked = ! $visit->started_at;

        /* خطوات الزيارة الإلزاميّة — محسوبةٌ في
           `VisitWorkspaceController::stepState()` لا هنا: نفس المصفوفة
           بعينها هي ما يحرس به `complete()` نفسه، فلا يفترق ما تعرضه
           الشاشة عمّا يقبله الخادم. القفل هنا بصريٌّ فقط. */
        $stHasContact = $steps['has_contact'];
        $stHasShift = $steps['has_shift'];
        $stHasNote = $steps['has_note'];
        $stCompetitorStep = $steps['competitor_step'];
        $stHasCompetitors = $steps['has_competitors'];
        $stDocsOpen = $steps['docs_unlocked'];
        $stCanComplete = $steps['can_complete'];
        // «سأعود لهذا العميل» يكفيه المسؤول ودوامه والملاحظة
        $stCanReturn = $steps['can_return'];
        $stOutcome = $steps['outcome'];

        // الخطوة المفتوحة الآن — لا تُفتح واحدةٌ قبل التي تسبقها
        $stCurrent = ! $stHasContact ? 1 : (! $stHasShift ? 2 : (! $stHasNote ? 3 : (! $stHasCompetitors ? 4 : ($stOutcome === 'success' ? 6 : 5))));

        /* «طلب إيقاف العميل» — الوجهةُ المقصودة تُحسَب من حالته الآن لا يختارها
           المندوب من قائمة: حالة العميل ثنائيّة (نشط/موقوف — `Statuses::assignable()`)،
           فالزرّ في الميدان فعلٌ واحدٌ مسمّى بنتيجته لا منسدلةٌ تُقرأ. والمنسدلة
           تبقى في المودال لمنشأةٍ عرّفت حالاتٍ أكثر من اثنتين. */
        $wsStatusCurrentKpi = optional($client->status_client)->kpi_role;
        $wsStatusTarget = $canChangeClientStatus
            ? ($wsStatusCurrentKpi === 'stopped'
                ? $clientStatusOptions->firstWhere('kpi_role', 'active')
                : $clientStatusOptions->firstWhere('kpi_role', 'stopped'))
            : null;
        $wsStatusActionLabel = $wsStatusCurrentKpi === 'stopped' ? 'إعادة تنشيط العميل' : 'إيقاف العميل';
        $wsStatusBtnLabel = ($clientStatusNeedsApproval ? 'طلب ' : '').$wsStatusActionLabel;
        // لا مُعتمِد في المنشأة ⇐ الطلب لا مُرسَلَ إليه؛ يُقال ذلك بدل نموذجٍ يفشل
        $wsStatusBlocked = $clientStatusNeedsApproval && $clientStatusApprovers->isEmpty();
        $wsStatusShow = $canChangeClientStatus && $wsStatusTarget;

        /* «طلب تغيير وضع العميل» — خلافاً للحالة، الوضع حقلٌ مفتوح (أكثر من
           خيارين) فلا «هدف محسوب»: المندوب يختار من القائمة في المودال نفسه. */
        $wsStageBtnLabel = ($clientStageNeedsApproval ? 'طلب ' : '').'تغيير وضع العميل';
        $wsStageBlocked = $clientStageNeedsApproval && $clientStageApprovers->isEmpty();
        $wsStageShow = $canChangeClientStage && $clientStageOptions->isNotEmpty();
    @endphp

    <div class="visit-ws {{ $wsLocked ? 'is-locked' : '' }}" dir="rtl">
        @include('client::components.page-head', [
            'title' => 'إتمام الزيارة',
            'icon' => 'map-pin',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                $client->trade_name => route('clients.show', $client->id),
                'إتمام الزيارة' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        @if ($wsLocked)
            <div class="ws-lock-banner">
                <i class="fas fa-lock"></i>
                <span>
                    لم تبدأ الزيارة بعد — كل أزرار هذه الشاشة وإجراءاتها معطّلة حتى تضغط
                    <strong>«بدء الزيارة»</strong> من موقع العميل. المتاح الآن: بدء الزيارة أو إلغاؤها فقط.
                </span>
            </div>
        @endif

        {{-- لوحة الإرشادات — شاشةٌ يفتحها المندوب في الميدان على جواله،
             وحقولها مختصرة («عدد العهدة»، «مستندات المنافسين»)
             لا يُعرف من عناوينها ما يُكتب فيها ولا ما يترتّب عليه. الشرح
             التفصيلي لكل حقل بجانبه (`cl-help`)؛ هذه اللوحة للمشهد العام:
             ترتيب الخطوات، وما يمنع الإنهاء، ومن أين تُضبط الشاشة. --}}
        @include('client::components.guide', [
            'key' => 'visit-workspace',
            'open' => false,
            'title' => 'ما هذه الشاشة، وماذا أُدخل فيها؟',
            'lead' => 'مساحة عمل الزيارة: كل ما تسجّله عند العميل من لحظة وصولك حتى خروجك — الوقت، والملاحظة، وجرد العهد، ورصد المنافسين، والنتيجة والتوقيع.',
            'blocks' => [
                'خطوات الزيارة بالترتيب — لا تُفتح خطوة قبل التي تسبقها' => [
                    '١) «بدء الزيارة» وأنت في موقع العميل فعلاً — قبلها كل أزرار الشاشة معطّلة، والمتاح إلغاء الزيارة فقط.',
                    '٢) مسؤول العميل: إن لم يكن للعميل مسؤولٌ مسجَّل فهذا أوّل ما تفعله. بحفظه تعود إلى الزيارة وتُكمل.',
                    '٣) دوام المسؤول: ساعات تواجده. يُسجَّل مرّةً على المسؤول نفسه ويُقرأ في كل زيارةٍ قادمة — لا تعيد إدخاله في كل ملاحظة.',
                    '٤) ملاحظة الزيارة: مودالٌ واحد — تختار فيه المسؤول الذي قابلتَه، وتكتب عدد العهدة، وتلتقط المرفق، وتكتب الملاحظة.',
                    '٥) رصد المنافسين: يُفتح بعد الملاحظة — كل منافسٍ رأيتَه وعدد قطع عهدته، ومنافسٌ جديد يُضاف من زرّ «منافس جديد». لا منافس؟ أشِّر «لا يوجد منافس».',
                    '٦) المستندات: الفاتورة وسند القبض والإشعار الدائن والتبديل تُفتح بعد رصد المنافسين. بحفظ أيٍّ منها تعود إلى الزيارة تلقائياً.',
                    '٧) «إنهاء الزيارة»: يُقفلها ويُخرج العميل من خطّ سيرك لليوم.',
                ],
                'ما الذي يمنع إنهاء الزيارة' => [
                    'لا تُقفل الزيارة قبل: مسؤولٍ للعميل، ودوامٍ له، وملاحظة الزيارة، ورصد المنافسين — فلا تُقيَّد زيارةٌ بلا أثر.',
                    '«سأعود لهذا العميل اليوم» يكفيه المسؤول ودوامه والملاحظة: تُقفل هذه الزيارة ويبقى العميل في خطّ سيرك لتعود إليه.',
                    'الخروج من نطاق موقع العميل ('.$proximityMeters.' متر) يوقف المؤقّت ويمنع الحفظ حتى تعود.',
                    'بعد «بدء الزيارة» لا تنتقل إلى عميلٍ آخر ولا إلى أيّ صفحة حتى «إنهاء الزيارة» أو «إلغاء الزيارة» — المسموح مستندات هذه الزيارة وطباعتها فقط.',
                ],
                'النتيجة لا تُختار' => [
                    'لم يعد في الشاشة اختيارٌ لنتيجة الزيارة. تُحسَب عند الإنهاء مما جرى فعلاً: زيارةٌ سجّلتَ فيها **فاتورة أو سند قبض** ⇐ **ناجحة**؛ وما عداها ⇐ **سلبيّة**.',
                    'الإشعار الدائن والتبديل مستندان يفتحهما تسجيل الملاحظة، لكنّهما ليسا بيعاً ولا تحصيلاً فلا يجعلان الزيارة ناجحة.',
                    '«زيارة سلبيّة» تصف زيارةً دخلتَها ولم تُنتج بيعاً؛ أمّا «إلغاء الزيارة» فيعني أنّك لم تدخل أصلاً (المحل مغلق، المسؤول غائب).',
                ],
                'الفرق بين ما يتشابه' => [
                    '«مسؤول العميل» شخصٌ يعمل عند العميل لا موظفٌ في نظامنا؛ يُضاف مرّةً ثم يُختار في كل زيارة.',
                    '«دوام المسؤول» ساعات تواجد الشخص الذي تتعامل معه، لا ساعات فتح المحل — تُجدوَل بها الزيارة القادمة في وقتٍ تجده فيه.',
                    'تغيير دوامٍ مسجَّلٍ من قبل يحتاج صلاحية «تغيير دوام مسؤول العميل بعد تسجيله» — تسجيلُه أوّل مرّة متاحٌ لك دائماً.',
                    '«إلغاء الزيارة» يخصّ زيارة اليوم وحدها، أمّا «إيقاف العميل» فيخصّ العميل نفسه: يُخرجه من العملاء النشطين وتقاريرهم حتى يُعاد تنشيطه — ولا يمسّ أرصدته ولا فواتيره.',
                ],
                'إعداداتٌ تحكم هذه الشاشة من خارجها' => [
                    'إظهار/إخفاء بطاقات هذه الشاشة وتبويباتها: إعدادات وحدات العملاء ← الزيارات ← «أقسام مساحة عمل الزيارة» — فبطاقةٌ غائبة عندك قد تكون مطفأةً لا مفقودة.',
                    'نطاق القرب المسموح ('.$proximityMeters.' متر حالياً) يُضبط من الشاشة نفسها، ويقبل استثناءً لموظفٍ بعينه.',
                ],
            ],
            'facts' => [
                'الملاحظة والصور تُحفظ في سجلّ العميل الدائم، لا في هذه الزيارة وحدها.',
                'التوقيعان يُحفظان صورتين مع الزيارة ويظهران في تقريرها.',
                'المؤقّت يتوقف وحده حين تخرج من نطاق موقع العميل ويعود حين ترجع — لا زرّ إيقافٍ يدويّ.',
                'زرّ «طلب إيقاف العميل» بجانب اسمه في الرأس يرفع الطلب للمسؤول بسببه ورقم هذه الزيارة — ولا يغيّر حالة العميل حتى يُعتمد.',
                'بطاقة «إشعارات الإدارة عن هذا العميل» أعلى الصفحة تعرض ما أُرسل إليك عنه — اقرأها قبل الدخول عليه، وردّ عليها من مكانها.',
            ],
        ])

        {{-- ==================== رأس الزيارة ==================== --}}
        <div class="ws-header-card">
            <div class="ws-header-top">
                <div>
                    <h4 class="mb-1">
                        {{ $client->trade_name }}
                        <small class="text-muted">{{ $client->code }}</small>
                        {{-- مخفيٌّ أثناء زيارةٍ بدأت ولم تنتهِ: `LockToOpenVisit` يعيد أيّ
                             صفحةٍ أخرى إلى هذه الزيارة، فالرابط كان سيفتح تبويباً يرتدّ إليها. --}}
                        @unless ($visit->started_at && ! $visit->departure_time)
                            <a href="{{ route('clients.show', $client->id) }}" target="_blank" class="ws-open-client">
                                <i class="fas fa-external-link-alt"></i> فتح ملف العميل الكامل
                            </a>
                        @endunless
                    </h4>
                    <div class="ws-header-badges mt-1">
                        <div class="ws-badge-group">
                            @if ($client->customerTarget)
                                <span class="badge ws-badge text-white" style="background-color: {{ $client->customerTarget->color }};">
                                    <i class="fas fa-layer-group me-1"></i> تصنيف {{ $client->customerTarget->name }}
                                </span>
                            @endif
                            @if ($client->status_client)
                                <span class="badge ws-badge text-white" style="background-color: {{ $client->status_client->color ?? '#16a34a' }};">
                                    {{ $client->status_client->name }}
                                </span>
                            @endif
                            {{-- حالةُ العميل ليست بيانَ زيارة، لكنّ ما يوجب إيقافه يُكتشف هنا:
                                 محلٌّ أُغلق، أو امتناعٌ عن السداد، أو نشاطٌ توقّف. كان على المندوب
                                 أن يخرج من الزيارة إلى ملفّ العميل ليطلبه — فيؤجَّل ثم يُنسى.
                                 الزرّ يظهر لحامل `clients_change_status` وحده، والخادم
                                 (`ClientController::updateStatusClient()`) هو من يقرّر أطلبٌ
                                 معلَّق أم تغييرٌ مباشر — لا هذه الشاشة. الزرّ ملاصقٌ لشارة الحالة
                                 أعلاه لا بديلاً عنها. --}}
                            @if ($wsStatusShow)
                                <button type="button"
                                    class="btn btn-sm ws-action-chip {{ $wsStatusCurrentKpi === 'stopped' ? 'btn-outline-success' : 'btn-outline-danger' }}"
                                    data-bs-toggle="modal" data-bs-target="#wsClientStatusModal"
                                    @disabled($wsLocked || $wsStatusBlocked)
                                    title="{{ $wsLocked ? 'ابدأ الزيارة أولاً' : ($wsStatusBlocked ? 'لا يوجد مسؤولٌ يملك صلاحية اعتماد حالات العملاء' : $wsStatusBtnLabel) }}">
                                    <i class="fas {{ $wsStatusCurrentKpi === 'stopped' ? 'fa-play-circle' : 'fa-ban' }}"></i>
                                    {{ $wsStatusBtnLabel }}
                                </button>
                            @endif
                        </div>

                        @if (($client->registration_stage_id && $client->registrationStage) || $wsStageShow)
                            <div class="ws-badge-group">
                                {{-- وضع العميل (مرحلة تسجيله — `registration_stage_id`) بجانب حالته:
                                     وسمٌ لا يؤثّر على الحالة التشغيلية، بنفس شارة ملفّ العميل
                                     (`partials.client-info`) حتى لا يُفتح الملفّ لمعرفته. --}}
                                @if ($client->registration_stage_id && $client->registrationStage)
                                    <span class="badge ws-badge text-white" style="background-color: {{ $client->registrationStage->color ?: '#ff9f43' }};" title="وضع العميل">
                                        <i class="fas fa-hourglass-half me-1"></i> وضع العميل: {{ $client->registrationStage->name }}
                                    </span>
                                @endif

                                {{-- زرُّ طلب تغيير الوضع — إلى جانب الشارة أعلاه لا بديلاً عنها:
                                     الشارة تعرض الوضع الحالي، والزرّ يفتح مودال طلب تغييره.
                                     نفس فلسفة زرّ الحالة أعلاه: الخادم
                                     (`ClientController::updateStageClient()`) هو من يقرّر
                                     أطلبٌ معلَّق أم تغييرٌ مباشر — لا هذه الشاشة. --}}
                                @if ($wsStageShow)
                                    <button type="button"
                                        class="btn btn-sm ws-action-chip btn-outline-warning"
                                        data-bs-toggle="modal" data-bs-target="#wsClientStageModal"
                                        @disabled($wsLocked || $wsStageBlocked)
                                        title="{{ $wsLocked ? 'ابدأ الزيارة أولاً' : ($wsStageBlocked ? 'لا يوجد مسؤولٌ يملك صلاحية اعتماد وضع العملاء' : $wsStageBtnLabel) }}">
                                        <i class="fas fa-hourglass-half"></i>
                                        {{ $wsStageBtnLabel }}
                                    </button>
                                @endif
                            </div>
                        @endif

                        <span class="ws-header-divider"></span>

                        {{-- شارات أدوات: رقم الزيارة والرصيد كانا في «إجراءات سريعة»؛ صارت
                             للمستندات وحدها. الرأس ظاهرٌ دائماً، و`data-ws-allow`: الخريطة
                             طريقُ المندوب إلى العميل قبل أن يستطيع البدء. --}}
                        <div class="ws-badge-group">
                            @if ($visit->visit_number)
                                <span class="badge bg-light text-dark border">{{ $visit->visit_number }}</span>
                            @endif
                            <span class="badge {{ $clientDue > 0 ? 'bg-danger' : 'bg-success' }}">
                                <i class="fas fa-wallet me-1"></i> رصيد العميل: {{ number_format($clientDue, 2) }}
                            </span>
                            @if ($contact && $contact->employee_phone)
                                <a href="tel:{{ $contact->employee_phone }}" data-ws-allow class="badge bg-light text-dark border text-decoration-none"><i class="fas fa-phone me-1"></i> اتصال بالمسؤول</a>
                            @endif
                            @if ($clientLatitude && $clientLongitude)
                                <a href="https://www.google.com/maps?q={{ $clientLatitude }},{{ $clientLongitude }}&z=17" target="_blank" data-ws-allow class="badge bg-light text-dark border text-decoration-none"><i class="fas fa-map me-1"></i> فتح الخريطة</a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="ws-timer-block">
                    <div class="ws-timer" id="visitTimer">00:00:00</div>

                    @if ($visit->isCompleted())
                        <span class="badge bg-success p-2"><i class="fas fa-check-circle me-1"></i> زيارة منتهية</span>
                    @elseif (! $visit->started_at)
                        {{-- لم تبدأ الزيارة بعد: المؤقّت متوقّف حتى الضغط على «بدء الزيارة» من موقع العميل نفسه --}}
                        <div id="startLocationStatus" class="small text-muted fw-bold mt-1">
                            <i class="fas fa-spinner fa-spin"></i> جاري تحديد موقعك...
                        </div>
                        <button type="button" id="startVisitBtn" class="btn btn-sm text-white mt-2" style="background: var(--ws-primary);" disabled>
                            <i class="fas fa-play-circle me-1"></i> بدء الزيارة
                        </button>
                    @else
                        <div id="geofenceBadge" class="small text-danger fw-bold mt-1 d-none">
                            <i class="fas fa-exclamation-triangle"></i> خارج نطاق موقع العميل — المؤقّت متوقّف
                        </div>
                        {{-- زرّا «إيقاف مؤقت» و«استئناف» حُذفا: المندوب لا يوقف
                             مؤقّت زيارته بيده. الإيقاف الوحيد الباقي تلقائيّ —
                             يقع حين يخرج من نطاق موقع العميل ويرتفع حين يعود
                             (`wsHandleGeofence()` أسفل الصفحة). --}}
                        {{-- لا زرّ إنهاءٍ هنا: كان «إنهاء الزيارة والخروج» في الرأس
                             و«التسجيل والخروج» في قسم الإنهاء — زرّان لفعلٍ واحد
                             فظنّهما المستخدم فعلين. الزرّ الوحيد «إنهاء الزيارة» في
                             قسم الإنهاء (`#completeVisitBtn`). --}}
                        @unless ($stCanComplete)
                            <div class="small text-warning fw-bold mt-1">
                                <i class="fas fa-exclamation-circle"></i>
                                @if (! $stHasContact)
                                    أضِف مسؤول العميل — أوّل خطوةٍ في الزيارة
                                @elseif (! $stHasShift)
                                    سجّل دوام المسؤول قبل إكمال الزيارة
                                @elseif (! $stHasNote)
                                    سجّل ملاحظة الزيارة قبل إنهائها
                                @else
                                    سجّل رصد المنافسين — أو «سأعود لهذا العميل» من قسم الإنهاء
                                @endif
                            </div>
                        @endunless
                    @endif
                </div>
            </div>

            @php $isDone = $visit->isCompleted(); $isCancelled = $visit->stage === 'cancelled'; @endphp
            {{-- شريط الخطوات صار خريطةَ السلسلة الإلزاميّة نفسها، لا مراحلَ
                 وصفيّة عامّة: ما تراه مضيئاً هو ما أنجزتَه، وما بعده مقفلٌ فعلاً. --}}
            <div class="ws-stepper">
                @foreach ([
                    ['label' => 'بدء الزيارة', 'done' => (bool) $visit->started_at],
                    ['label' => 'مسؤول العميل', 'done' => $stHasContact, 'current' => ! $isDone && ! $isCancelled && $stCurrent === 1],
                    ['label' => 'دوام المسؤول', 'done' => $stHasShift, 'current' => ! $isDone && ! $isCancelled && $stCurrent === 2],
                    ['label' => 'الملاحظة', 'done' => $stHasNote, 'current' => ! $isDone && ! $isCancelled && $stCurrent === 3],
                    ['label' => 'رصد المنافسين', 'done' => $stHasCompetitors, 'current' => ! $isDone && ! $isCancelled && $stCurrent === 4, 'skip' => ! $stCompetitorStep],
                    ['label' => 'فاتورة أو تحصيل', 'done' => $steps['has_sale'], 'current' => ! $isDone && ! $isCancelled && $stCurrent === 5],
                    ['label' => 'إنهاء الزيارة', 'done' => $isDone],
                ] as $step)
                    @continue($step['skip'] ?? false)
                    <div class="ws-step {{ ($step['done'] ?? false) ? 'is-done' : '' }} {{ ($step['current'] ?? false) ? 'is-current' : '' }}">
                        <span class="ws-step-dot">@if ($step['done'] ?? false)<i class="fas fa-check"></i>@endif</span>
                        <span class="ws-step-label">{{ $step['label'] }}</span>
                    </div>
                @endforeach
                @if ($isCancelled)
                    <span class="badge bg-danger p-2 ms-2">ملغاة{{ $visit->cancel_reason ? ' — '.$visit->cancel_reason : '' }}</span>
                @endif
            </div>

            {{-- النتيجة تُعرض لا تُختار — «فاتورة أو تحصيل ⇐ ناجحة، وإلا سلبيّة».
                 راجع `VisitWorkspaceController::hasSaleOrCollectionToday()`. --}}
            <div class="ws-outcome-preview {{ $stOutcome === 'success' ? 'is-success' : 'is-negative' }}">
                <i class="fas {{ $stOutcome === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' }}"></i>
                <span>
                    @if ($visit->isCompleted())
                        نتيجة هذه الزيارة: <strong>{{ $visit->outcome === 'success' ? 'ناجحة' : 'سلبيّة' }}</strong>
                    @elseif ($stOutcome === 'success')
                        بوضعها الحالي هذه الزيارة <strong>ناجحة</strong> — سُجّلت فاتورة أو سند قبض لهذا العميل اليوم.
                    @else
                        بوضعها الحالي هذه الزيارة <strong>سلبيّة</strong> — لا فاتورة ولا سند قبض لهذا العميل اليوم.
                    @endif
                </span>
            </div>
        </div>

        {{-- ============ إشعارات الإدارة عن هذا العميل ============
             فوق كلّ شيء وتحت الرأس مباشرةً: هذه توجيهاتٌ كُتبت للمندوب ليقرأها
             *عند* العميل («وانت عند العميل كلمنى»)، فمكانُها قبل الخطوات لا في
             تبويبٍ يُفتح بعدها. وهي لم تكن تصله أصلاً — راجع تعليق
             `VisitWorkspaceController::clientNotifications()`. --}}
        @if ($clientNotifications->isNotEmpty())
            @php $wsUnreadNotifs = $clientNotifications->where('read', 0)->count(); @endphp
            <div class="ws-notif-card {{ $wsUnreadNotifs ? 'has-unread' : '' }}" id="wsNotifCard">
                <div class="ws-notif-head">
                    <i class="fas fa-bullhorn {{ $wsUnreadNotifs ? 'text-warning' : 'text-muted' }}"></i>
                    <span>إشعارات الإدارة عن هذا العميل</span>
                    @if ($wsUnreadNotifs)
                        <span class="badge bg-warning text-dark">{{ $wsUnreadNotifs }} غير مقروء</span>
                    @endif
                </div>

                @foreach ($clientNotifications as $wsNotif)
                    <div class="ws-notif-item {{ $wsNotif->read ? '' : 'is-unread' }}" data-notif="{{ $wsNotif->id }}">
                        <div class="ws-notif-title">
                            @unless ($wsNotif->read)<i class="fas fa-circle text-warning" style="font-size:.5rem; vertical-align:middle;"></i> @endunless
                            {{ $wsNotif->title ?: 'إشعار' }}
                        </div>
                        <div class="ws-notif-meta">
                            <i class="fas fa-user-tie"></i> {{ optional($wsNotif->user)->name ?: 'النظام' }}
                            &nbsp;·&nbsp;
                            <i class="fas fa-clock"></i> {{ optional($wsNotif->created_at)->format('Y/m/d h:i A') }}
                        </div>
                        <div class="ws-notif-body">{{ $wsNotif->description }}</div>

                        @foreach ($wsNotif->relationLoaded('replies') ? $wsNotif->replies : [] as $wsReply)
                            <div class="ws-notif-reply">
                                <i class="fas fa-reply"></i> ردّك: {{ $wsReply->description }}
                            </div>
                        @endforeach

                        <div class="ws-notif-actions">
                            @unless ($wsNotif->read)
                                <form method="POST" action="{{ route('clients.visits.notifications.read', ['visit' => $visit->id, 'notification' => $wsNotif->id]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.74rem;">
                                        <i class="fas fa-check"></i> تم الاطّلاع
                                    </button>
                                </form>
                            @endunless
                            @if ($canReplyToNotifications)
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.74rem;"
                                    data-bs-toggle="modal" data-bs-target="#wsNotifReplyModal"
                                    data-notif-id="{{ $wsNotif->id }}" data-notif-title="{{ $wsNotif->title ?: 'إشعار' }}">
                                    <i class="fas fa-reply"></i> ردّ
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach

                <small class="cl-help d-block mt-2">
                    ما تراه هنا موجَّهٌ إليك أنت عن هذا العميل. «تم الاطّلاع» يقفل الإشعار، و«ردّ» يصل مُرسِلَه ويقفله معاً.
                </small>
            </div>
        @endif

        {{-- شريطُ «ما يجب فعله» — يظهر عند محاولة الإنهاء وشيءٌ ناقص، ويبقى
             مرئياً أثناء تمرير الصفحة إلى البطاقة المقصودة. بديلُ نافذة
             `alert` التي كانت تُغلق فينسى المندوب ما قيل له. --}}
        <div id="wsRequiredBanner" class="ws-required-banner d-none" role="alert">
            <i class="fas fa-circle-exclamation"></i>
            <span id="wsRequiredBannerText"></span>
            {{-- «خذني إليها»: يفتح مودال الخطوة الناقصة نفسها بدل أن يبحث
                 المندوب عن زرّها — يُملأ من `wsFirstMissing()`. --}}
            <button type="button" id="wsRequiredAction" class="btn btn-sm btn-danger text-nowrap ms-auto d-none"></button>
            <button type="button" class="btn-close" aria-label="إغلاق"
                onclick="document.getElementById('wsRequiredBanner').classList.add('d-none')"></button>
        </div>

        {{-- ==================== بانتظار استلامك ====================
             المديرُ يضع العميلَ في خطّ سير المستلِم، فيصل إليه ويجد هنا أنّ زميلاً
             سلّمه فواتير هذا العميل — «استلم الآن» تفتح تبويب التحويلات وفيه
             «استلام الكل». يظهر لمن ينتظر ردُّه وحده. --}}
        @php
            $wsAwaitingTransfers = hasPermission('clients_invoice_transfer_respond')
                ? \App\Models\InvoiceTransfer::with('invoice:id,due_value', 'fromEmployee:id,name', 'toEmployee:id,name')
                    ->where('client_id', $client->id)->where('status', 'pending')
                    ->respondableBy(auth()->id())->get()
                : collect();
        @endphp
        @if ($wsAwaitingTransfers->isNotEmpty())
            @php
                $wsAwFrom = $wsAwaitingTransfers->map(fn ($t) => $t->isRequest() ? $t->toEmployee?->name : $t->fromEmployee?->name)->filter()->unique()->implode('، ');
            @endphp
            <div class="ws-wt-banner">
                <i class="fas fa-inbox"></i>
                <div>
                    @php
                    $wsAwBalance = $wsAwaitingTransfers->contains(fn ($t) => $t->isBalance());
                    $wsAwInvoices = $wsAwaitingTransfers->reject(fn ($t) => $t->isBalance())->count();
                @endphp
                <strong>بانتظار استلامك لهذا العميل من {{ $wsAwFrom }}:
                    {{ collect([$wsAwBalance ? 'رصيد الحساب' : null, $wsAwInvoices ? ($wsAwInvoices === 1 ? 'فاتورة' : $wsAwInvoices.' فواتير') : null])->filter()->implode(' و') }}</strong>
                    <span>بقيمة {{ number_format($wsAwaitingTransfers->sum(fn ($t) => (float) ($t->isBalance() ? $t->invoice_amount : ($t->invoice->due_value ?? 0))), 2) }}</span>
                </div>
                <button type="button" class="btn btn-warning btn-sm ms-auto" onclick="wsGoToTab('invoice-transfers'); document.querySelector('[data-pane=&quot;invoice-transfers&quot;]').scrollIntoView({behavior: 'smooth'})">
                    استلم الآن
                </button>
            </div>
        @endif

        {{-- ==================== تبويبات الزيارة ==================== --}}
        <div class="ws-tabs" id="wsTabBar">
            <button type="button" class="active" data-tab="summary">ملخص الزيارة</button>
            @if ($wsShow('show_notes_tab'))
                <button type="button" data-tab="notes">الملاحظات @if($notes->count())<span class="badge bg-secondary">{{ $notes->count() }}</span>@endif</button>
            @endif
            @if ($wsShow('show_competitors_tab'))
                <button type="button" data-tab="competitors">رصد المنافسين</button>
            @endif
            @if ($wsShow('show_docs_tab'))
                <button type="button" data-tab="docs">المستندات</button>
            @endif
            {{-- مستندات العميل (آخر 20 من كل نوع) — `VisitWorkspaceController::clientDocuments()`.
                 «الإشعارات الدائنة» لمن يُصدرها أو حين توجد، و«المرتجعات» حين توجد فقط. --}}
            @php
                $wsCanCreditNote = hasPermission(['sales.credit_notes.create', 'clients_create_credit_notice']);
                $wsDocCount = fn (string $group) => $clientDocuments->get($group, collect())->count();
            @endphp
            <button type="button" data-tab="client-invoices">الفواتير @if($wsDocCount('invoices'))<span class="badge bg-secondary">{{ $wsDocCount('invoices') }}</span>@endif</button>
            <button type="button" data-tab="client-receipts">سندات القبض @if($wsDocCount('receipts'))<span class="badge bg-secondary">{{ $wsDocCount('receipts') }}</span>@endif</button>
            @if ($wsCanCreditNote || $wsDocCount('credit_notes'))
                <button type="button" data-tab="client-credit-notes">الإشعارات الدائنة @if($wsDocCount('credit_notes'))<span class="badge bg-secondary">{{ $wsDocCount('credit_notes') }}</span>@endif</button>
            @endif
            @if ($wsDocCount('returns'))
                <button type="button" data-tab="client-returns">المرتجعات <span class="badge bg-secondary">{{ $wsDocCount('returns') }}</span></button>
            @endif
            {{-- تسليم واستلام فواتير العميل المستحقّة — `visit_workspace/tab-invoice-transfers`.
                 يغيب مع قسم «تسليم الفواتير» المطفأ في إعدادات وحدات العملاء، كما تُمنع مساراته. --}}
            @php
                $wsTransfersOn = hasPermission('clients_invoice_transfer_view')
                    && (bool) (\App\Models\ClientModuleSetting::current()->invoice_transfer_enabled ?? true);
                $wsDueInvoices = $wsTransfersOn
                    ? \App\Models\Invoice::where('client_id', $client->id)->where('due_value', '>', 0)->whereNull('voided_at')
                        ->where(fn ($type) => $type->whereNull('type')->orWhere('type', '!=', 'returned'))->count()
                    : 0;
            @endphp
            @if ($wsTransfersOn)
                <button type="button" data-tab="invoice-transfers">تسليم واستلام الفواتير @if($wsDueInvoices)<span class="badge bg-secondary">{{ $wsDueInvoices }}</span>@endif</button>
            @endif
            @if ($wsShow('show_attachments_tab'))
                <button type="button" data-tab="attachments">المرفقات @if($attachments->count())<span class="badge bg-secondary">{{ $attachments->count() }}</span>@endif</button>
            @endif
        </div>

        {{-- ==================== ملخص الزيارة ==================== --}}
        <div class="ws-tab-pane" data-pane="summary">
            <div class="row g-3">
                {{-- العمود الأيمن: الموقع + إجراءات سريعة --}}
                <div class="col-lg-3">
                    @if ($wsShow('show_location_card'))
                    <div class="card mb-3">
                        <div class="card-header"><span><i class="fas fa-map-marker-alt text-danger me-1"></i> موقع الزيارة</span></div>
                        <div class="card-body">
                            <div id="visitMap" class="ws-map"></div>
                            <div class="ws-loc-facts">
                                <div><span class="text-muted">المسافة من موقع العميل</span> <strong id="locDistance">{{ $visit->distance !== null ? round($visit->distance * 1000).' متر' : '—' }}</strong></div>
                                <div><span class="text-muted">دقة الموقع</span> <strong id="locAccuracy">{{ $visit->gps_accuracy ? '± '.round($visit->gps_accuracy).' متر' : '—' }}</strong></div>
                                <div><span class="text-muted">آخر تحديث</span> <strong id="locUpdatedAt">{{ $visit->arrival_time?->format('h:i A') }}</strong></div>
                            </div>
                            <small class="cl-help">
                                «المسافة» بينك وبين الموقع المسجَّل للعميل. تجاوزُ {{ $proximityMeters }} متر يوقف المؤقّت ويمنع الحفظ —
                                النطاق يُضبط من إعدادات وحدات العملاء ← الزيارات. و«دقّة الموقع» رقمٌ يعطيه جهازك لا نحن: كلّما كبر
                                كان تقديره أضعف، فاخرج للعراء أو انتظر ثوانٍ قبل أن تقرأ المسافة.
                            </small>
                        </div>
                    </div>
                    @endif

                    @if ($wsShow('show_quick_actions_card'))
                    <div class="card ws-actions-card">
                        <div class="card-header"><span><i class="fas fa-bolt text-warning me-1"></i> إجراءات سريعة</span></div>
                        <div class="card-body py-1">
                            {{-- الخطوة الرابعة: المستندات. لا تُفتح قبل تسجيل ملاحظة
                                 اليوم («وإذا نفّذ إحدى هذه الخطوات يفتح له الإجراءات
                                 من فاتورة وسند وتبديل وإشعار دائن»).

                                 الروابط تمرّ بـ`clients.visits.openDocument` لا بشاشة
                                 الإنشاء مباشرةً: هو من يختم رابط العودة في الجلسة
                                 فيُرجعك `ReturnToVisitWorkspace` إلى الزيارة بعد الحفظ
                                 بدل فهرس المستند، وهو أيضاً من يعيد فحص الخطوات في
                                 الخادم — قفل الواجهة هنا يزول بأداة مطوّرين. --}}
                            {{-- أزرارٌ مباشرة لا قائمةٌ منسدلة: «إجراءات العميل» إجراءاتٌ مثل
                                 الملاحظة ورصد المنافسين، فتُعرض بشكلها نفسه — زرٌّ لكلّ
                                 مستند، ظاهرٌ وإن كان مقفلاً، حتى يرى المندوب ما ينتظره. --}}
                            <div class="ws-quick-subhead">
                                <i class="fas fa-file-invoice-dollar"></i> إجراءات العميل
                                @unless ($stDocsOpen)<i class="fas fa-lock ms-1 text-muted" style="font-size:.7rem"></i>@endunless
                            </div>
                            @php
                                // إلزاميّة الظهور، غير قابلةٍ للإخفاء من الإعدادات — نفس معاملة
                                // clients.show (client_show_actions_settings). والصلاحيات هي
                                // صلاحيات راوت الإنشاء نفسها، فلا يقود زرٌّ إلى 403.
                                $wsDocButtons = [
                                    // الفاتورة أوّلها — بطلب المستخدم مكانُها هنا، وتبقى أيضاً في تبويب «الفواتير»
                                    ['type' => 'invoice', 'icon' => 'fa-file-invoice', 'label' => 'إنشاء فاتورة', 'show' => true],
                                    ['type' => 'receipt', 'icon' => 'fa-money-bill-wave', 'label' => 'سند قبض', 'show' => true],
                                    ['type' => 'credit-note', 'icon' => 'fa-file-invoice-dollar', 'label' => 'إشعار دائن', 'show' => hasPermission(['sales.credit_notes.create', 'clients_create_credit_notice'])],
                                ];
                            @endphp
                            <ul class="ws-quick-list">
                                @foreach ($wsDocButtons as $wsDoc)
                                    @continue(! $wsDoc['show'])
                                    <li>
                                        @if ($stDocsOpen)
                                            <a href="{{ route('clients.visits.openDocument', ['visit' => $visit->id, 'type' => $wsDoc['type']]) }}"><i class="fa {{ $wsDoc['icon'] }}"></i> {{ $wsDoc['label'] }}</a>
                                        @else
                                            <button type="button" disabled><i class="fa {{ $wsDoc['icon'] }}"></i> {{ $wsDoc['label'] }}</button>
                                        @endif
                                    </li>
                                @endforeach
                                {{-- تبديل البضاعة: نفس شرط زرّه في clients.show — يوم خط
                                     السير + معاملة اليوم إن اشترطها الإعداد. انظر
                                     App\Support\ProductExchangeEligibility. --}}
                                @if (hasPermission('sales.exchanges.create'))
                                    <li>
                                        @if ($stDocsOpen && $exchangeEligibility['can_exchange'])
                                            <a href="{{ route('clients.visits.openDocument', ['visit' => $visit->id, 'type' => 'exchange']) }}"><i class="fa fa-exchange-alt"></i> تبديل بضاعة</a>
                                        @else
                                            <button type="button" disabled
                                                title="{{ ! $stDocsOpen ? 'مقفل حتى تُكمل خطوات الزيارة' : (! $exchangeEligibility['is_itinerary_day'] ? 'متاح في يوم خط السير فقط' : 'يحتاج فاتورة أو سند قبض أو إشعار دائن اليوم') }}">
                                                <i class="fa fa-exchange-alt"></i>
                                                <span>
                                                    تبديل بضاعة
                                                    @if ($stDocsOpen)
                                                        <small class="d-block text-muted" style="font-size:.7rem">
                                                            {{ ! $exchangeEligibility['is_itinerary_day'] ? 'متاح في يوم خط السير فقط' : 'يحتاج فاتورة أو سند قبض أو إشعار دائن اليوم' }}
                                                        </small>
                                                    @endif
                                                </span>
                                            </button>
                                        @endif
                                    </li>
                                @endif
                                {{-- سند الصرف آخرها — بترتيب طلب المستخدم: فاتورة، قبض، إشعار، تبديل، صرف.
                                     الملاحظة والمنافسون والمسؤول في بطاقاتها. --}}
                                @if (hasPermission('account.expenses.create'))
                                    <li>
                                        @if ($stDocsOpen)
                                            <a href="{{ route('clients.visits.openDocument', ['visit' => $visit->id, 'type' => 'expense']) }}"><i class="fa fa-hand-holding-usd"></i> سند صرف</a>
                                        @else
                                            <button type="button" disabled><i class="fa fa-hand-holding-usd"></i> سند صرف</button>
                                        @endif
                                    </li>
                                @endif
                            </ul>
                            <small class="cl-help d-block mb-2" data-hint-keep>
                                @if ($stDocsOpen)
                                    بعد حفظ أيّ مستندٍ من هنا تعود إلى هذه الزيارة تلقائياً لتُكملها.
                                    الفاتورة أو سند القبض يجعلان الزيارة <strong>ناجحة</strong>.
                                @else
                                    مقفلة: سجّل مسؤول العميل ودوامه ثم ملاحظة الزيارة{{ $stCompetitorStep ? ' ثم رصد المنافسين' : '' }} أوّلاً.
                                @endif
                            </small>
                        </div>
                    </div>
                    @endif

                    {{-- ما أُصدر في هذه الزيارة — بزرّ طباعةٍ لكلٍّ منه، فيُسلَّم
                         للعميل وهو عنده. يظهر متى صدر مستند؛ الحفظ يعيد المندوب
                         إلى هنا (`ReturnToVisitWorkspace`) فيجده في رأس القائمة. --}}
                    @if ($visitDocuments->isNotEmpty())
                    <div class="card mt-3 ws-docs-card" id="wsVisitDocsCard">
                        <div class="card-header">
                            <span><i class="fas fa-print text-primary me-1"></i> مستندات هذه الزيارة</span>
                            <span class="badge bg-primary">{{ $visitDocuments->count() }}</span>
                        </div>
                        <div class="card-body py-1">
                            @include('client::partials.visit-documents')
                        </div>
                    </div>
                    @endif
                </div>

                {{-- العمود الثاني: مسؤول العميل ودوامه — الخطوتان ١ و٢ --}}
                <div class="col-lg-3">
                    @if ($wsShow('show_contact_card'))
                    {{-- `id` هدفُ التوجيه: زرّ «إنهاء الزيارة» يمرّر إلى البطاقة
                         الناقصة ويُبرزها بدل نافذة تنبيهٍ تُغلق وتُنسى. --}}
                    <div class="card mb-3" id="stepContactCard">
                        <div class="card-header">
                            <span>
                                <span class="ws-step-badge {{ $stHasShift ? 'is-done' : '' }}">{{ $stHasShift ? '✓' : '١' }}</span>
                                مسؤول العميل ودوامه
                            </span>
                            @if ($canAddNote)
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeNoteModal">+ موظف</button>
                            @endif
                        </div>
                        <div class="card-body">
                            @if (! $stHasContact)
                                {{-- «يكون أول إجراء إذا لا يوجد للعميل موظف يضاف له
                                     موظف» — فالبطاقة هنا لا تكتفي بقول «لا يوجد»، بل
                                     هي مدخل الخطوة نفسها. --}}
                                <div class="alert alert-warning py-2 small mb-2">
                                    <div class="fw-bold mb-1"><i class="fa fa-exclamation-circle me-1"></i> أوّل خطوة في هذه الزيارة</div>
                                    لا يوجد مسؤولٌ مسجَّلٌ لهذا العميل. أضِفه أولاً — وبحفظه تعود إلى الزيارة لتُكمل.
                                </div>
                                @if ($canAddNote)
                                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addEmployeeNoteModal">
                                        <i class="fas fa-user-plus me-1"></i> إضافة موظف للعميل
                                    </button>
                                @else
                                    <p class="text-muted small mb-0">لا تملك صلاحية إضافة مسؤولٍ للعميل — راجع مشرفك.</p>
                                @endif
                            @else
                                @foreach ($clientEmployees as $ce)
                                    <div class="ws-contact-row">
                                        <div class="ws-contact-avatar">{{ mb_substr($ce->employee_name ?? '؟', 0, 1) }}</div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small">
                                                {{ $ce->employee_name }}
                                                @if ($ce->is_primary)<span class="badge bg-primary" style="font-size:.6rem">رئيسي</span>@endif
                                            </div>
                                            <small class="text-muted d-block">{{ $ce->employee_position ?? 'مسؤول' }}</small>
                                            @if ($ce->hasShift())
                                                <small class="text-success d-block"><i class="fas fa-clock"></i> {{ $ce->shift_label }}</small>
                                            @else
                                                <small class="text-danger d-block fw-bold"><i class="fas fa-clock"></i> لم يُسجَّل دوامه بعد</small>
                                            @endif
                                        </div>
                                        <div class="d-flex flex-column gap-1">
                                            @if ($ce->employee_phone)
                                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $ce->employee_phone) }}" target="_blank" class="btn btn-sm btn-outline-success py-0"><i class="fab fa-whatsapp"></i></a>
                                            @endif
                                            {{-- «ويكون فيه صلاحية تسمح بتغيير دوام المسؤول»:
                                                 التسجيل أوّل مرّة متاحٌ للجميع (وإلا حُبست
                                                 الزيارة عند خطوةٍ لا يستطيع المندوب تجاوزها)،
                                                 والتغيير بعده يحتاج الصلاحية. نفس الشرط
                                                 مُطبَّقٌ في `storeContactShift()` بالخادم. --}}
                                            @if (! $ce->hasShift() || $canChangeShift)
                                                {{-- بنصٍّ لا بأيقونة وحدها: الساعة الصغيرة لم تُقرأ
                                                     «تعديل دوام» فظنّ المستخدم أنّ الميزة غير موجودة.

                                                     القيم في `data-` لا داخل `onclick`: `@json` يطبع
                                                     النصّ بين علامتَي `"` فتُغلق السمة `onclick="…"`
                                                     عند اسم الموظف، ولا يصل للمتصفّح إلا
                                                     `wsOpenShiftModal(1129, ` — فالزرّ لا يفعل شيئاً. --}}
                                                <button type="button" class="btn btn-sm btn-outline-primary py-0 text-nowrap"
                                                    data-contact-id="{{ $ce->id }}"
                                                    data-contact-name="{{ $ce->employee_name }}"
                                                    data-shift-range="{{ $ce->shift_range_value }}"
                                                    data-has-shift="{{ $ce->hasShift() ? '1' : '0' }}"
                                                    onclick="wsOpenShiftModal(this.dataset.contactId, this.dataset.contactName, this.dataset.shiftRange, this.dataset.hasShift === '1')">
                                                    <i class="fas fa-clock me-1"></i>{{ $ce->hasShift() ? 'تعديل الدوام' : 'تسجيل الدوام' }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                @unless ($stHasShift)
                                    <div class="alert alert-warning py-2 small mt-2 mb-0">
                                        <div class="fw-bold mb-1"><i class="fa fa-clock me-1"></i> الخطوة التالية: دوام المسؤول</div>
                                        سجّل ساعات تواجد المسؤول (زرّ «تسجيل الدوام» بجانبه) — لا تُفتح الملاحظة ولا المستندات قبلها.
                                    </div>
                                @endunless

                                <small class="cl-help">
                                    ساعات تواجد الشخص الذي تتعامل معه، لا ساعات فتح المحل — تُجدوَل بها الزيارة القادمة في وقتٍ تجده فيه.
                                    تُسجَّل مرّةً على المسؤول وتُقرأ في كل زيارةٍ قادمة.
                                    @unless ($canChangeShift)
                                        تغييرُ دوامٍ مسجَّلٍ من قبل يحتاج صلاحية «تغيير دوام مسؤول العميل بعد تسجيله».
                                    @endunless
                                </small>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- العمود الثالث: الملاحظات — الخطوة ٣ --}}
                <div class="col-lg-3">
                    @if ($wsShow('show_notes_card'))
                    <div class="card mb-3 {{ $stHasContact ? '' : 'ws-step-locked' }}" id="stepNoteCard">
                        <div class="card-header">
                            <span>
                                <span class="ws-step-badge {{ $stHasNote ? 'is-done' : ($stHasContact ? '' : 'is-locked') }}">{{ $stHasNote ? '✓' : '٣' }}</span>
                                الملاحظات
                            </span>
                            @if ($canAddNote)
                                <button type="button" class="btn btn-sm btn-primary" @disabled(! $stHasContact) data-bs-toggle="modal" data-bs-target="#noteModal" onclick="prepNoteModal(false)">+ ملاحظة الزيارة</button>
                            @endif
                        </div>
                        <div class="card-body">
                            @unless ($stHasContact)
                                <div class="alert alert-secondary py-2 small mb-2">
                                    <i class="fa fa-lock me-1"></i> مقفلة حتى تضيف مسؤول العميل.
                                </div>
                            @endunless
                            @forelse ($notes->take(4) as $note)
                                <div class="ws-note-card">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="ws-note-badge">ملاحظة</span>
                                        <small class="text-muted">{{ $note->created_at->diffForHumans() }}</small>
                                    </div>
                                    <p class="mb-1 small">{{ \Illuminate\Support\Str::limit($note->description, 90) }}</p>
                                    <small class="text-muted"><i class="fas fa-user me-1"></i>{{ $note->employee->name ?? 'موظف' }}</small>
                                </div>
                            @empty
                                <p class="text-muted small mb-0">لا توجد ملاحظات بعد لهذا العميل.</p>
                            @endforelse
                        </div>
                        @if ($notes->count() > 4)
                            <div class="card-footer text-center bg-white">
                                <button type="button" class="btn btn-sm btn-link" onclick="wsGoToTab('notes')">عرض جميع الملاحظات ({{ $notes->count() }})</button>
                            </div>
                        @endif
                    </div>
                    @endif

                    {{-- الخطوة التالية للملاحظة: رصد المنافسين بموداله المستقلّ.
                         `id` هدفُ التوجيه من زرّ «إنهاء الزيارة» حين تنقص. --}}
                    @if ($stCompetitorStep)
                    <div class="card mb-3 {{ $stHasNote ? '' : 'ws-step-locked' }}" id="stepCompetitorCard">
                        <div class="card-header">
                            <span>
                                <span class="ws-step-badge {{ $stHasCompetitors ? 'is-done' : ($stHasNote ? '' : 'is-locked') }}">{{ $stHasCompetitors ? '✓' : '٤' }}</span>
                                رصد المنافسين
                            </span>
                            @if ($canAddNote)
                                <button type="button" class="btn btn-sm btn-primary" @disabled(! $stHasNote) data-bs-toggle="modal" data-bs-target="#competitorStepModal">
                                    {{ $stHasCompetitors ? '+ منافس' : 'رصد' }}
                                </button>
                            @endif
                        </div>
                        <div class="card-body">
                            @if ($competitors->isEmpty())
                                <div class="alert {{ $stHasNote ? 'alert-warning' : 'alert-secondary' }} py-2 small mb-0">
                                    @if ($stHasNote)
                                        <i class="fa fa-crosshairs me-1"></i> <strong>إلزامي:</strong> ارصد المنافسين (مع صورة كل منافس) أو أشِّر «لا يوجد منافس» — لا تُفتح المستندات قبلها.
                                    @else
                                        <i class="fa fa-lock me-1"></i> مقفلة حتى تسجّل ملاحظة الزيارة.
                                    @endif
                                </div>
                            @else
                                @foreach ($competitors as $obs)
                                    <div class="d-flex justify-content-between align-items-center gap-2 small border-bottom py-1">
                                        <span class="flex-grow-1">{{ $obs->competitor_name }}</span>
                                        @if ($obs->competitor_id)
                                            <span class="text-muted">عهدة: {{ (int) $obs->asset_count }}</span>
                                        @endif
                                        @if ($obs->photo_path)
                                            <a href="{{ asset('assets/uploads/visit_competitors/'.$obs->photo_path) }}" target="_blank" rel="noopener" data-ws-allow title="مرفق المنافس">
                                                <img src="{{ asset('assets/uploads/visit_competitors/'.$obs->photo_path) }}" alt="" style="width:32px;height:32px;object-fit:cover;border-radius:6px;">
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- العمود الأيسر: تفاصيل الزيارة + الإنهاء --}}
                <div class="col-lg-3">
                    @if ($wsShow('show_details_card'))
                    <div class="card mb-3">
                        <div class="card-header"><span><i class="fas fa-info-circle text-secondary me-1"></i> تفاصيل الزيارة</span></div>
                        <div class="card-body ws-details-list">
                            <div><span>رقم الزيارة</span><strong>{{ $visit->visit_number ?? '—' }}</strong></div>
                            <div><span>نوع الزيارة</span><strong>{{ $visit->visit_type === 'periodic' ? 'زيارة دورية' : ($visit->visit_type ?? '—') }}</strong></div>
                            <div><span>تاريخ الزيارة</span><strong>{{ $visit->visit_date?->format('Y-m-d') }}</strong></div>
                            <div><span>وقت الوصول</span><strong>{{ $visit->arrival_time?->format('h:i A') }}</strong></div>
                            {{-- طرفا المؤقّت ومدّته — `$elapsedSeconds` محسوبةٌ في المتحكّم
                                 لا هنا (راجع تعليق `Visit::getElapsedSecondsAttribute()` عن
                                 المنطقة الزمنية)، وهي المدّة الفعلية بعد خصم الإيقاف المؤقّت،
                                 وهي نفسها ما يُختم على سجلّ خط السير فيظهر في ملف العميل. --}}
                            <div><span>وقت بدء الزيارة</span><strong>{{ $visit->started_at?->format('h:i A') ?? 'لم تبدأ بعد' }}</strong></div>
                            @if ($visit->departure_time)
                                <div><span>وقت الانصراف</span><strong>{{ $visit->departure_time->format('h:i A') }}</strong></div>
                            @endif
                            @if ($visit->started_at)
                                <div>
                                    <span>مدة الزيارة{{ $visit->isCompleted() ? '' : ' حتى الآن' }}</span>
                                    <strong>
                                        @php
                                            $wsDurH = intdiv($elapsedSeconds, 3600);
                                            $wsDurM = intdiv($elapsedSeconds % 3600, 60);
                                        @endphp
                                        {{ $wsDurH ? $wsDurH.' س '.$wsDurM.' د' : $wsDurM.' د' }}
                                        @if ($visit->total_paused_seconds)
                                            <small class="text-muted">(خُصم {{ intdiv($visit->total_paused_seconds, 60) }} د إيقاف)</small>
                                        @endif
                                    </strong>
                                </div>
                            @endif
                            <div><span>المندوب</span><strong>{{ $visit->employee->name ?? '—' }}</strong></div>
                            <div><span>الغرض من الزيارة</span><strong>{{ $visit->purpose ?? 'غير محدد' }}</strong></div>
                        </div>
                    </div>
                    @endif

                    <div class="card" id="endVisitSection">
                        <div class="card-header">
                            <span>
                                @if ($wsShow('show_signature_section'))
                                    <i class="fas fa-signature text-primary me-1"></i> التوقيع
                                @else
                                    <i class="fas fa-sign-out-alt text-primary me-1"></i> إنهاء الزيارة
                                @endif
                            </span>
                        </div>
                        <div class="card-body">
                            @if ($visit->isCompleted())
                                <p class="mb-0 text-success small"><i class="fas fa-check-circle me-1"></i> تم إنهاء هذه الزيارة بالفعل.</p>
                            @elseif (! $visit->started_at)
                                <p class="mb-0 text-muted small"><i class="fas fa-info-circle me-1"></i> ابدأ الزيارة أولاً من الأعلى قبل إتمامها.</p>
                                {{-- `data-ws-allow`: المخرج الوحيد المتاح قبل البدء — راجع
                                     `wsApplyLock()` أسفل الصفحة و`blockUntilStarted()` في المتحكّم
                                     (تستثني `cancel()` عمداً). --}}
                                @if ($canCancelVisit)
                                    <button type="button" data-ws-allow class="btn btn-outline-danger w-100 mt-2" data-bs-toggle="modal" data-bs-target="#cancelVisitModal">
                                        <i class="fas fa-times-circle me-1"></i> إلغاء الزيارة
                                    </button>
                                @endif
                            @else
                                {{-- بطاقة التوقيع قابلة للإخفاء من الإعدادات (إعدادات وحدات
                                     العملاء ← الزيارات ← أقسام مساحة عمل الزيارة) — إخفاؤها
                                     لا يمنع إتمام الزيارة، يُسقط لوحتَي التوقيع من نموذجها فقط --}}
                                @if ($wsShow('show_signature_section'))
                                    <small class="cl-help mb-2">وقّع بإصبعك داخل المربّع. يُحفظ التوقيعان صورتين مع الزيارة ويظهران في تقريرها — فهما إقرار المسؤول بأنّك حضرتَ وبما جرى.</small>

                                    <label class="form-label small fw-bold mb-1">توقيع مسؤول العميل</label>
                                    <canvas id="clientSigPad" class="ws-sig-pad mb-1"></canvas>
                                    <button type="button" class="btn btn-sm btn-link p-0 mb-2" onclick="clientSigPad.clear()">مسح التوقيع</button>
                                    <small class="cl-help mb-2" style="margin-top:-.4rem">توقيع الشخص الذي قابلتَه عند العميل — يوقّع هو على جهازك.</small>

                                    <label class="form-label small fw-bold mb-1">توقيع المندوب</label>
                                    <canvas id="employeeSigPad" class="ws-sig-pad mb-1"></canvas>
                                    <button type="button" class="btn btn-sm btn-link p-0 mb-2" onclick="employeeSigPad.clear()">مسح التوقيع</button>
                                    <small class="cl-help mb-2" style="margin-top:-.4rem">توقيعك أنت.</small>
                                @endif

                                @unless ($stCanComplete)
                                    {{-- المستخدم يصل هنا بالضغط على «إنهاء الزيارة» أعلى الصفحة —
                                         فلا يكفي أن نقول له ما الناقص، لازم نعطيه طريقاً مباشراً
                                         إلى الخطوة الناقصة بلا تمرير الصفحة بحثاً عنها --}}
                                    <div class="alert alert-warning py-2 small mb-2">
                                        <div class="fw-bold mb-1"><i class="fa fa-exclamation-circle me-1"></i> لا يمكن إنهاء الزيارة بعد</div>
                                        @if (! $stHasContact)
                                            لا مسؤولَ مسجَّلٌ لهذا العميل — وهي أوّل خطوةٍ في الزيارة.
                                            @if ($canAddNote)
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#addEmployeeNoteModal">
                                                        <i class="fas fa-user-plus"></i> إضافة مسؤول العميل
                                                    </button>
                                                </div>
                                            @endif
                                        @elseif (! $stHasShift)
                                            لم يُسجَّل دوام المسؤول بعد — سجّله من بطاقة «مسؤول العميل ودوامه».
                                        @elseif (! $stHasNote)
                                            سجّل ملاحظة الزيارة أولاً.
                                            @if ($canAddNote)
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#noteModal" onclick="prepNoteModal(false)">
                                                        <i class="fas fa-sticky-note"></i> ملاحظة الزيارة
                                                    </button>
                                                </div>
                                            @endif
                                        @else
                                            سجّل رصد المنافسين لإنهاء الزيارة — أو أشِّر «سأعود لهذا العميل اليوم» أدناه لتُقفلها ويبقى العميل في خطّ سيرك.
                                            @if ($canAddNote)
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#competitorStepModal">
                                                        <i class="fas fa-crosshairs"></i> رصد المنافسين
                                                    </button>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @endunless

                                {{-- النتيجة ليست حقلاً في هذا النموذج: لا `outcome` يُرسَل
                                     ولا يُقبَل. `complete()` يستنتجها من وجود فاتورةٍ أو سند
                                     قبضٍ لهذا العميل اليوم. ما بقي هنا ملاحظةُ الخروج فقط. --}}
                                <div class="alert {{ $stOutcome === 'success' ? 'alert-success' : 'alert-warning' }} py-2 small mb-2">
                                    <i class="fas {{ $stOutcome === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' }} me-1"></i>
                                    ستُقيَّد هذه الزيارة <strong>{{ $stOutcome === 'success' ? 'ناجحة' : 'سلبيّة' }}</strong>
                                    {{ $stOutcome === 'success' ? '— سُجّلت فاتورة أو سند قبض اليوم.' : '— لا فاتورة ولا سند قبض لهذا العميل اليوم.' }}
                                </div>

                                <form id="completeVisitForm" method="POST" action="{{ route('clients.visits.completeWorkspace', $visit->id) }}">
                                    @csrf
                                    <input type="hidden" name="client_signature" id="clientSignatureInput">
                                    <input type="hidden" name="employee_signature" id="employeeSignatureInput">
                                    <input type="hidden" name="current_latitude" id="completeLat">
                                    <input type="hidden" name="current_longitude" id="completeLng">

                                    {{-- «العميل قال ارجع المغرب» — السؤال هنا لا في شاشةٍ
                                         أخرى: هذه اللحظة الوحيدة التي يعرف فيها المندوب
                                         الجواب. الافتراض «انتهيت»، فالغالب زيارةٌ واحدة،
                                         و«سأعود» يفتح صفَّ خطّ سيرٍ ثانياً لليوم فيبقى
                                         العميل ظاهراً في الخريطة بدل أن يختفي. --}}
                                    <div class="ws-return-box mb-2">
                                        <label class="d-flex align-items-start gap-2 mb-0" style="cursor:pointer">
                                            <input type="checkbox" name="return_later" value="1" id="wsReturnLater" class="mt-1"
                                                onchange="document.getElementById('wsReturnTimeWrap').classList.toggle('d-none', !this.checked)">
                                            <span>
                                                <strong>سأعود لهذا العميل اليوم</strong>
                                                <small class="d-block text-muted">
                                                    اختَرها إن طلب منك العودة لاحقاً. يكفيها المسؤول ودوامه والملاحظة —
                                                    تُقفل هذه الزيارة بمدّتها، ويبقى العميل في خطّ سيرك لزيارةٍ ثانية.
                                                </small>
                                            </span>
                                        </label>
                                        <div id="wsReturnTimeWrap" class="d-none mt-2">
                                            <label class="form-label small fw-bold mb-1">وقت العودة المتوقَّع</label>
                                            <input type="time" name="return_time" id="wsReturnTime" class="form-control form-control-sm">
                                            <small class="cl-help">إلزاميّ متى اخترت العودة — يظهر في خطّ سيرك ليذكّرك، ولا يمنعك من العودة قبله أو بعده.</small>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold mb-1">ملخّص الزيارة (اختياري)</label>
                                        <textarea name="outcome_notes" class="form-control form-control-sm" rows="2" placeholder="ما دار في الزيارة وما بقي للزيارة القادمة...">{{ old('outcome_notes', $visit->outcome_notes) }}</textarea>
                                    </div>
                                    @if ($canCompleteVisit)
                                        {{-- مفتوحٌ دائماً بعد البدء — لا `disabled`: الزرّ المعطَّل
                                             لا يقول شيئاً، فيضغطه المندوب ولا يعرف ما الناقص. عند
                                             الضغط يُفحص الناقص (`wsWarnIfIncomplete()`) فيُمرَّر إليه
                                             ويُبرَز ويُعرض «عليك: …» بزرٍّ يفتحه. والخادم
                                             (`complete()`) يرفض ما نقص على أيّ حال. --}}
                                        <button type="button" id="completeVisitBtn" class="btn btn-success w-100">
                                            <i class="fas fa-sign-out-alt me-1"></i>
                                            <span id="completeVisitBtnLabel">إنهاء الزيارة</span>
                                        </button>
                                    @else
                                        <div class="alert alert-secondary py-2 small mb-0">
                                            <i class="fa fa-lock me-1"></i>
                                            لا تملك صلاحية «إنهاء الزيارة» — راجع مشرفك لإغلاق هذه الزيارة.
                                        </div>
                                    @endif
                                </form>

                                @if ($canCancelVisit)
                                    <button type="button" class="btn btn-outline-danger w-100 mt-2" data-bs-toggle="modal" data-bs-target="#cancelVisitModal">
                                        <i class="fas fa-times-circle me-1"></i> إلغاء الزيارة
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== الملاحظات (كامل) ==================== --}}
        @if ($wsShow('show_notes_tab'))
        <div class="ws-tab-pane d-none" data-pane="notes">
            <div class="card">
                <div class="card-header">
                    <span>كل ملاحظات هذا العميل</span>
                    @if ($canAddNote)
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#noteModal" onclick="prepNoteModal(false)">+ ملاحظة جديدة</button>
                    @endif
                </div>
                <div class="card-body">
                    @forelse ($notes as $note)
                        <div class="ws-note-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="ws-note-badge">ملاحظة</span>
                                <small class="text-muted">{{ $note->created_at->format('Y-m-d h:i A') }}</small>
                            </div>
                            <p class="mb-1">{{ $note->description }}</p>
                            @php $noteAttachments = json_decode($note->attachments ?? '[]', true) ?: []; @endphp
                            @if (! empty($noteAttachments))
                                <div class="d-flex gap-2 flex-wrap mb-1">
                                    @foreach ($noteAttachments as $file)
                                        <a href="{{ asset('assets/uploads/notes/'.$file) }}" target="_blank">
                                            <img src="{{ asset('assets/uploads/notes/'.$file) }}" class="ws-thumb">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            <small class="text-muted d-block mt-1"><i class="fas fa-user me-1"></i>{{ $note->employee->name ?? 'موظف' }}</small>
                        </div>
                    @empty
                        <div class="empty-state"><i class="fas fa-sticky-note"></i><p class="mb-0">لا توجد ملاحظات بعد.</p></div>
                    @endforelse
                </div>
            </div>
        </div>
        @endif

        {{-- «العهد والأصول» و«الأنشطة والمهام (المواعيد)» حُذفتا من مساحة عمل
             الزيارة: الزيارة الآن سلسلةُ خطواتٍ إلزاميّة (مسؤول ← دوام ←
             ملاحظة ← مستند ← إنهاء)، وهما لم يكونا منها. جداولهما وبياناتهما
             باقيةٌ كما هي — `ClientCompanyAsset` ما زال يُعدّ في لوحة العملاء. --}}

        {{-- ==================== رصد المنافسين (كامل) ==================== --}}
        @if ($wsShow('show_competitors_tab'))
        <div class="ws-tab-pane d-none" data-pane="competitors">
            @include('client::visit_workspace.tab-competitors')
        </div>
        @endif

        {{-- ==================== المستندات ==================== --}}
        @if ($wsShow('show_docs_tab'))
        <div class="ws-tab-pane d-none" data-pane="docs">
            <div class="card ws-docs-card">
                <div class="card-header"><span><i class="fas fa-print text-primary me-1"></i> مستندات هذه الزيارة</span></div>
                <div class="card-body">
                    @include('client::partials.visit-documents')
                </div>
            </div>
        </div>
        @endif

        {{-- ==================== مستندات العميل ====================
             آخر 20 من كل نوع لهذا العميل أيّاً كان منشئها. زرّ الإنشاء في رأس التبويب
             يمرّ بـ`openDocument` فيعود المندوب إلى الزيارة بعد الحفظ، ومقفلٌ حتى تكتمل
             خطوات الزيارة — والخادم يعيد الفحص. --}}
        @php
            $wsClientTabs = [
                ['pane' => 'client-invoices', 'group' => 'invoices', 'title' => 'فواتير العميل', 'icon' => 'fa-file-invoice',
                    'create' => ['type' => 'invoice', 'label' => 'إنشاء فاتورة', 'show' => true], 'empty' => 'لا توجد فواتير لهذا العميل.'],
                ['pane' => 'client-receipts', 'group' => 'receipts', 'title' => 'سندات قبض العميل', 'icon' => 'fa-money-bill-wave',
                    'create' => ['type' => 'receipt', 'label' => 'سند قبض', 'show' => true], 'empty' => 'لا توجد سندات قبض لهذا العميل.'],
                ['pane' => 'client-credit-notes', 'group' => 'credit_notes', 'title' => 'الإشعارات الدائنة للعميل', 'icon' => 'fa-file-invoice-dollar',
                    'create' => ['type' => 'credit-note', 'label' => 'إشعار دائن', 'show' => $wsCanCreditNote], 'empty' => 'لا توجد إشعارات دائنة لهذا العميل.'],
                ['pane' => 'client-returns', 'group' => 'returns', 'title' => 'مرتجعات العميل', 'icon' => 'fa-undo',
                    'create' => null, 'empty' => 'لا توجد مرتجعات لهذا العميل.'],
            ];
        @endphp
        @foreach ($wsClientTabs as $wsTab)
        <div class="ws-tab-pane d-none" data-pane="{{ $wsTab['pane'] }}">
            <div class="card ws-docs-card">
                <div class="card-header">
                    <span><i class="fas {{ $wsTab['icon'] }} text-primary me-1"></i> {{ $wsTab['title'] }} <small class="text-muted">— آخر 20</small></span>
                    @if ($wsTab['create'] && $wsTab['create']['show'])
                        @if ($stDocsOpen)
                            <a href="{{ route('clients.visits.openDocument', ['visit' => $visit->id, 'type' => $wsTab['create']['type']]) }}" class="btn btn-sm btn-primary">+ {{ $wsTab['create']['label'] }}</a>
                        @else
                            <button type="button" class="btn btn-sm btn-primary" disabled title="مقفل حتى تُكمل خطوات الزيارة"><i class="fas fa-lock me-1"></i>{{ $wsTab['create']['label'] }}</button>
                        @endif
                    @endif
                </div>
                <div class="card-body">
                    @include('client::partials.visit-documents', [
                        'visitDocuments' => $clientDocuments->get($wsTab['group'], collect()),
                        'showDate' => true,
                        'emptyText' => $wsTab['empty'],
                    ])
                </div>
            </div>
        </div>
        @endforeach

        {{-- ==================== تسليم واستلام الفواتير ==================== --}}
        @if ($wsTransfersOn)
            @include('client::visit_workspace.tab-invoice-transfers')
        @endif

        {{-- ==================== المرفقات ==================== --}}
        @if ($wsShow('show_attachments_tab'))
        <div class="ws-tab-pane d-none" data-pane="attachments">
            <div class="card">
                <div class="card-body">
                    @if ($attachments->count())
                        <div class="ws-photo-grid">
                            @foreach ($attachments as $photo)
                                <a href="{{ $photo['url'] }}" target="_blank" class="ws-photo-item">
                                    <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}">
                                    <span class="ws-photo-label">{{ $photo['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state"><i class="fas fa-images"></i><p class="mb-0">لا توجد مرفقات لهذه الزيارة بعد.</p></div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ==================== المودالات ==================== --}}
    @if ($canAddNote)
    <div class="modal fade ws-compact-modal" id="noteModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-scrollable" style="max-width: 560px;">
            <div class="modal-content">
                <form method="POST" id="noteForm" action="{{ route('clients.visits.storeNote', $visit->id) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="current_latitude" id="noteLat">
                    <input type="hidden" name="current_longitude" id="noteLng">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-sticky-note me-1"></i> ملاحظة الزيارة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="noteGpsAlert" class="alert alert-warning py-1 px-2 mb-2 small">
                            <i class="fa fa-spinner fa-spin me-1"></i> جاري تحديد موقعك...
                        </div>

                        {{-- ترتيب الحقول: صفٌّ لـ«من قابلتَ ومتى»، وصفٌّ لـ«ما رأيتَه في
                             الموقع»، ثم الملاحظة فالمرفقات — بدل صفَّين يخلطان الشخص
                             بالعدّ بالوقت. و«نوع الموقع» حُذف من الشاشة: المتحكّم
                             (`ClientNoteController::addnotes`) لا يتحقّق منه أصلاً ويكتب
                             `markets` حين يغيب، فبقاؤه كان سؤالاً إلزامياً بلا أثر.
                             و«الدوام» صار قائمةً واحدةً بدل حقلَي وقتٍ حرَّين — وهي
                             القائمة السريعة نفسها، لكنّها الآن المُدخل لا مساعِدَه؛
                             `shift_start`/`shift_end` (إلزاميّان في المتحكّم) يُملآن
                             من اختيارها في حقلين مخفيَّين. --}}
                        {{-- «الدوام» لم يعد حقلاً يُملأ هنا: صار خاصّيةً لمسؤول
                             العميل تُسجَّل مرّةً في الخطوة الثانية وتُقرأ في كل
                             ملاحظة. المعروض هنا دوام المسؤول المختار للقراءة فقط،
                             و`shift_start`/`shift_end` (إلزاميّان في
                             `ClientNoteController::addnotes`) يملؤهما الخادم من
                             سجلّ المسؤول نفسه — لا من هذا النموذج، حتى لا يُلتفّ
                             على صلاحية تغيير الدوام بإرسال قيمةٍ أخرى من هنا. --}}
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">مسؤول العميل <span class="text-danger">*</span></label>
                                <select name="client_employee_id" id="wsNoteContactSelect" class="form-select" required onchange="wsNoteSyncShift()">
                                    <option value="">اختر مسؤول العميل</option>
                                    @forelse ($clientEmployees as $ce)
                                        <option value="{{ $ce->id }}"
                                            data-shift="{{ $ce->shift_label ?? '' }}"
                                            data-shift-range="{{ $ce->shift_range_value }}"
                                            data-has-shift="{{ $ce->hasShift() ? '1' : '0' }}"
                                            {{-- لا `selected` على خيارٍ معطَّل: مسؤولٌ بلا دوامٍ
                                                 لا يصلح مختاراً، والمتصفّح يُبقي قيمة الحقل عليه
                                                 فيبدو النموذج مملوءاً وهو غير قابلٍ للإرسال --}}
                                            @selected($contact && $ce->id === $contact->id)>
                                            {{ $ce->employee_name }}@if($ce->employee_position) — {{ $ce->employee_position }}@endif
                                            @unless ($ce->hasShift()) (بلا دوام مسجَّل) @endunless
                                        </option>
                                    @empty
                                        <option value="" disabled>لا يوجد مسؤولون لهذا العميل</option>
                                    @endforelse
                                </select>
                            </div>
                            {{-- الدوام في النافذة نفسها: يُسجَّل لمسؤولٍ بلا دوام، ويُعدَّل لمن دوامُه
                                 مسجَّل بصلاحية «تغيير الدوام» ويبقى مقروءاً لغيره (`wsNoteSyncShift()`).
                                 الخادم يعيد الحكم نفسه في `VisitWorkspaceController::storeNote()`. --}}
                            <div class="col-md-6" id="wsNoteShiftCol">
                                @include('client::partials.shift-select', ['label' => 'دوام المسؤول (من – إلى)'])
                                <small class="cl-help d-block fw-bold" id="wsNoteShiftHint">اختر المسؤول أولاً.</small>
                            </div>
                        </div>

                        {{-- «عدد العهدة» و«المرفقات» يتبعان مفتاحيهما في إعدادات
                             وحدات العملاء ← الزيارات. مطفأً لا يُعرض الحقل **ولا
                             يُطلب** في `ClientNoteController::addnotes()` — نفس
                             المفتاح يقرأه الاثنان، فلا يقع حقلٌ مخفيٌّ يرفض
                             الخادمُ الحفظ بسببه. --}}
                        <div class="row g-2 mb-2">
                            @if ($showNoteDepositCount)
                            <div class="col-md-6">
                                <label class="form-label">عدد العهدة الموجودة <span class="text-danger">*</span></label>
                                <input type="number" name="deposit_count" class="form-control" min="0" required>
                                <small class="cl-help">ما تراه الآن في الموقع — 0 إن لم توجد.</small>
                            </div>
                            @endif
                            {{-- «عدد مستندات المنافسين» حُذف: صار يُحسب من عدد
                                 المنافسين الذين تُضيفهم أسفل هذه النافذة
                                 (`VisitWorkspaceController::storeNote()`). سؤالُ
                                 المندوب عن رقمٍ يعرفه النظام من صفوفه تكرارٌ
                                 يُخطئ فيه. --}}
                        </div>

                        <div class="mb-2">
                            <label class="form-label">الملاحظة <span class="text-danger">*</span></label>
                            <textarea name="description" id="noteBodyInput" class="form-control" rows="2" placeholder="ما دار في الزيارة، وما طلبه العميل، وما وعدتَ به" required></textarea>
                        </div>

                        {{-- رصد المنافسين ليس هنا: خطوةٌ تالية بنافذتها المستقلّة
                             `#competitorStepModal` — أُدمج هنا يوماً ثم أُعيد مستقلّاً بطلب المستخدم. --}}

                        @if ($showNotePhoto)
                        <div class="mb-2">
                            <label class="form-label">
                                المرفقات <span class="text-danger">*</span>
                                <small class="text-muted fw-normal">— {{ $noteMinPhotos > 1 ? $noteMinPhotos.' صور على الأقل' : 'صورة أو أكثر' }} من الكاميرا</small>
                            </label>
                            <input type="file" name="attachments[]" multiple id="wsNoteAttachments" class="form-control ws-hidden-file-input"
                                accept="image/*" capture="environment" onchange="wsNoteHandleFileSelection(event)" required>

                            {{-- `<label for>` لا زرٌّ يستدعي `input.click()`: المتصفّح يفتح المنتقي
                                 بنفسه من لمسة المستخدم مباشرةً، فلا يُحجَب على متصفّحات الجوّال. --}}
                            <label for="wsNoteAttachments" class="ws-upload-btn" role="button">
                                <i class="fas fa-camera"></i> التقاط صورة <small>تُضغط تلقائياً</small>
                            </label>
                            {{-- قائمتا «جودة الضغط» و«أقصى حجم» حُذفتا لتصغير النافذة: `wsNoteCompressImage()`
                                 تسقط إلى إعدادات حدود المرفقات (`WS_UPLOAD_QUALITY` / `WS_UPLOAD_MAX_PX`) حين تغيبان. --}}

                            <div id="wsNoteCompressionProgress" class="mt-2 d-none">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted mt-1 d-block">يتم ضغط الملفات...</small>
                            </div>

                            <div id="wsNoteSelectedFiles" class="mt-2"></div>

                            <div id="wsNoteCompressionSummary" class="mt-2 d-none">
                                <div class="alert alert-success border-0 py-2">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-muted d-block">الحجم الأصلي</small>
                                            <span class="fw-bold small" id="wsNoteOriginalSize">0 MB</span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">بعد الضغط</small>
                                            <span class="fw-bold small text-success" id="wsNoteCompressedSize">0 MB</span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">نسبة التوفير</small>
                                            <span class="fw-bold small text-primary" id="wsNoteSavingsPercentage">0%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="noteSubmitBtn" disabled>حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- ==================== موظف العميل ====================
         نفس مسار الحفظ (`storeContact` ← `ClientNoteController::storeEmployeeNote`)
         الذي تستعمله نسخة شاشة العميل في partials/modals.blade.php، بحقول
         الموظف وحدها. --}}
    @if ($canAddNote)
    <div class="modal fade ws-compact-modal" id="addEmployeeNoteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-1"></i> إضافة موظف العميل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                {{-- `wsContactForm` لا `employeeNoteForm`: النموذج هنا يُرسَل
                     بسكربت هذه الشاشة إلى `clients.visits.storeContact` ثم
                     **تُعاد الصفحة** — «وعند الحفظ يرجع في الزيارة ويكمل».

                     موظفُ العميل وصورته الاختيارية — لا ملاحظة ولا ربط فاتورة.
                     النافذة نفسها في شاشة العميل (`partials/modals.blade.php`)،
                     غيّرهما معاً. --}}
                <form id="wsContactForm" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-sm-7">
                                <label class="form-label">اسم الموظف <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="employee_name"
                                    placeholder="موظف المحل لا موظف النظام" maxlength="255" required>
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label">الجوال <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="employee_phone"
                                    placeholder="05xxxxxxxx" pattern="[0-9+\-\s()]*" maxlength="20" required>
                            </div>

                            {{-- دوام الموظف يُسجَّل معه — الجزئية المشتركة نفسها
                                 (`client::partials.shift-select`) التي تستعملها نافذة
                                 الملاحظة ومودال تغيير الدوام. --}}
                            <div class="col-12">
                                @include('client::partials.shift-select')
                            </div>

                            <div class="col-sm-7">
                                <label class="form-label">الوظيفة</label>
                                <select class="form-control" name="employee_position">
                                    <option value="موظف">موظف</option>
                                    <option value="مشرف">مشرف</option>
                                    <option value="كاشير">كاشير</option>
                                    <option value="موظف مبيعات">موظف مبيعات</option>
                                    <option value="مدير فرع">مدير فرع</option>
                                    <option value="أخرى">أخرى</option>
                                </select>
                            </div>
                            <div class="col-sm-5 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_primary" id="isPrimaryCheck" value="1">
                                    <label class="form-check-label" for="isPrimaryCheck">موظف رئيسي</label>
                                </div>
                            </div>

                            {{-- صورة الموظف — اختيارية، تُضغط في المتصفّح قبل الإرسال --}}
                            <div class="col-12">
                                <label class="form-label">صورة الموظف <small class="text-muted">(اختيارية)</small></label>
                                <input type="file" class="ws-hidden-file-input" name="image" accept="image/*" capture="environment" id="wsContactImageInput">
                                <div class="d-flex align-items-center gap-2">
                                    <label for="wsContactImageInput" class="btn btn-outline-primary btn-sm mb-0" role="button">
                                        <i class="fas fa-camera me-1"></i> إرفاق صورة
                                    </label>
                                    <span id="wsContactImagePreview" class="align-items-center gap-2" style="display: none;">
                                        <img id="wsContactImageThumb" src="" alt="" style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px;">
                                        <button type="button" class="btn btn-sm btn-light text-danger" id="wsContactImageRemove" title="إزالة الصورة"><i class="fa fa-times"></i></button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save me-1"></i>حفظ الموظف
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- ==================== منافس جديد ====================
         مودالٌ مستقلّ يُفتح **فوق** مودال الملاحظة. Bootstrap 5.3 يرصّ
         المودالات ويرفع z-index تلقائياً، لكنّه يُعيد `overflow:hidden` إلى
         `<body>` عند إغلاق العلويّ فيتجمّد تمرير السفليّ — يُعالَج في
         `hidden.bs.modal` أسفل الصفحة.

         وهو خارج `#noteModal` في الـDOM عمداً: مودالٌ **داخل** آخر يرثه
         Bootstrap ضمن شجرته فيُغلقهما معاً. --}}
    @if ($canAddNote && $showCompetitorCapture)
    <div class="modal fade ws-compact-modal" id="addCompetitorModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-star text-warning me-1"></i> إضافة منافس</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">اسم المنافس <span class="text-danger">*</span></label>
                    <input type="text" id="wsNewCompetitorName" class="form-control"
                        placeholder="مثال: شركة الأغذية الوطنية" maxlength="255">
                    <small class="cl-help">يُضاف إلى قائمة المنافسين لكل المندوبين، ويُختار في السطر مباشرةً بعد الحفظ. اكتب الاسم التجاري لا اسم المنتج.</small>
                    <div id="wsNewCompetitorMsg" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-success" onclick="wsSaveNewCompetitor()">حفظ واختيار</button>
                </div>
            </div>
        </div>
    </div>
    @endif


    {{-- ==================== رصد المنافسين — الخطوة بعد الملاحظة ====================
         مودالٌ مستقلّ (أُدمج في مودال الملاحظة ثم أُعيد مستقلّاً بطلب المستخدم):
         منافس + عدد قطع عهدته + صورته، صفوفٌ تُبنى بـJS ولا يتكرّر فيها منافس.
         «منافس جديد» يفتح `#addCompetitorModal` فوقه ويختار الجديد في سطرٍ فارغ.
         بحفظه تُفتح المستندات (`VisitWorkspaceController::storeCompetitorStep()`).
         صورة كل منافس إلزامية — في الخادم (`competitorValidationError()`) وهنا. --}}
    @if ($canAddNote && $showCompetitorCapture)
    <div class="modal fade ws-compact-modal" id="competitorStepModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-scrollable" style="max-width: 540px;">
            <div class="modal-content">
                <form method="POST" id="competitorStepForm" action="{{ route('clients.visits.storeCompetitorStep', $visit->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-crosshairs text-danger"></i> رصد المنافسين</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="wsNoteCompetitorSection">
                        <p class="ws-comp-intro">لكل منافسٍ عند العميل: <strong>عدد قطع عهدته</strong> (0 إن لم توجد) و<strong>صورته</strong> — الصورة إلزامية.</p>

                        @if ($competitorOptions->isEmpty())
                            <div class="alert alert-warning py-1 px-2 small mb-2" id="wsCompetitorEmptyHint">
                                <i class="fa fa-exclamation-triangle me-1"></i>
                                لا يوجد منافسون في القائمة بعد — أضِف أوّلهم من «منافس جديد للقائمة».
                            </div>
                        @endif

                        <div class="ws-comp-head"><span>المنافس</span><span class="text-center">العهدة</span><span class="text-center">صورة</span><span></span></div>
                        <div id="wsCompetitorRows"></div>

                        <div class="ws-comp-tools">
                            <button type="button" class="btn btn-sm btn-light" onclick="wsAddCompetitorRow()">
                                <i class="fas fa-plus me-1"></i> منافس آخر
                            </button>
                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addCompetitorModal">
                                <i class="fas fa-star text-warning me-1"></i> منافس جديد للقائمة
                            </button>
                        </div>

                        <label class="ws-comp-none" for="wsNoCompetitors">
                            <input type="checkbox" name="no_competitors" value="1" id="wsNoCompetitors" class="form-check-input" onchange="wsToggleNoCompetitors()">
                            <span>
                                <strong>لا يوجد منافس عند هذا العميل</strong>
                                <small>يُسجَّل أنّك نظرتَ ولم تجد منافساً — صورة إثبات مرفوعة إلزامية.</small>
                            </span>
                        </label>

                        {{-- حقل الصورة عند اختيار «لا يوجد منافس» - صورة إثبات إلزامية --}}
                        <div id="wsNoCompetitorsPhotoBlock" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--ws-bg); border-radius: var(--cl-r, 8px); border: 1px solid var(--ws-border);">
                            <label for="wsNoCompetitorsPhoto" class="form-label fw-bold">
                                صورة إثبات البحث <span class="text-danger">*</span>
                                <small class="d-block text-muted fw-normal" style="margin-top: 0.25rem;">صورة توثّق أنك بحثت عن منافسين في المكان</small>
                            </label>
                            <div class="mb-2">
                                <input type="file" name="no_competitors_photo" id="wsNoCompetitorsPhoto" class="form-control" accept="image/*" capture="environment">
                            </div>
                            <div id="wsNoCompetitorsPhotoPreview" style="margin-top: 0.5rem;"></div>
                        </div>

                        <div id="wsCompetitorRequiredMsg" class="small text-danger fw-bold mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ الرصد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- ==================== الخطوة ٢: دوام المسؤول ====================
         الدوام يُسجَّل على المسؤول نفسه مرّةً (`client_employee_notes`)، لا في
         كل ملاحظة. تسجيلُه أوّل مرّة متاحٌ للجميع؛ تغييرُ دوامٍ مسجَّلٍ من قبل
         يحتاج `clients_change_contact_shift` — والشرط نفسه مطبَّقٌ في
         `VisitWorkspaceController::storeContactShift()` بالخادم، فقفلُ الأزرار
         هنا بصريٌّ فقط.

         مودالات «إضافة أصل»، و«تسجيل فحص»، و«إضافة مهمة للمتابعة» حُذفت مع
         بطاقاتها وتبويباتها ومساراتها — العهد والأصول والمواعيد خرجت من مساحة
         عمل الزيارة. --}}
    <div class="modal fade" id="contactShiftModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('clients.visits.storeContactShift', $visit->id) }}">
                    @csrf
                    <input type="hidden" name="client_employee_id" id="wsShiftContactId">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <span id="wsShiftModalTitle">تسجيل دوام المسؤول</span>:
                            <span id="wsShiftContactName" class="text-muted"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="cl-help mb-3">
                            ساعات تواجد <strong>الشخص الذي تتعامل معه</strong>، لا ساعات فتح المحل — تُجدوَل بها
                            الزيارة القادمة في وقتٍ تجده فيه. تُسجَّل مرّةً وتُقرأ في كل زيارةٍ قادمة، فلا تعيد إدخالها
                            مع كل ملاحظة.
                        </p>
                        @include('client::partials.shift-select', ['label' => 'فترة الدوام'])
                        <div id="wsShiftChangeWarning" class="alert alert-warning py-2 small mt-3 d-none">
                            <i class="fa fa-exclamation-triangle me-1"></i>
                            هذا المسؤول له دوامٌ مسجَّلٌ من قبل — تغييرُه يُبدّل ما تُجدوَل به زياراته القادمة.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ والعودة للزيارة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- مودال «رصد منافس» حُذف: الرصد صار داخل ملاحظة الزيارة (قسم «رصد
         المنافسين» في مودال الملاحظة)، وتبويبُ المنافسين صار للعرض فقط —
         مدخلٌ واحد للبيان الواحد. ومسار `clients.visits.storeCompetitor`
         حُذف معه. --}}

    {{-- الردّ على إشعار الإدارة — نموذجٌ واحد يُملأ هدفُه من الزرّ المضغوط،
         لا مودالٌ لكلّ إشعار. --}}
    @if ($canReplyToNotifications && $clientNotifications->isNotEmpty())
    <div class="modal fade" id="wsNotifReplyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="wsNotifReplyForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-reply me-1"></i> ردّ على إشعار الإدارة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="cl-help mb-3">الإشعار: <strong id="wsNotifReplyTitle"></strong></p>
                        <label class="form-label fw-bold">ردّك <span class="text-danger">*</span></label>
                        <textarea name="reply_message" class="form-control" rows="3" maxlength="500" required
                            placeholder="مثال: كلّمته، وطلب تأجيل السداد لنهاية الأسبوع"></textarea>
                        <small class="cl-help">يصل ردُّك إشعاراً لمن أرسل إليك، ويُعلَّم الإشعار الأصلي مقروءاً.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-primary">إرسال الردّ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- طلب تغيير حالة العميل — ينشُر إلى نفس مسار ملفّ العميل
         (`clients.updateStatusClient`، محروسٌ بـ`permission:clients_change_status`)
         بلا نسخةٍ ثانية من المنطق، ويعود `back()` إلى هذه الزيارة. --}}
    @if ($wsStatusShow)
    <div class="modal fade" id="wsClientStatusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('clients.updateStatusClient') }}">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    {{-- رقمُ الزيارة يُختم في سبب الطلب، فيعرف المُعتمِد أنّه ناتجُ معاينةٍ ميدانية --}}
                    <input type="hidden" name="visit_id" value="{{ $visit->id }}">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas {{ $wsStatusCurrentKpi === 'stopped' ? 'fa-play-circle' : 'fa-ban' }} me-1"></i>
                            {{ $wsStatusBtnLabel }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <p class="cl-help mb-3" data-hint-keep>
                            @if ($clientStatusNeedsApproval)
                                هذا <strong>طلبٌ</strong> لا تغييرٌ فوريّ: حالة العميل تبقى
                                «{{ optional($client->status_client)->name ?? 'كما هي' }}» حتى يعتمده المسؤول الذي تختاره،
                                ويصله إشعارٌ به الآن. لن تتأثّر هذه الزيارة ولا مستنداتها بالطلب.
                            @else
                                تغييرٌ فوريّ بصلاحيتك — تُبدَّل حالة العميل فور الحفظ ويُسجَّل التغيير بسببه في سجلّ العميل.
                            @endif
                        </p>

                        <div class="mb-3">
                            <label class="form-label fw-bold">الحالة المطلوبة <span class="text-danger">*</span></label>
                            <select name="status_id" class="form-select" required>
                                @foreach ($clientStatusOptions as $wsStatusOption)
                                    @continue($wsStatusOption->id == $client->status_id)
                                    <option value="{{ $wsStatusOption->id }}" @selected($wsStatusTarget && $wsStatusOption->id === $wsStatusTarget->id)>
                                        {{ $wsStatusOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="cl-help">
                                «موقوف» يُخرج العميل من عدّاد العملاء النشطين وتقاريرهم — لا يحذفه ولا يمسّ أرصدته ولا فواتيره السابقة.
                            </small>
                        </div>

                        @if ($clientStatusNeedsApproval)
                            <div class="mb-3">
                                <label class="form-label fw-bold">المسؤول المعتمِد <span class="text-danger">*</span></label>
                                <select name="manager_id" class="form-select" required>
                                    <option value="">اختر المسؤول...</option>
                                    @foreach ($clientStatusApprovers as $wsApprover)
                                        <option value="{{ $wsApprover->id }}">{{ $wsApprover->name }}</option>
                                    @endforeach
                                </select>
                                <small class="cl-help">من يملكون صلاحية اعتماد حالات العملاء — الطلب يصل إشعاراً لمن تختاره وحده.</small>
                            </div>
                        @endif

                        <div class="mb-1">
                            <label class="form-label fw-bold">السبب <span class="text-danger">*</span></label>
                            <textarea name="notes" class="form-control" rows="3" maxlength="1000" required
                                placeholder="مثال: المحل مُغلق نهائياً منذ شهر، واللوحة أُزيلت"></textarea>
                            <small class="cl-help">
                                السببُ هو كلّ ما يراه المُعتمِد عن هذا العميل — اكتب ما رأيتَه اليوم لا حكماً عاماً. يُحفظ في سجلّ العميل الدائم مع رقم هذه الزيارة.
                            </small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn {{ $wsStatusCurrentKpi === 'stopped' ? 'btn-success' : 'btn-danger' }}">
                            {{ $clientStatusNeedsApproval ? 'إرسال الطلب' : 'تأكيد التغيير' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- طلب تغيير وضع العميل — نفس نمط مودال الحالة أعلاه حرفياً، ينشُر إلى
         `clients.updateStageClient` (محروسٌ بـ`permission:clients_change_status`)
         ويعود `back()` إلى هذه الزيارة. --}}
    @if ($wsStageShow)
    <div class="modal fade" id="wsClientStageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('clients.updateStageClient') }}">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="visit_id" value="{{ $visit->id }}">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-hourglass-half me-1"></i>
                            {{ $wsStageBtnLabel }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <p class="cl-help mb-3" data-hint-keep>
                            @if ($clientStageNeedsApproval)
                                هذا <strong>طلبٌ</strong> لا تغييرٌ فوريّ: وضع العميل يبقى
                                «{{ optional($client->registrationStage)->name ?? 'بلا وضع' }}» حتى يعتمده المسؤول الذي تختاره،
                                ويصله إشعارٌ به الآن. لن تتأثّر هذه الزيارة ولا مستنداتها بالطلب.
                            @else
                                تغييرٌ فوريّ بصلاحيتك — يُبدَّل وضع العميل فور الحفظ ويُسجَّل التغيير بسببه في سجلّ العميل.
                            @endif
                        </p>

                        <div class="mb-3">
                            <label class="form-label fw-bold">الوضع المطلوب</label>
                            <select name="stage_id" class="form-select">
                                <option value="" @selected(! $client->registration_stage_id)>بلا وضع</option>
                                @foreach ($clientStageOptions as $wsStageOption)
                                    @continue($wsStageOption->id == $client->registration_stage_id)
                                    <option value="{{ $wsStageOption->id }}">
                                        {{ $wsStageOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="cl-help">
                                وضعٌ تجاريٌّ إضافي على العميل — لا يؤثّر على حالته التشغيلية (نشط/موقوف) ولا يمسّ أرصدته ولا فواتيره.
                            </small>
                        </div>

                        @if ($clientStageNeedsApproval)
                            <div class="mb-3">
                                <label class="form-label fw-bold">المسؤول المعتمِد <span class="text-danger">*</span></label>
                                <select name="manager_id" class="form-select" required>
                                    <option value="">اختر المسؤول...</option>
                                    @foreach ($clientStageApprovers as $wsStageApprover)
                                        <option value="{{ $wsStageApprover->id }}">{{ $wsStageApprover->name }}</option>
                                    @endforeach
                                </select>
                                <small class="cl-help">من يملكون صلاحية اعتماد وضع العملاء — الطلب يصل إشعاراً لمن تختاره وحده.</small>
                            </div>
                        @endif

                        <div class="mb-1">
                            <label class="form-label fw-bold">السبب <span class="text-danger">*</span></label>
                            <textarea name="notes" class="form-control" rows="3" maxlength="1000" required
                                placeholder="مثال: العميل قدّم ملفه الضريبي وصار وضعه «ضريبي»"></textarea>
                            <small class="cl-help">
                                السببُ هو كلّ ما يراه المُعتمِد عن هذا العميل — اكتب ما رأيتَه اليوم لا حكماً عاماً. يُحفظ في سجلّ العميل الدائم مع رقم هذه الزيارة.
                            </small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-warning">
                            {{ $clientStageNeedsApproval ? 'إرسال الطلب' : 'تأكيد التغيير' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if ($canCancelVisit)
    <div class="modal fade" id="cancelVisitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('clients.visits.cancelWorkspace', $visit->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">إلغاء الزيارة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <input type="hidden" name="current_latitude" id="cancelLat">
                    <input type="hidden" name="current_longitude" id="cancelLng">
                    <div class="modal-body">
                        {{-- الإلغاء يشترط الموقع كالإنهاء تماماً: كان يُقفل الزيارة
                             من أيّ مكانٍ بحجّة «المحل مغلق». --}}
                        <div id="cancelGpsAlert" class="alert alert-warning py-2 mb-3 small">
                            <i class="fa fa-spinner fa-spin me-1"></i> جاري تحديد موقعك...
                        </div>
                        <label class="form-label">سبب الإلغاء <span class="text-danger">*</span></label>
                        <textarea name="cancel_reason" class="form-control" rows="2" required
                            placeholder="مثال: المحل مغلق، المسؤول غير موجود"></textarea>
                        <small class="cl-help">الإلغاء يُنهي الزيارة بلا نتيجةٍ ولا توقيع ولا يمكن التراجع عنه — والسبب هو كلّ ما يبقى منها في التقرير، ويُحفظ معه موقعك لحظة الإلغاء.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-danger" id="cancelVisitSubmit" disabled>تأكيد الإلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

@endsection

@section('scripts')
    {{-- حقول الهاتف والجوال: رقمٌ سعودي يبدأ بصفر وطولُه 10 — نظيرُ App\Rules\SaudiPhone --}}
    <script src="{{ asset('assets/js/saudi-phone.js') }}?v=1"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

    @if ($canReplyToNotifications && $clientNotifications->isNotEmpty())
    <script>
        // مودالٌ واحد لكلّ الإشعارات: الزرّ المضغوط يكتب فيه هدفَه قبل الفتح
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('wsNotifReplyForm');
            const titleEl = document.getElementById('wsNotifReplyTitle');
            const template = "{{ route('clients.visits.notifications.reply', ['visit' => $visit->id, 'notification' => '__ID__']) }}";

            document.querySelectorAll('[data-bs-target="#wsNotifReplyModal"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    form.action = template.replace('__ID__', this.dataset.notifId);
                    titleEl.textContent = this.dataset.notifTitle || '';
                    form.querySelector('textarea[name="reply_message"]').value = '';
                });
            });
        });
    </script>
    @endif
    @if ($canAddNote)
        {{-- سكربت `clients.show` المشترك (`js/client/employee-notes.js`) لم
             يعد يُحمَّل هنا: هو يرسل النموذج ثم يحدّث تبويب «ملاحظات الموظفين»
             في تلك الشاشة — تبويبٌ لا وجود له هنا، فتُحفَظ البيانات وتبقى
             خطوة الزيارة كما هي في نظر المندوب. الإرسال في هذه الشاشة أدناه:
             حفظٌ ثم **إعادة تحميل الصفحة**، فتتقدّم الخطوة أمام عينه. --}}
    @endif
    <script>
        // ===== تبديل تبويبات الزيارة (JS بسيط، بلا Bootstrap tabs) =====
        function wsGoToTab(tabKey) {
            document.querySelectorAll('#wsTabBar button').forEach(b => b.classList.toggle('active', b.dataset.tab === tabKey));
            document.querySelectorAll('.ws-tab-pane').forEach(p => p.classList.toggle('d-none', p.dataset.pane !== tabKey));
        }
        document.querySelectorAll('#wsTabBar button').forEach(btn => {
            btn.addEventListener('click', () => wsGoToTab(btn.dataset.tab));
        });
        // العودة إلى تبويبٍ بعينه بعد إعادة التحميل (#ws-<tab>) — يضعه تبويب التحويلات بعد كلّ عملية
        if (location.hash.indexOf('#ws-') === 0 && document.querySelector('#wsTabBar button[data-tab="' + location.hash.slice(4) + '"]')) {
            wsGoToTab(location.hash.slice(4));
        }

        /* ===== ما الذي لم يكتمل بعد؟ =====
           مصدرٌ واحد لنصّ التنبيه يقرؤه زرّ «إنهاء الزيارة»
           معاً، مبنيٌّ على حالة الخطوات التي يحسبها الخادم
           (`VisitWorkspaceController::stepState()`) — فلا يقول أحدهما شيئاً
           ويقول الآخر غيره. */
        var WS_STEPS = @json($steps);
        /* صلاحية «بدء الزيارة وإنهاؤها وإلغاؤها من أي موقع». الخادم يُسقط فحص
           المسافة لحاملها (`VisitWorkspaceController::outOfRangeMessage()`)،
           فلا يصحّ أن تُبقي الواجهة أزرارها معطَّلةً بانتظار موقعٍ لا يُطلب —
           ولا أن يوقف المؤقّتُ نفسه كلما ابتعد. */
        var WS_ANY_LOCATION = @json($canVisitAnyLocation);

        /* أوّلُ خطوةٍ ناقصة ومكانُها في الشاشة — الترتيب هو ترتيب السلسلة،
           فلا يُرسَل المندوب إلى الملاحظة وهو لم يسجّل المسؤول بعد.

           `forReturn`: «سأعود لهذا العميل اليوم» يكفيه المسؤول ودوامه
           والملاحظة — رصد المنافسين للإنهاء التامّ وحده. نفس ما يفرضه
           `complete()` بـ`can_return`/`can_complete`. */
        /* المسؤول الأوّل بلا دوام — لزرّ «سجّل الدوام الآن» في شريط التنبيه */
        var WS_UNSHIFTED_CONTACT = @json(optional($clientEmployees->first(fn ($c) => ! $c->hasShift()), fn ($c) => ['id' => $c->id, 'name' => $c->employee_name]));

        function wsOpenModal(id) {
            var el = document.getElementById(id);
            if (el && window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
        }

        function wsFirstMissing(forReturn) {
            if (!WS_STEPS.has_contact) {
                return {
                    target: '#stepContactCard',
                    text: 'عليك أوّلاً: إضافة مسؤول العميل.',
                    action: { label: 'أضِف المسؤول الآن', run: function () { wsOpenModal('addEmployeeNoteModal'); } },
                };
            }
            if (!WS_STEPS.has_shift) {
                return {
                    target: '#stepContactCard',
                    text: 'عليك أوّلاً: تسجيل دوام المسؤول' + (WS_UNSHIFTED_CONTACT ? ' «' + WS_UNSHIFTED_CONTACT.name + '»' : '') + '.',
                    action: WS_UNSHIFTED_CONTACT ? {
                        label: 'سجّل الدوام الآن',
                        run: function () { wsOpenShiftModal(WS_UNSHIFTED_CONTACT.id, WS_UNSHIFTED_CONTACT.name, '', false); },
                    } : null,
                };
            }
            if (!WS_STEPS.has_note) {
                return {
                    target: '#stepNoteCard',
                    text: 'عليك أوّلاً: تسجيل ملاحظة الزيارة (المسؤول والعهدة والمرفق والملاحظة).',
                    action: { label: 'سجّل الملاحظة الآن', run: function () { wsOpenModal('noteModal'); prepNoteModal(false); } },
                };
            }
            if (!forReturn && !WS_STEPS.has_competitors) {
                return {
                    target: '#stepCompetitorCard',
                    text: 'عليك أوّلاً: رصد المنافسين — أو أشِّر «سأعود لهذا العميل اليوم» لتُقفل الزيارة ويبقى العميل في خطّ سيرك.',
                    action: { label: 'ارصد المنافسين الآن', run: function () { wsOpenModal('competitorStepModal'); } },
                };
            }
            return null;
        }

        /* بديلُ `alert`: يُظهر الشريط، ويمرّر الصفحة إلى البطاقة الناقصة،
           ويُبرزها ثوانيَ حتى تقع عليها العين. لا نافذةَ تُغلق فيُنسى ما فيها. */
        /* بديلُ `alert`: يُظهر الشريط، ويمرّر الصفحة إلى العنصر الناقص،
           ويُبرزه ثوانيَ حتى تقع عليه العين. لا نافذةَ تُغلق فيُنسى ما فيها. */
        function wsShowRequired(text, targetSelector, action) {
            var banner = document.getElementById('wsRequiredBanner');
            var bannerText = document.getElementById('wsRequiredBannerText');
            if (banner && bannerText) {
                bannerText.textContent = text;
                banner.classList.remove('d-none');
            }

            // زرّ «افعلها الآن» — يفتح الخطوة الناقصة نفسها
            var actionBtn = document.getElementById('wsRequiredAction');
            if (actionBtn) {
                actionBtn.classList.toggle('d-none', !action);
                actionBtn.onclick = null;
                if (action) {
                    actionBtn.textContent = action.label;
                    actionBtn.onclick = function () {
                        banner.classList.add('d-none');
                        action.run();
                    };
                }
            }

            // عنصرٌ أخفاه المدير من الإعدادات لا وجود له — الشريط وحده يكفي
            // حينها، ولا نمرّر إلى لا شيء
            var el = targetSelector ? document.querySelector(targetSelector) : null;
            if (!el) return;

            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.remove('ws-needs-action');
            void el.offsetWidth; // إعادة تشغيل الرسم المتحرّك عند الضغط المتكرّر
            el.classList.add('ws-needs-action');
            setTimeout(function () { el.classList.remove('ws-needs-action'); }, 4000);
        }

        function wsWarnIfIncomplete() {
            var missing = wsFirstMissing(!!document.getElementById('wsReturnLater')?.checked);
            if (!missing) return false;

            wsShowRequired('لا يمكن إنهاء الزيارة بعد — ' + missing.text, missing.target, missing.action);

            return true;
        }

        document.getElementById('wsReturnLater')?.addEventListener('change', function () {
            var label = document.getElementById('completeVisitBtnLabel');
            if (label) label.textContent = this.checked ? 'إنهاء الزيارة — سأعود لاحقاً' : 'إنهاء الزيارة';
        });

        // الشريط يختفي فور شروع المندوب في المطلوب، فلا يبقى معلّقاً بعد إنجازه
        ['noteModal', 'competitorStepModal', 'addEmployeeNoteModal', 'contactShiftModal'].forEach(function (id) {
            document.getElementById(id)?.addEventListener('shown.bs.modal', function () {
                document.getElementById('wsRequiredBanner')?.classList.add('d-none');
            });
        });

        // ===== موقع الإلغاء — نفس شرط الإنهاء =====
        var wsCancelModalEl = document.getElementById('cancelVisitModal');
        if (wsCancelModalEl) {
            wsCancelModalEl.addEventListener('shown.bs.modal', function () {
                var box = document.getElementById('cancelGpsAlert');
                var btn = document.getElementById('cancelVisitSubmit');
                btn.disabled = true;

                if (!navigator.geolocation) {
                    box.className = 'alert alert-danger py-2 mb-3 small';
                    box.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i> المتصفح لا يدعم تحديد الموقع — لا يمكن الإلغاء بدونه.';
                    if (WS_ANY_LOCATION) { btn.disabled = false; }
                    return;
                }

                box.className = 'alert alert-warning py-2 mb-3 small';
                box.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> جاري تحديد موقعك...';

                navigator.geolocation.getCurrentPosition(function (pos) {
                    document.getElementById('cancelLat').value = pos.coords.latitude;
                    document.getElementById('cancelLng').value = pos.coords.longitude;
                    box.className = 'alert alert-success py-2 mb-3 small';
                    box.innerHTML = '<i class="fa fa-check-circle me-1"></i> تم تحديد موقعك.';
                    btn.disabled = false;
                }, function () {
                    box.className = 'alert alert-danger py-2 mb-3 small';
                    box.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i> تعذّر تحديد موقعك — فعّل GPS وأذن الوصول ثم أعد فتح النافذة.';
                    if (WS_ANY_LOCATION) {
                        box.className = 'alert alert-info py-2 mb-3 small';
                        box.innerHTML = '<i class="fa fa-info-circle me-1"></i> تعذّر تحديد موقعك، ولديك صلاحية الإلغاء من أي موقع.';
                        btn.disabled = false;
                    }
                }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
            });
        }

        // ===== الخطوة ١: حفظ مسؤول العميل ثم العودة إلى الزيارة =====
        // «وعند الحفظ يرجع في الزيارة ويكمل»: إعادةُ تحميل الصفحة هي العودة —
        // الخادم يعيد حساب الخطوات (`stepState()`) فتُفتح الخطوة التالية وحدها.
        document.getElementById('wsContactForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            const original = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> جاري الحفظ...'; }

            fetch("{{ route('clients.visits.storeContact', $visit->id) }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: new FormData(form),
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    window.location.reload();
                    return;
                }
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
                alert(data.message || 'تعذّر حفظ مسؤول العميل.');
            }).catch(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
                alert('حدث خطأ أثناء حفظ مسؤول العميل.');
            });
        });

        // صورة موظف العميل — تُضغط بمُضغِط صور الملاحظة نفسه ثم تحلّ محلّ الملف الأصلي
        (function () {
            const input = document.getElementById('wsContactImageInput');
            if (!input) return;
            const preview = document.getElementById('wsContactImagePreview');
            const thumb = document.getElementById('wsContactImageThumb');
            const clear = function () {
                input.value = '';
                thumb.src = '';
                preview.style.display = 'none';
            };

            input.addEventListener('change', async function () {
                const file = input.files[0];
                if (!file) return clear();
                if (!file.type.startsWith('image/')) {
                    clear();
                    return;
                }
                let finalFile = file;
                if (typeof wsNoteCompressImage === 'function') {
                    try {
                        finalFile = (await wsNoteCompressImage(file)).file;
                        const dt = new DataTransfer();
                        dt.items.add(finalFile);
                        input.files = dt.files;
                    } catch (e) { finalFile = file; }
                }
                thumb.src = URL.createObjectURL(finalFile);
                preview.style.display = 'inline-flex';
            });

            document.getElementById('wsContactImageRemove')?.addEventListener('click', clear);
            document.getElementById('addEmployeeNoteModal')?.addEventListener('hidden.bs.modal', clear);
        })();

        // ===== الخطوة ٢: دوام المسؤول =====
        // نموذجٌ عاديٌّ يُرسَل للخادم ويعود بإعادة توجيهٍ إلى هذه الصفحة نفسها
        // (`storeContactShift()`), فلا حاجة لـfetch هنا. الدالّة تُهيّئ المودال فقط.
        function wsOpenShiftModal(contactId, contactName, shiftRange, hasShift) {
            const modalEl = document.getElementById('contactShiftModal');
            document.getElementById('wsShiftContactId').value = contactId;
            document.getElementById('wsShiftContactName').textContent = contactName || '';
            document.getElementById('wsShiftModalTitle').textContent = hasShift ? 'تغيير دوام المسؤول' : 'تسجيل دوام المسؤول';
            document.getElementById('wsShiftChangeWarning').classList.toggle('d-none', !hasShift);

            /* القائمة مبنيّةٌ في المتصفّح، والاختيار يُضبط عبر jQuery متى
               كان select2 مفعَّلاً — ضبط `value` وحده لا يحدّث واجهته
               المعروضة، فيبقى معروضاً «اختر فترة الدوام» فوق قيمةٍ مختارة.

               ودوامٌ قديمٌ بدقائق غير صفرية (`08:30`) لا يطابق أيّ خيار،
               فيبقى فارغاً — وهو الصحيح: يُختار صراحةً بدل عرض وقتٍ ليس هو
               المحفوظ. */
            const select = modalEl.querySelector('[data-shift-select]');
            if (select) {
                const value = hasShift ? (shiftRange || '') : '';
                if (window.jQuery && window.jQuery.fn.select2 && select.classList.contains('select2-hidden-accessible')) {
                    window.jQuery(select).val(value).trigger('change');
                } else {
                    select.value = value;
                }
            }

            new bootstrap.Modal(modalEl).show();
        }

        // ===== القفل قبل بدء الزيارة =====
        // كلُّ عنصرٍ فعّالٍ داخل تبويبات الزيارة يُعطَّل فعلياً (لا بالمظهر
        // وحده) حتى يُسجَّل `started_at`: التبويبات نفسها ورأس الصفحة
        // خارج النطاق، فيبقى التنقّل والاطّلاع ممكناً وزرّا «بدء الزيارة»
        // و«إلغاء الزيارة» (`data-ws-allow`) وحدهما عاملَين.
        // هذا القفل للواجهة فقط — البوّابة الفعلية في الخادم
        // (`VisitWorkspaceController::blockUntilStarted()`)، لأنّ أيّ تعطيلٍ
        // هنا يزول بأداة مطوّرين أو بطلبٍ مصنوع.
        var wsLocked = {{ $wsLocked ? 'true' : 'false' }};

        function wsApplyLock() {
            if (!wsLocked) return;

            document.querySelectorAll('.visit-ws .ws-tab-pane').forEach(function (pane) {
                pane.querySelectorAll('button, a, input, select, textarea, label[for]').forEach(function (el) {
                    if (el.closest('[data-ws-allow]')) return;

                    el.classList.add('ws-locked-el');
                    el.setAttribute('aria-disabled', 'true');
                    el.setAttribute('tabindex', '-1');
                    el.removeAttribute('data-bs-toggle');
                    el.removeAttribute('data-bs-target');
                    el.removeAttribute('onclick');

                    if (el.tagName === 'A') {
                        el.removeAttribute('href');
                        el.removeAttribute('target');
                    } else if ('disabled' in el) {
                        el.disabled = true;
                    }
                });

                // مفتاح Enter داخل حقلٍ بقي فعّالاً لسببٍ ما يرسل النموذج بلا نقرة
                pane.querySelectorAll('form').forEach(function (form) {
                    if (form.closest('[data-ws-allow]')) return;
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        alert('لم تبدأ الزيارة بعد — اضغط «بدء الزيارة» أولاً.');
                    });
                });
            });
        }

        wsApplyLock();

        // ===== مودال إضافة الملاحظة: GPS (يبقى شرطاً — داخل زيارةٍ فعلية) +
        // رفعُ ملفاتٍ متعددة بضغطٍ قابل لإعادة التطبيق، بنفس منطق
        // appointments/note/add_note.blade.php بدل ملفٍ واحدٍ بضغطٍ ثابت. =====
        var wsNoteModalEl = document.getElementById('noteModal');
        var wsNoteGpsAlert = document.getElementById('noteGpsAlert');
        var wsNoteLatInput = document.getElementById('noteLat');
        var wsNoteLngInput = document.getElementById('noteLng');
        var wsNoteSubmitBtn = document.getElementById('noteSubmitBtn');
        var wsNoteGpsOk = false;
        var wsNoteRawFiles = [];
        var wsNoteProcessedFiles = [];
        var wsNoteTotalOriginalSize = 0;
        var wsNoteTotalCompressedSize = 0;

        // بلا صورةٍ مطلوبة لا معنى لقفل الحفظ عليها — كان الزرّ يبقى معطَّلاً
        // إلى الأبد متى أُطفئ حقل المرفقات
        var WS_NOTE_PHOTO_REQUIRED = @json($showNotePhoto);

        // العدد المطلوب من الإعدادات لا `> 0` مثبَّتاً — وإلّا فُتح الحفظ
        // بصورةٍ واحدة ثمّ رفضه الخادم بحجّة النقص
        var WS_NOTE_MIN_PHOTOS = @json($noteMinPhotos);
        // ضغطُ الصور قبل الرفع — من إعدادات وحدات العملاء ← حدود المرفقات
        var WS_UPLOAD_QUALITY = @json($uploadImageQuality);
        var WS_UPLOAD_MAX_PX = @json($uploadImageMaxPx);

        function wsNoteUpdateSubmitState() {
            var photosOk = !WS_NOTE_PHOTO_REQUIRED || wsNoteProcessedFiles.length >= WS_NOTE_MIN_PHOTOS;
            wsNoteSubmitBtn.disabled = !(wsNoteGpsOk && photosOk);
        }

        function wsNoteFetchGps() {
            wsNoteGpsOk = false;
            wsNoteUpdateSubmitState();
            if (!('geolocation' in navigator)) {
                wsNoteGpsAlert.className = 'alert alert-danger py-2 mb-3 small';
                wsNoteGpsAlert.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i> المتصفح لا يدعم تحديد الموقع.';
                if (WS_ANY_LOCATION) { wsNoteGpsOk = true; wsNoteUpdateSubmitState(); }
                return;
            }
            wsNoteGpsAlert.className = 'alert alert-warning py-2 mb-3 small';
            wsNoteGpsAlert.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> جاري تحديد موقعك...';
            navigator.geolocation.getCurrentPosition(function (pos) {
                wsNoteLatInput.value = pos.coords.latitude;
                wsNoteLngInput.value = pos.coords.longitude;
                wsNoteGpsOk = true;
                wsNoteGpsAlert.className = 'alert alert-success py-2 mb-3 small';
                wsNoteGpsAlert.innerHTML = '<i class="fa fa-check-circle me-1"></i> تم تحديد موقعك. تأكد أنك ضمن نطاق موقع العميل.';
                wsNoteUpdateSubmitState();
            }, function () {
                wsNoteGpsAlert.className = 'alert alert-danger py-2 mb-3 small';
                wsNoteGpsAlert.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i> تعذّر تحديد موقعك — فعّل GPS وأذن الوصول ثم أعد فتح المودال.';
                if (WS_ANY_LOCATION) {
                    wsNoteGpsAlert.className = 'alert alert-info py-2 mb-3 small';
                    wsNoteGpsAlert.innerHTML = '<i class="fa fa-info-circle me-1"></i> تعذّر تحديد موقعك، ولديك صلاحية العمل من أي موقع — يمكنك الحفظ.';
                    wsNoteGpsOk = true;
                    wsNoteUpdateSubmitState();
                }
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        }

        if (wsNoteModalEl) {
            wsNoteModalEl.addEventListener('shown.bs.modal', wsNoteFetchGps);
            wsNoteModalEl.addEventListener('shown.bs.modal', wsNoteSyncShift);
            wsNoteModalEl.addEventListener('hidden.bs.modal', function () {
                document.getElementById('noteForm').reset();
                wsNoteSyncShift();
                document.getElementById('wsNoteSelectedFiles').innerHTML = '';
                document.getElementById('wsNoteCompressionSummary').classList.add('d-none');
                wsNoteRawFiles = [];
                wsNoteProcessedFiles = [];
                wsNoteTotalOriginalSize = 0;
                wsNoteTotalCompressedSize = 0;
                wsNoteGpsOk = false;
                wsNoteUpdateSubmitState();
            });
        }

        async function wsNoteHandleFileSelection(event, isRecompression) {
            var files = Array.from((event.target.files) || []);
            if (files.length === 0) return;

            wsNoteShowProgress(true);

            if (isRecompression) {
                wsNoteProcessedFiles = [];
                wsNoteTotalOriginalSize = 0;
                wsNoteTotalCompressedSize = 0;
            } else {
                wsNoteRawFiles.push.apply(wsNoteRawFiles, files);
            }

            var filesToProcess = isRecompression ? wsNoteRawFiles : files;

            for (var i = 0; i < filesToProcess.length; i++) {
                var file = filesToProcess[i];
                wsNoteUpdateProgress((i / filesToProcess.length) * 100, 'معالجة ' + file.name + '...');
                var processed = await wsNoteProcessFile(file);
                if (processed) wsNoteProcessedFiles.push(processed);
            }

            wsNoteUpdateProgress(100, 'تم الانتهاء من معالجة جميع الملفات');
            setTimeout(function () {
                wsNoteShowProgress(false);
                wsNotePreviewFiles();
                wsNoteUpdateSummary();
                wsNoteUpdateSubmitState();
            }, 500);
        }

        function wsNoteProcessFile(file) {
            wsNoteTotalOriginalSize += file.size;
            if (!file.type.startsWith('image/')) {
                alert('يُسمح فقط بالصور من الكاميرا');
                return Promise.resolve(null);
            }
            return wsNoteCompressImage(file);
        }

        function wsNoteCompressImage(file) {
            return new Promise(function (resolve) {
                var canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');
                var img = new Image();
                img.onload = function () {
                    /* القوائم تعيش داخل قسم المرفقات في مودال الملاحظة، وذاك
                       القسم يختفي متى أُطفئ مفتاح «حقل الصورة». وهذه الدالّة
                       يستعملها المودال المختصر أيضاً — فبلا هذا الاحتياط كان
                       ضغطُ صورته يسقط بخطأ JS على عنصرٍ غير موجود. القيم
                       الافتراضية من إعدادات وحدات العملاء ← حدود المرفقات. */
                    var qualityEl = document.getElementById('wsNoteImageQuality');
                    var maxSizeEl = document.getElementById('wsNoteMaxImageSize');
                    var quality = qualityEl ? parseFloat(qualityEl.value) : WS_UPLOAD_QUALITY;
                    var maxSize = maxSizeEl ? parseInt(maxSizeEl.value) : WS_UPLOAD_MAX_PX;
                    var width = img.width, height = img.height;
                    var ratio = Math.min(maxSize / width, maxSize / height);
                    if (ratio < 1) { width *= ratio; height *= ratio; }
                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob(function (blob) {
                        wsNoteTotalCompressedSize += blob.size;
                        var previewUrl = URL.createObjectURL(blob);
                        var compressedFile = new File([blob], file.name, { type: file.type, lastModified: Date.now() });
                        resolve({ file: compressedFile, originalSize: file.size, compressedSize: blob.size, preview: previewUrl });
                    }, file.type, quality);
                };
                img.src = URL.createObjectURL(file);
            });
        }

        function wsNotePreviewFiles() {
            var preview = document.getElementById('wsNoteSelectedFiles');
            preview.innerHTML = '';
            wsNoteProcessedFiles.forEach(function (item, index) {
                var savings = item.originalSize > 0 ? ((item.originalSize - item.compressedSize) / item.originalSize * 100).toFixed(1) : 0;
                var div = document.createElement('div');
                div.className = 'd-flex align-items-center gap-2 border rounded p-2 mb-1 bg-light';
                div.innerHTML = '<img src="' + item.preview + '" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">'
                    + '<div class="flex-grow-1 small">'
                    + '<span class="badge bg-primary">' + wsNoteFormatFileSize(item.compressedSize) + '</span> '
                    + (savings > 0 ? '<span class="badge bg-success">-' + savings + '%</span>' : '')
                    + '</div>'
                    + '<button type="button" class="btn btn-sm btn-outline-danger" onclick="wsNoteRemoveFile(' + index + ')"><i class="fas fa-times"></i></button>';
                preview.appendChild(div);
            });
            wsNoteUpdateFileInput();
        }

        function wsNoteUpdateFileInput() {
            var dt = new DataTransfer();
            wsNoteProcessedFiles.forEach(function (item) { dt.items.add(item.file); });
            document.getElementById('wsNoteAttachments').files = dt.files;
        }

        function wsNoteUpdateSummary() {
            var summary = document.getElementById('wsNoteCompressionSummary');
            if (wsNoteProcessedFiles.length === 0) { summary.classList.add('d-none'); return; }
            var savings = wsNoteTotalOriginalSize > 0
                ? ((wsNoteTotalOriginalSize - wsNoteTotalCompressedSize) / wsNoteTotalOriginalSize * 100).toFixed(1)
                : 0;
            document.getElementById('wsNoteOriginalSize').textContent = wsNoteFormatFileSize(wsNoteTotalOriginalSize);
            document.getElementById('wsNoteCompressedSize').textContent = wsNoteFormatFileSize(wsNoteTotalCompressedSize);
            document.getElementById('wsNoteSavingsPercentage').textContent = savings + '%';
            summary.classList.remove('d-none');
        }

        function wsNoteShowProgress(show) {
            var progress = document.getElementById('wsNoteCompressionProgress');
            if (show) progress.classList.remove('d-none'); else progress.classList.add('d-none');
        }

        function wsNoteUpdateProgress(percentage, message) {
            var bar = document.querySelector('#wsNoteCompressionProgress .progress-bar');
            var text = document.querySelector('#wsNoteCompressionProgress small');
            bar.style.width = percentage + '%';
            text.textContent = message;
        }

        function wsNoteFormatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function wsNoteRemoveFile(index) {
            var item = wsNoteProcessedFiles[index];
            wsNoteTotalOriginalSize -= item.originalSize;
            wsNoteTotalCompressedSize -= item.compressedSize;
            wsNoteProcessedFiles.splice(index, 1);
            wsNoteRawFiles.splice(index, 1);
            wsNotePreviewFiles();
            wsNoteUpdateSummary();
            wsNoteUpdateSubmitState();
        }

        /* دوام المسؤول في نافذة الملاحظة: يُملأ من المسؤول المختار. بلا دوامٍ مسجَّل
           يُختار هنا ويُحفظ مع الملاحظة؛ ودوامٌ مسجَّل يُعدَّل لمن يملك صلاحية تغييره
           ويبقى مقروءاً لغيره — والحكم نفسه في `VisitWorkspaceController::storeNote()`. */
        var WS_CAN_CHANGE_SHIFT = @json($canChangeShift);
        function wsNoteSyncShift() {
            var select = document.getElementById('wsNoteContactSelect');
            var shift = document.querySelector('#wsNoteShiftCol [data-shift-select]');
            var hint = document.getElementById('wsNoteShiftHint');
            if (!select || !shift) return;

            var option = select.options[select.selectedIndex];
            var chosen = !!option && option.value !== '';
            var hasShift = chosen && option.dataset.hasShift === '1';
            var locked = hasShift && !WS_CAN_CHANGE_SHIFT;

            shift.disabled = !chosen || locked;
            shift.required = chosen && !locked;
            var value = hasShift ? (option.dataset.shiftRange || '') : '';
            if (window.jQuery && window.jQuery.fn.select2 && shift.classList.contains('select2-hidden-accessible')) {
                window.jQuery(shift).val(value).trigger('change');
            } else {
                shift.value = value;
            }

            if (hint) {
                hint.className = 'cl-help d-block fw-bold ' + (chosen && !hasShift ? 'text-danger' : 'text-muted');
                hint.textContent = !chosen ? 'اختر المسؤول أولاً.'
                    : (!hasShift ? 'لا دوام مسجَّل لهذا المسؤول — اختره هنا ويُحفظ مع الملاحظة.'
                    : (locked ? 'دوامه مسجَّل — تعديله يحتاج صلاحية «تغيير دوام مسؤول العميل».'
                    : 'دوامه مسجَّل — عدّله هنا إن تغيّر، ويُحفظ مع الملاحظة.'));
            }
        }

        // إعادة الضغط تلقائياً عند تغيير الجودة/الحجم — نفس add_note.blade.php
        document.getElementById('wsNoteImageQuality')?.addEventListener('change', function () {
            if (wsNoteRawFiles.length > 0) wsNoteHandleFileSelection({ target: { files: wsNoteRawFiles } }, true);
        });
        document.getElementById('wsNoteMaxImageSize')?.addEventListener('change', function () {
            if (wsNoteRawFiles.length > 0) wsNoteHandleFileSelection({ target: { files: wsNoteRawFiles } }, true);
        });

        // ===== رصد المنافسين داخل الملاحظة =====
        // الصفوف تُبنى في المتصفّح: عددها غير معروفٍ مسبقاً، والقائمة نفسها
        // تتكرّر في كل صفّ — طبعُها في HTML لكل صفٍّ محتمل عبثٌ بلا حدّ.
        var WS_COMPETITORS = @json($competitorOptions);
        // إلزاميٌّ ما لم يُرصَد في هذه الزيارة — بعدها يُضاف منافسٌ اختيارياً
        var WS_COMPETITORS_REQUIRED = @json($competitorRequired);
        var wsCompetitorRowSeq = 0;

        function wsCompetitorOptionsHtml() {
            var html = '<option value="">اختر المنافس</option>';
            WS_COMPETITORS.forEach(function (c) {
                html += '<option value="' + c.id + '">' + c.name + '</option>';
            });
            return html;
        }

        var wsCompetitorModalEl = document.getElementById('addCompetitorModal');
        if (wsCompetitorModalEl) {
            wsCompetitorModalEl.addEventListener('shown.bs.modal', function () {
                document.getElementById('wsNewCompetitorMsg').textContent = '';
                document.getElementById('wsNewCompetitorName').focus();
            });

            /* Bootstrap ينزع `modal-open` عن `<body>` عند إغلاق أيّ مودال،
               فيفقد مودال الملاحظة الباقي خلفه تمريرَه. نعيده متى بقي مودالٌ
               مفتوح. */
            wsCompetitorModalEl.addEventListener('hidden.bs.modal', function () {
                document.getElementById('wsNewCompetitorName').value = '';
                if (document.querySelector('.modal.show')) {
                    document.body.classList.add('modal-open');
                }
            });
        }

        function wsSaveNewCompetitor() {
            var input = document.getElementById('wsNewCompetitorName');
            var msg = document.getElementById('wsNewCompetitorMsg');
            var name = (input.value || '').trim();

            if (!name) { msg.className = 'small mt-2 text-danger'; msg.textContent = 'اكتب اسم المنافس.'; return; }

            msg.className = 'small mt-2 text-muted';
            msg.textContent = 'جاري الحفظ...';

            fetch("{{ route('clients.visits.storeQuickCompetitor', $visit->id) }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ name: name }),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.success) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = data.message || 'تعذّر إضافة المنافس.';
                    return;
                }

                // القائمة المشتركة أوّلاً، ثمّ كل القوائم المعروضة — وإلا ظهر
                // الجديد في صفٍّ ولم يظهر في الصفوف التالية
                if (!WS_COMPETITORS.some(function (c) { return String(c.id) === String(data.competitor.id); })) {
                    WS_COMPETITORS.push(data.competitor);
                    document.querySelectorAll('#wsCompetitorRows select').forEach(function (select) {
                        var option = document.createElement('option');
                        option.value = data.competitor.id;
                        option.textContent = data.competitor.name;
                        select.appendChild(option);
                    });
                }

                // يُختار في أوّل سطرٍ فارغ، وإن لم يوجد فُتح سطرٌ جديد له
                var target = Array.prototype.slice.call(document.querySelectorAll('#wsCompetitorRows select'))
                    .filter(function (sel) { return !sel.value; })[0];
                if (!target) {
                    wsAddCompetitorRow();
                    var all = document.querySelectorAll('#wsCompetitorRows select');
                    target = all[all.length - 1];
                }
                if (target) target.value = data.competitor.id;
                wsSyncCompetitorChoices();
                document.getElementById('wsCompetitorEmptyHint')?.remove();

                msg.className = 'small mt-2 text-success';
                msg.textContent = data.message;
                input.value = '';
                setTimeout(function () {
                    bootstrap.Modal.getInstance(document.getElementById('addCompetitorModal'))?.hide();
                }, 900);
            }).catch(function () {
                msg.className = 'small mt-2 text-danger';
                msg.textContent = 'حدث خطأ أثناء إضافة المنافس.';
            });
        }

        // Enter داخل حقل الاسم يحفظ مباشرةً
        document.getElementById('wsNewCompetitorName')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); wsSaveNewCompetitor(); }
        });

        function wsAddCompetitorRow() {
            var host = document.getElementById('wsCompetitorRows');
            if (!host) return;
            if (host.children.length >= WS_COMPETITORS.length) {
                alert('لا يوجد منافسون آخرون في القائمة.');
                return;
            }

            var index = wsCompetitorRowSeq++;
            var row = document.createElement('div');
            row.className = 'ws-competitor-row';
            // منافس + عهدته + مرفقه (كاميرا) + حذف السطر — أعمدة `.ws-competitor-row` في CSS أعلى الصفحة.
            // حقل الملف يسبق زرّ الكاميرا مباشرةً: `previousElementSibling`/`nextElementSibling` يربطانهما.
            row.innerHTML =
                '<select name="competitors[' + index + '][competitor_id]" class="form-select form-select-sm" required onchange="wsSyncCompetitorChoices()">'
                + wsCompetitorOptionsHtml() + '</select>'
                + '<input type="number" name="competitors[' + index + '][asset_count]" class="form-control form-control-sm" '
                + 'min="0" step="1" placeholder="0" required>'
                + '<span class="ws-comp-photo">'
                + '<input type="file" id="wsCompPhoto' + index + '" name="competitors[' + index + '][photo]" class="ws-hidden-file-input" accept="image/*" capture="environment" onchange="wsCompetitorPhotoPicked(this)">'
                + '<label for="wsCompPhoto' + index + '" role="button" class="btn btn-sm btn-outline-primary ws-comp-photo-btn mb-0" title="صورة المنافس">'
                + '<i class="fas fa-camera"></i></label></span>'
                + '<button type="button" class="btn btn-sm ws-comp-remove" title="حذف السطر" onclick="wsRemoveCompetitorRow(this)"><i class="fas fa-trash-alt"></i></button>';

            host.appendChild(row);
            wsSyncCompetitorChoices();

            // سطرٌ جديد يعني أنّ هناك منافساً — فلا يبقى «لا يوجد منافس» مؤشَّراً
            var noneBox = document.getElementById('wsNoCompetitors');
            if (noneBox && noneBox.checked) {
                noneBox.checked = false;
                wsToggleNoCompetitors();
            }
        }

        /* ===== مودال خطوة «رصد المنافسين» =====
           أوّل سطرٍ يُفتح مع النافذة (إلا مع كتالوجٍ فارغ — كان سيُطلق تنبيه
           «لا يوجد منافسون آخرون» فور فتحها؛ أوّل سطرٍ يأتي حينها مع أوّل
           منافسٍ يُضاف). «لا يوجد منافس» يُعطّل الصفوف ويُسقط إلزامها. */
        function wsToggleNoCompetitors() {
            var none = !!document.getElementById('wsNoCompetitors')?.checked;
            var photoBlock = document.getElementById('wsNoCompetitorsPhotoBlock');
            var photoInput = document.getElementById('wsNoCompetitorsPhoto');
            
            document.querySelectorAll('#wsCompetitorRows select, #wsCompetitorRows input').forEach(function (el) {
                el.disabled = none;
                el.required = !none;
            });
            
            // إظهار/إخفاء حقل صورة «لا يوجد منافس»
            if (photoBlock) {
                if (none) {
                    photoBlock.style.display = 'block';
                    if (photoInput) photoInput.required = true;
                } else {
                    photoBlock.style.display = 'none';
                    if (photoInput) photoInput.required = false;
                }
            }
            
            // معاينة الصورة
            if (photoInput) {
                photoInput.addEventListener('change', function(e) {
                    var preview = document.getElementById('wsNoCompetitorsPhotoPreview');
                    if (preview && this.files.length > 0) {
                        var reader = new FileReader();
                        reader.onload = function(event) {
                            preview.innerHTML = '<img src="' + event.target.result + '" style="max-width: 150px; max-height: 150px; border-radius: 6px; border: 1px solid var(--ws-border);">';
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        }

        /* نافذة «رصد المنافسين» المستقلّة: أوّل سطرٍ يُفتح معها (إلا مع كتالوجٍ
           فارغ — كان سيُطلق تنبيه «لا يوجد منافسون آخرون» فور فتحها)، والإرسال
           يُمنع بلا صفٍّ مختار ولا «لا يوجد منافس»، أو بمنافسٍ بلا صورة — برسالةٍ
           داخل النافذة لا بنافذة تنبيه. */
        var wsCompetitorStepModalEl = document.getElementById('competitorStepModal');
        if (wsCompetitorStepModalEl) {
            wsCompetitorStepModalEl.addEventListener('shown.bs.modal', function () {
                var host = document.getElementById('wsCompetitorRows');
                if (host && host.children.length === 0 && WS_COMPETITORS.length > 0) wsAddCompetitorRow();
            });

            wsCompetitorStepModalEl.addEventListener('hidden.bs.modal', function () {
                /* «منافس جديد» يُفتح فوق هذه النافذة؛ لو أخفاها المتصفّح معه
                   (رصُّ مودالات، ESC) لا نمحو ما اختاره المندوب من صفوف. */
                if (document.getElementById('addCompetitorModal')?.classList.contains('show')) return;

                document.getElementById('competitorStepForm').reset();
                var host = document.getElementById('wsCompetitorRows');
                if (host) host.innerHTML = '';
                wsToggleNoCompetitors();
                var msg = document.getElementById('wsCompetitorRequiredMsg');
                if (msg) msg.textContent = '';
            });

            /* صورة المنافس إلزامية لكل صفٍّ اختير فيه منافس — والخادم يرفضها أيضاً
               (`competitorValidationError()`)، لكنّ رفضه يعيد الصفحة فيضيع ما كُتب
               والتُقط، فيُمنع الإرسال هنا أوّلاً. حقل الملف مخفيّ فلا يصلح له `required`. */
            document.getElementById('competitorStepForm')?.addEventListener('submit', function (e) {
                if (document.getElementById('wsNoCompetitors')?.checked) return;
                var missing = Array.prototype.filter.call(document.querySelectorAll('#wsCompetitorRows .ws-competitor-row'), function (row) {
                    var select = row.querySelector('select');
                    var file = row.querySelector('input[type="file"]');
                    return select && select.value && !(file && file.files && file.files.length);
                });
                if (missing.length === 0) return;

                e.preventDefault();
                e.stopImmediatePropagation();
                missing.forEach(function (row) {
                    var btn = row.querySelector('.ws-comp-photo-btn');
                    if (btn) btn.classList.replace('btn-outline-primary', 'btn-outline-danger');
                });
                var msg = document.getElementById('wsCompetitorRequiredMsg');
                if (msg) msg.textContent = 'صورة المنافس إلزامية — التقطها من زرّ الكاميرا في سطر كل منافس.';
                document.getElementById('wsNoteCompetitorSection')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });

            document.getElementById('competitorStepForm')?.addEventListener('submit', function (e) {
                var none = !!document.getElementById('wsNoCompetitors')?.checked;
                var chosen = Array.prototype.some.call(document.querySelectorAll('#wsCompetitorRows select'), function (s) { return !!s.value; });
                if (none || chosen) return;

                e.preventDefault();
                var msg = document.getElementById('wsCompetitorRequiredMsg');
                if (msg) msg.textContent = 'اختر منافساً واحداً على الأقل وعدد قطع عهدته — أو أشِّر «لا يوجد منافس عند هذا العميل».';
                document.getElementById('wsNoteCompetitorSection')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }

        /* مرفق المنافس: يُضغط بضاغط صور الملاحظة نفسه (إعدادات حدود المرفقات)
           ويُعاد إلى حقل السطر، وتظهر الصورة المصغّرة مكان أيقونة الكاميرا.
           عدّاد أحجام مودال الملاحظة يُحفظ ويُعاد — الضاغط يزيده جانبيّاً. */
        async function wsCompetitorPhotoPicked(input) {
            var file = input.files && input.files[0];
            var btn = input.nextElementSibling;
            if (!file) return;
            if (!file.type.startsWith('image/')) { input.value = ''; return; }

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            var sizeBefore = wsNoteTotalCompressedSize;
            var processed = await wsNoteCompressImage(file);
            wsNoteTotalCompressedSize = sizeBefore;

            var dt = new DataTransfer();
            dt.items.add(processed.file);
            input.files = dt.files;

            // الزرّ قد يكون أحمر من محاولة حفظٍ بلا صورة
            btn.classList.remove('btn-outline-primary', 'btn-outline-danger');
            btn.classList.add('btn-outline-success');
            if (!document.querySelector('#wsCompetitorRows .ws-comp-photo-btn.btn-outline-danger')) {
                var wsPhotoMsg = document.getElementById('wsCompetitorRequiredMsg');
                if (wsPhotoMsg) wsPhotoMsg.textContent = '';
            }
            btn.innerHTML = '<img src="' + processed.preview + '" alt="" style="width:100%;height:28px;object-fit:cover;border-radius:4px;">';
            btn.title = 'تغيير مرفق المنافس';
        }

        function wsRemoveCompetitorRow(button) {
            var host = document.getElementById('wsCompetitorRows');
            button.closest('.ws-competitor-row').remove();
            // آخر صفٍّ لا يُحذف: القسم إلزاميٌّ متى كان ظاهراً
            if (host && host.children.length === 0) wsAddCompetitorRow();
            wsSyncCompetitorChoices();
        }

        /* منافسٌ اختير في صفٍّ يُعطَّل في بقيّة الصفوف: تكراره يُنتج رصدين
           لنفس المنافس في ملاحظةٍ واحدة، وهو ما يفسد أي جمعٍ لاحق. */
        function wsSyncCompetitorChoices() {
            var selects = Array.prototype.slice.call(document.querySelectorAll('#wsCompetitorRows select'));
            var chosen = selects.map(function (s) { return s.value; }).filter(Boolean);

            selects.forEach(function (select) {
                Array.prototype.slice.call(select.options).forEach(function (option) {
                    if (!option.value) return;
                    option.disabled = option.value !== select.value && chosen.indexOf(option.value) !== -1;
                });
            });
        }

        function prepNoteModal(focusPhoto) {
            setTimeout(function () {
                // «التقاط صورة» بلا حقل مرفقات: نفتح الملاحظة على نصّها
                if (focusPhoto && document.getElementById('wsNoteAttachments')) {
                    document.getElementById('wsNoteAttachments').click();
                } else {
                    document.getElementById('noteBodyInput').focus();
                }
            }, 500);
        }

        // ===== المؤقّت =====
        // $elapsedSeconds محسوبةٌ في VisitWorkspaceController::show() فور
        // جلب الزيارة، لا $visit->elapsed_seconds هنا مباشرة — انظر تعليق
        // الحساب هناك: مُركِّب اللغة قد يبدّل المنطقة الزمنية الافتراضية
        // أثناء تصيير هذه الصفحة بالذات، فتُعاد صياغة started_at/paused_at
        // بمنطقةٍ مختلفة لو استُدعيت الخاصّية من هنا بتأخّر
        let wsElapsed = {{ (int) $elapsedSeconds }};
        let wsPaused = {{ $visit->isPaused() ? 'true' : 'false' }};
        let wsCompleted = {{ $visit->isCompleted() ? 'true' : 'false' }};
        let wsStarted = {{ $visit->started_at ? 'true' : 'false' }};

        function wsFormatHMS(total) {
            total = Math.max(0, total);
            const h = String(Math.floor(total / 3600)).padStart(2, '0');
            const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
            const s = String(total % 60).padStart(2, '0');
            return `${h}:${m}:${s}`;
        }
        function wsRenderTimer() {
            const el = document.getElementById('visitTimer');
            if (el) el.textContent = wsFormatHMS(wsElapsed);
        }
        wsRenderTimer();
        setInterval(function () {
            if (wsStarted && !wsPaused && !wsCompleted) {
                wsElapsed++;
                wsRenderTimer();
            }
        }, 1000);

        /* زرّا «إيقاف مؤقت» و«استئناف» حُذفا من الشاشة، فلم يبقَ إيقافٌ إلا
           التلقائي حين يخرج المندوب من نطاق موقع العميل. وبسقوطهما سقط معهما
           علمُ `localStorage` الذي كان يميّز الإيقاف اليدويّ من إيقاف الموقع
           عند إعادة تحميل الصفحة: زيارةٌ متوقّفةٌ اليوم لا تحتمل إلا سبباً
           واحداً، فالحالة في قاعدة البيانات كافيةٌ وحدها. */
        let outsideGeofence = wsPaused; // هل الموظف خارج نطاق موقع العميل الآن؟
        if (outsideGeofence) {
            document.getElementById('geofenceBadge')?.classList.remove('d-none');
        }

        function wsTogglePauseOnServer() {
            return fetch("{{ route('clients.visits.togglePause', $visit->id) }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            }).then(r => r.json()).then(data => {
                if (!data.success) return data;
                wsPaused = data.paused;
                wsElapsed = data.elapsed_seconds;
                wsRenderTimer();
                return data;
            });
        }

        // ===== إيقاف/استئناف المؤقّت تلقائياً حسب موقع الموظف من العميل =====
        const wsProximityMeters = {{ (float) $proximityMeters }};
        /* قراءة GPS واحدةً غير دقيقة (شائعة في أوّل ثوانٍ بعد `getCurrentPosition`/
           `watchPosition`، تحديداً فور الضغط على «بدء الزيارة») كانت تكفي وحدها
           لتفعيل الإيقاف التلقائي فوراً — يظهر للموظف زرّ «استئناف» ولوحة
           «خارج نطاق موقع العميل» وهو واقفٌ عند العميل فعلياً. نطرح هامش خطأ
           الجهاز (`accuracy`، المعروضة أصلاً في «دقة الموقع») من المسافة قبل
           مقارنتها بالنطاق المسموح — لا نعتبره خارج النطاق إلا إذا بقي كذلك
           حتى في أفضل تفسيرٍ ممكنٍ للقراءة. */
        function wsHandleGeofence(distanceMeters, accuracyMeters) {
            // لا إيقافَ تلقائيّاً لمن يعمل من أي موقع — الابتعاد عنده ليس خروجاً
            if (WS_ANY_LOCATION) return;
            const badge = document.getElementById('geofenceBadge');
            const effectiveDistance = Math.max(0, distanceMeters - (accuracyMeters || 0));
            const nowOutside = effectiveDistance > wsProximityMeters;

            if (nowOutside && !outsideGeofence) {
                outsideGeofence = true;
                if (badge) badge.classList.remove('d-none');
                if (!wsPaused) wsTogglePauseOnServer();
            } else if (!nowOutside && outsideGeofence) {
                outsideGeofence = false;
                if (badge) badge.classList.add('d-none');
                if (wsPaused) wsTogglePauseOnServer();
            }
        }

        // ===== قبل بدء الزيارة: تفعيل/تعطيل زرّ «بدء الزيارة» حسب القرب
        // الفعلي من موقع العميل — نفس تتبّع الموقع الحيّ المستعمَل لاحقاً
        // لقفل المؤقّت تلقائياً، قبل أن تبدأ الزيارة أصلاً. =====
        function wsUpdateStartButton(distanceMeters) {
            const btn = document.getElementById('startVisitBtn');
            const status = document.getElementById('startLocationStatus');
            if (!btn || !status) return;

            const within = distanceMeters <= wsProximityMeters;
            btn.disabled = !within && !WS_ANY_LOCATION;

            if (!within && WS_ANY_LOCATION) {
                status.className = 'small text-info fw-bold mt-1';
                status.innerHTML = '<i class="fas fa-info-circle"></i> أنت خارج نطاق موقع العميل ('
                    + Math.round(distanceMeters) + ' متر) — ولديك صلاحية البدء من أي موقع.';
                return;
            }

            if (within) {
                status.className = 'small text-success fw-bold mt-1';
                status.innerHTML = '<i class="fas fa-check-circle"></i> أنت ضمن نطاق موقع العميل — يمكنك بدء الزيارة.';
            } else {
                status.className = 'small text-danger fw-bold mt-1';
                status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> أنت خارج نطاق موقع العميل ('
                    + Math.round(distanceMeters) + ' متر) — يجب أن تكون ضمن ' + Math.round(wsProximityMeters) + ' متر لبدء الزيارة.';
            }
        }

        /**
         * فشلٌ في تحديد الموقع نفسه (لا مجرّد بُعدٍ عن العميل) — يُظهر السبب
         * ويُبقي زرّ «بدء الزيارة» معطَّلاً، مع رابط إعادة تحميل بدل ترك
         * «جاري تحديد موقعك...» معلَّقةً للأبد بلا أي تفسير.
         */
        function wsUpdateStartError(message) {
            const btn = document.getElementById('startVisitBtn');
            const status = document.getElementById('startLocationStatus');
            if (!status) return;

            if (WS_ANY_LOCATION) {
                // تعذُّر الموقع أو غياب موقع العميل لا يحبس من يعمل من أي موقع
                if (btn) btn.disabled = false;
                status.className = 'small text-info fw-bold mt-1';
                status.innerHTML = '<i class="fas fa-info-circle"></i> ' + message
                    + ' — لديك صلاحية البدء من أي موقع.';
                return;
            }

            if (btn) btn.disabled = true;
            status.className = 'small text-danger fw-bold mt-1';
            status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message
                + ' <a href="javascript:void(0)" onclick="location.reload()">إعادة المحاولة</a>';
        }

        // ===== لوحتا التوقيع =====
        let clientSigPad, employeeSigPad;
        function wsResizeCanvas(canvas) {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
        }
        document.addEventListener('DOMContentLoaded', function () {
            const clientCanvas = document.getElementById('clientSigPad');
            const employeeCanvas = document.getElementById('employeeSigPad');
            if (clientCanvas && employeeCanvas && window.SignaturePad) {
                wsResizeCanvas(clientCanvas);
                wsResizeCanvas(employeeCanvas);
                clientSigPad = new SignaturePad(clientCanvas);
                employeeSigPad = new SignaturePad(employeeCanvas);
            }

            // ===== زرّ «بدء الزيارة» — يقرأ الموقع لحظة الضغط ويرسله
            // للخادم، الذي يتحقّق فعلياً من القرب قبل تسجيل البدء (لا يُعتمد
            // على تعطيل الزر بالواجهة وحده). عند النجاح تُعاد الصفحة كي تظهر
            // كل عناصر «بعد البدء» (المؤقّت، الإيقاف المؤقت، الإنهاء) بحالتها الصحيحة. =====
            const startBtn = document.getElementById('startVisitBtn');
            if (startBtn) {
                startBtn.addEventListener('click', function () {
                    if (!navigator.geolocation && !WS_ANY_LOCATION) {
                        alert('متصفحك لا يدعم تحديد الموقع — لا يمكن بدء الزيارة بدونه.');
                        return;
                    }

                    startBtn.disabled = true;
                    startBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري بدء الزيارة...';

                    const wsSendStart = function (pos) {
                        fetch("{{ route('clients.visits.workspaceStart', $visit->id) }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(pos ? {
                                current_latitude: pos.coords.latitude,
                                current_longitude: pos.coords.longitude,
                            } : {}),
                        }).then(r => r.json()).then(data => {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                startBtn.disabled = false;
                                startBtn.innerHTML = '<i class="fas fa-play-circle me-1"></i> بدء الزيارة';
                                alert(data.message || 'تعذّر بدء الزيارة.');
                            }
                        }).catch(function () {
                            startBtn.disabled = false;
                            startBtn.innerHTML = '<i class="fas fa-play-circle me-1"></i> بدء الزيارة';
                            alert('حدث خطأ أثناء بدء الزيارة.');
                        });
                    };

                    if (!navigator.geolocation) { wsSendStart(null); return; }

                    navigator.geolocation.getCurrentPosition(wsSendStart, function () {
                        // من يعمل من أي موقع يبدأ ولو تعذّر موقعه — الخادم لا يطلبه منه
                        if (WS_ANY_LOCATION) { wsSendStart(null); return; }
                        startBtn.disabled = false;
                        startBtn.innerHTML = '<i class="fas fa-play-circle me-1"></i> بدء الزيارة';
                        alert('تعذّر تحديد موقعك — فعّل GPS وأذن الوصول ثم حاول مرة أخرى.');
                    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
                });
            }

            // بطاقة التوقيع قابلة للإخفاء من الإعدادات — إن أُخفيت لا لوحتَي
            // توقيعٍ في DOM أصلاً (clientSigPad/employeeSigPad تبقيان null)،
            // فلا يصحّ أن يُحجَب إتمام الزيارة بانتظار توقيعٍ لا مكان له
            const signatureSectionEnabled = @json($wsShow('show_signature_section'));

            const completeBtn = document.getElementById('completeVisitBtn');
            if (completeBtn) {
                completeBtn.addEventListener('click', function () {
                    // نفس التنبيه ونفس المصدر — الزرّ معطَّلٌ أصلاً حين ينقص
                    // شيء، وهذا احتياطٌ لمن وصله بطريقٍ آخر
                    if (wsWarnIfIncomplete()) return;

                    // «سأعود» بلا ساعةٍ لا تُجدوِل شيئاً — نفس ما يرفضه
                    // `complete()` بقاعدة `required_if`
                    var returnLater = document.getElementById('wsReturnLater');
                    var returnTime = document.getElementById('wsReturnTime');
                    if (returnLater && returnLater.checked && returnTime && !returnTime.value) {
                        wsShowRequired('يجب تحديد وقت العودة المتوقَّع لهذا العميل.', '#wsReturnTime');
                        return;
                    }
                    /* التوقيع شرطٌ كبقيّة الشروط — يُوجَّه إليه بالشريط
                       والإبراز لا بنافذةٍ سوداء تُغلق. */
                    if (signatureSectionEnabled && (!clientSigPad || clientSigPad.isEmpty() || !employeeSigPad || employeeSigPad.isEmpty())) {
                        var whichPad = (!clientSigPad || clientSigPad.isEmpty()) ? 'clientSigPad' : 'employeeSigPad';
                        wsShowRequired(
                            'يجب توقيع ' + (whichPad === 'clientSigPad' ? 'مسؤول العميل' : 'المندوب') + ' قبل إتمام الزيارة.',
                            '#' + whichPad
                        );
                        return;
                    }
                    // لا اختيارَ نتيجةٍ يُتحقَّق منه هنا: `complete()` يستنتجها من
                    // وجود فاتورةٍ أو سند قبضٍ لهذا العميل اليوم
                    if (!navigator.geolocation && !WS_ANY_LOCATION) {
                        alert('متصفحك لا يدعم تحديد الموقع — لا يمكن إنهاء الزيارة بدونه.');
                        return;
                    }

                    if (signatureSectionEnabled) {
                        document.getElementById('clientSignatureInput').value = clientSigPad.toDataURL('image/png');
                        document.getElementById('employeeSignatureInput').value = employeeSigPad.toDataURL('image/png');
                    }
                    completeBtn.disabled = true;
                    completeBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري تحديد موقعك...';
                    if (!navigator.geolocation) { document.getElementById('completeVisitForm').submit(); return; }

                    navigator.geolocation.getCurrentPosition(function (pos) {
                        document.getElementById('completeLat').value = pos.coords.latitude;
                        document.getElementById('completeLng').value = pos.coords.longitude;
                        document.getElementById('completeVisitForm').submit();
                    }, function () {
                        // صلاحية «من أي موقع»: يُنهي ولو تعذّر موقعه
                        if (WS_ANY_LOCATION) { document.getElementById('completeVisitForm').submit(); return; }
                        completeBtn.disabled = false;
                        completeBtn.innerHTML = '<i class="fas fa-sign-out-alt me-1"></i> <span id="completeVisitBtnLabel">إنهاء الزيارة</span>';
                        alert('تعذّر تحديد موقعك — يجب أن تكون عند موقع العميل لإنهاء الزيارة. فعّل GPS وأذن الوصول ثم حاول مرة أخرى.');
                    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
                });
            }

            // ===== خريطة الزيارة =====
            const mapEl = document.getElementById('visitMap');
            const clientLat = {{ $clientLatitude ?? 'null' }};
            const clientLng = {{ $clientLongitude ?? 'null' }};
            const empLat = {{ $visit->employee_latitude ?? 'null' }};
            const empLng = {{ $visit->employee_longitude ?? 'null' }};
            let map = null, empMarker = null;

            if (mapEl && window.google && window.google.maps) {
                const center = (clientLat && clientLng) ? { lat: clientLat, lng: clientLng } : { lat: 24.7136, lng: 46.6753 };
                map = new google.maps.Map(mapEl, { zoom: 16, center, disableDefaultUI: true, zoomControl: true });
                if (clientLat && clientLng) new google.maps.Marker({ position: { lat: clientLat, lng: clientLng }, map, title: 'موقع العميل' });
                if (empLat && empLng) {
                    empMarker = new google.maps.Marker({ position: { lat: empLat, lng: empLng }, map, icon: { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' } });
                }
            }

            // ===== تتبّع الموقع الحيّ: يحدّث الخريطة (إن توفّرت) ويشغّل/يوقف
            // المؤقّت تلقائياً حسب المسافة من العميل — مستقلّ عن نجاح تحميل
            // خرائط قوقل، فلا يتعطّل قفل المؤقّت لو تعطّلت الخريطة وحدها.
            //
            // ثلاثة أعطالٍ كانت تجمّد «جاري تحديد موقعك...» إلى الأبد بلا أي
            // رسالة: لا `navigator.geolocation` أصلاً، لا موقع مسجَّل للعميل
            // (`clientLat`/`clientLng` فارغان)، أو نجاح تسجيل الدخول للموقع
            // لكن `watchPosition` هنا وحده — من بين كل نداءات الموقع في هذا
            // الملفّ — كان يمرّر `null` بدل دالّة خطأ، فلا رفضٌ يظهر ولا
            // مهلة (`timeout`) تقطعه لو عُلّق طلب الإذن. =====
            if (!wsStarted && !clientLat) {
                wsUpdateStartError('لا يوجد موقعٌ مسجَّل لهذا العميل — يجب تحديث بيانات العميل بموقعٍ على الخريطة أولاً.');
            } else if (!navigator.geolocation || !navigator.geolocation.watchPosition) {
                if (!wsStarted) wsUpdateStartError('متصفّحك لا يدعم تحديد الموقع — لا يمكن بدء الزيارة بدونه.');
            } else if (clientLat && clientLng) {
                navigator.geolocation.watchPosition(function (pos) {
                    const here = { lat: pos.coords.latitude, lng: pos.coords.longitude };

                    if (map) {
                        if (!empMarker) empMarker = new google.maps.Marker({ position: here, map, icon: { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' } });
                        else empMarker.setPosition(here);
                    }

                    document.getElementById('locAccuracy').textContent = '± ' + Math.round(pos.coords.accuracy) + ' متر';
                    document.getElementById('locUpdatedAt').textContent = new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });

                    const R = 6371000;
                    const dLat = (clientLat - here.lat) * Math.PI / 180;
                    const dLng = (clientLng - here.lng) * Math.PI / 180;
                    const a = Math.sin(dLat / 2) ** 2 + Math.cos(here.lat * Math.PI / 180) * Math.cos(clientLat * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
                    const distanceMeters = R * 2 * Math.asin(Math.sqrt(a));
                    document.getElementById('locDistance').textContent = Math.round(distanceMeters) + ' متر';

                    if (!wsStarted) {
                        wsUpdateStartButton(distanceMeters);
                    } else if (!wsCompleted) {
                        wsHandleGeofence(distanceMeters, pos.coords.accuracy);
                    }
                }, function (err) {
                    if (wsStarted) return;
                    const msg = err && err.code === err.PERMISSION_DENIED
                        ? 'تعذّر تحديد موقعك — يجب السماح بالوصول إلى الموقع من إعدادات المتصفح ثم إعادة تحميل الصفحة.'
                        : 'تعذّر تحديد موقعك — تأكد من تفعيل GPS وإعادة المحاولة.';
                    wsUpdateStartError(msg);
                }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 10000 });
            }
        });
    </script>
@endsection
