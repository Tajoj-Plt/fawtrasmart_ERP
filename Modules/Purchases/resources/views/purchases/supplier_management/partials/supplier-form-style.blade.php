{{--
    تخطيط نموذجَي «إضافة مورد» و«تعديل مورد» — مشتركٌ بين الشاشتين.

    نظيرُ `client::partials.client-form-style` بالبادئة `sl-`، على طلب
    المستخدم أن تخرج شاشاتُ الموردين بمقاس شاشات العملاء. نسخةٌ مستقلّة لا
    إشارةٌ إلى وحدة العملاء — فوحدةٌ معطَّلة في `modules_statuses.json`
    تُسقط قوالبها، وقالبٌ هنا يشير إليها ينكسر بإطفائها.

    • `.sl-form-bar`   شريطٌ رفيع: ملاحظة الإلزام وزرّا الإلغاء والحفظ.
    • `.sl-card-hint`  سطرُ شرحٍ باهتٌ تحت عنوان البطاقة.
    • كثافةُ النموذج  حشوةُ البطاقات والحقول داخل نموذجَي المورد وحدهما،
                      فلا تتأثّر بقيّة الشاشات التي تشترك في نظام التصميم.

    **المُنتقي `form[id^="supplier"]`** لا معرّفٌ بعينه: شاشة الإضافة
    نموذجُها `supplierForm` وشاشة التعديل `supplierUpdateForm`، فمُنتقٍ
    واحد يكفيهما بلا تكرار كلّ قاعدةٍ مرّتين.

    التخطيط نفسُه لم يُمَسّ: العمودان، وعددُ البطاقات، وكلُّ `id` و`name`
    و`.contact-fields-group` تقرؤه سكربتاتُ الشاشة باقٍ كما هو — المطلوب
    كان الإطار لا إعادةَ البناء.

    الاستعمال — بعد `purchase_requests.partials.sales-theme`:
        @include('purchases::purchases.supplier_management.partials.supplier-form-style')
--}}
@once
    <style>
        .sl-scope .sl-form-bar .card-body {
            display: flex; align-items: center; justify-content: space-between;
            gap: .75rem; flex-wrap: wrap;
            padding: .7rem 1.25rem;
        }
        .sl-form-bar__note { color: var(--app-muted, #6e6b7b); font-size: .85rem; }
        .sl-form-bar__actions { display: flex; gap: .5rem; flex-wrap: wrap; }

        .sl-card-hint { color: var(--app-muted, #6e6b7b); font-size: .78rem; }

        /* ═══════════ كثافة النموذج ═══════════ */

        .sl-scope form[id^="supplier"] .card-body { padding: 1rem 1.25rem; }
        .sl-scope form[id^="supplier"] .card-header { padding: .75rem 1.25rem; }
        .sl-scope form[id^="supplier"] .form-group { margin-bottom: 0; }
        .sl-scope form[id^="supplier"] .form-group > label,
        .sl-scope form[id^="supplier"] .col-12 > label {
            margin-bottom: .3rem; font-weight: 600; font-size: .85rem;
        }

        /* أيقونةُ الحقل داخل الإطار: `has-icon-left` من القالب الأصلي تضع
           الأيقونة فوق النصّ، وحشوةُ نظام التصميم أوسع من حشوته — فتتراكب
           الأيقونةُ والكتابة ما لم تُزَح الحشوةُ معها. */
        .sl-scope form[id^="supplier"] .has-icon-left .form-control { padding-inline-start: 2.4rem; }
        .sl-scope form[id^="supplier"] .has-icon-left .form-control-position {
            inset-inline-start: 0; inset-block-start: 0;
            width: 2.4rem; height: 100%;
            display: flex; align-items: center; justify-content: center;
            color: var(--app-muted, #6e6b7b); pointer-events: none;
        }

        /* جهاتُ الاتصال في شبكةٍ متجاورة بدل عمودٍ طويل */
        #contactContainer {
            display: grid; gap: .75rem;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 420px), 1fr));
        }
        #contactContainer .contact-fields-group { margin: 0 !important; padding: .75rem !important; }
        #contactContainer .contact-fields-group hr { display: none; }

        /* ═══════════ ألوانٌ كانت مثبَّتةً في القالب ═══════════

           `#007bff` و`#f8f9fa` و`#e3e6ef` كانت مكتوبةً نصّاً، فتبقى الشاشة
           زرقاء في الوضع الليلي وتُخالف لونَ النظام الأساسي أيّاً كان. */

        .sl-scope form[id^="supplier"] .upload-area:hover {
            background-color: var(--app-body-bg, #f8f9fa);
            border-color: var(--app-primary, #7367f0) !important;
        }
        .sl-scope form[id^="supplier"] .contact-fields-group { border: 1px solid var(--sl-line) !important; }
        .sl-scope form[id^="supplier"] .contact-fields-group:hover { box-shadow: var(--sl-e2); }
        .sl-scope form[id^="supplier"] .form-control:focus {
            border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--app-primary, #7367f0) 22%, transparent);
        }
    </style>
@endonce
