{{--
    ترويسة المنشأة في الإيصال الحراري — كلُّ سطرٍ فيها من شاشة «معلومات الحساب»
    (`App\Support\BusinessInfo` ← `DocumentTemplateDataService::getBusinessData()`).

    **ما كان** — البيانات مكتوبةً في متن كلّ قالبٍ حرفاً بحرف: «مؤسسة اعمال
    خاصة» ورقمُ مشرفٍ في قالب الفاتورة، و«مؤسسة الطيب الافضل للتجارة» ورقمٌ
    ضريبيٌّ `310213567700003` في قالب الفاتورة الضريبية. فمن سجّل بيانات
    منشأته في الإعدادات طبع باسم منشأةٍ أخرى — ورقمٌ ضريبيٌّ ليس رقمَ البائع
    في فاتورةٍ ضريبية عيبٌ نظاميّ لا شكليّ.

    **ما صار** — مصدرٌ واحد، وترويسةٌ واحدة يتقاسمها القالبان.

    وكلُّ سطرٍ مشروطٌ بامتلائه: إيصالٌ عرضُه 80mm لا يحتمل «الرقم الضريبي:»
    يتبعه فراغ.

    @param string|null $title  عنوان المستند فوق اسم المنشأة
--}}
@php $org = \App\Support\BusinessInfo::all(); @endphp

<div class="receipt-header">
    <h1 class="receipt-title">{{ $title ?? 'فاتورة' }}</h1>

    @if (! empty($org['logo']))
        <img src="{{ $org['logo'] }}" alt="{{ $org['name'] ?? '' }}" class="org-logo">
    @endif

    <p class="mb-0 org-name">{{ $org['name'] ?? '' }}</p>

    @if (! empty($org['address']))
        <p class="mb-0">{{ $org['address'] }}</p>
    @endif
    @if (! empty($org['phone']))
        <p class="mb-0">هاتف: <bdi dir="ltr">{{ $org['phone'] }}</bdi></p>
    @endif
    @if (! empty($org['email']))
        <p class="mb-0"><bdi dir="ltr">{{ $org['email'] }}</bdi></p>
    @endif
    @if (! empty($org['tax_number']))
        <p class="mb-0">الرقم الضريبي: <bdi dir="ltr">{{ $org['tax_number'] }}</bdi></p>
    @endif
    @if (! empty($org['commercial_registration']))
        <p class="mb-0">س.ت: <bdi dir="ltr">{{ $org['commercial_registration'] }}</bdi></p>
    @endif
</div>
