{{--
    تخطيط نموذجَي «إضافة عميل» و«تعديل عميل» — مشتركٌ بين الشاشتين.

    • `.cl-form-bar`       شريطٌ رفيع: ملاحظة الإلزام وزرّا الإلغاء والحفظ.
    • `.cl-form-grid`     شبكة «البيانات الأساسية». الحقل غير الإلزامي يحمل
                          `cl-field--opt` فيتأخّر بـ`order`، والكتل الكبيرة
                          (الخريطة، الصورة) تحمل `cl-field--block` فتأتي آخراً —
                          فتبقى الحقول الإلزامية متجاورةً أيّاً كانت الإعدادات.
    • `#contactContainer` جهاتُ الاتصال في شبكةٍ متجاورة بدل عمودٍ طويل،
                          وجسمُ البطاقة يُطوى ما دامت الحاوية فارغة.

    الاستعمال — بعد `client::partials.settings-theme`:
        include client::partials.client-form-style
--}}
<style>
    .cl-scope .cl-form-bar .card-body {
        display: flex; align-items: center; justify-content: space-between;
        gap: .75rem; flex-wrap: wrap;
        padding: .7rem 1.25rem;
    }
    .cl-form-bar__note { color: var(--app-muted, #6e6b7b); font-size: .85rem; }
    .cl-form-bar__actions { display: flex; gap: .5rem; flex-wrap: wrap; }

    .cl-card-hint { color: var(--app-muted, #6e6b7b); font-size: .78rem; }

    #clientForm .card-body { padding: 1rem 1.25rem; }
    #clientForm .card-header { padding: .75rem 1.25rem; }
    #clientForm .form-group { margin-bottom: 0; }
    #clientForm .form-group > label,
    #clientForm .col-12 > label,
    #clientForm .cl-field--block > label { margin-bottom: .3rem; font-weight: 600; font-size: .85rem; }

    .cl-form-grid > .cl-field--opt { order: 1; }
    .cl-form-grid > .cl-field--block { order: 2; }
    /* الخريطة تأخذ عرض السطر كاملاً بعد «إظهار الخريطة» — في ثلث العرض تضيق عن تحديد الموقع */
    .cl-form-grid > .cl-field--block:has(> #map-container:not([style*="none"])) { flex: 0 0 100%; max-width: 100%; }

    #contactContainer {
        display: grid; gap: .75rem;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 420px), 1fr));
    }
    #contactContainer .contact-fields-group { margin: 0 !important; padding: .75rem !important; }
    #contactContainer .contact-fields-group hr { display: none; }
    #clientForm .cl-contacts-body:has(> #contactContainer:empty) { display: none; }
</style>
