{{--
    رسائلُ الجلسة — مكانٌ واحد لأربعة مفاتيح.

    المتحكّماتُ تُرجع `success` و`error` و`warning`، ويُضاف إليها أخطاءُ
    التحقّق. وتكرارُ عرضها في كل شاشة كان يعني شاشةً تنسى `warning` فتُخفي
    عن المستخدم أنّ الفاتورة لم تُصدَر بعد إكمال الحجز.
--}}

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="feather icon-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="feather icon-alert-triangle"></i> {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="feather icon-alert-circle"></i> {{ session('error') }}
        {{-- الرسالةُ تقول السبب، والدليلُ يقول الحلّ --}}
        @if (Route::has('Reservations.guide') && ! request()->routeIs('Reservations.guide'))
            <a href="{{ route('Reservations.guide') }}#why" class="alert-link ms-1">{{ __('reservations.start.why_link') }}</a>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="feather icon-x-octagon"></i> تعذّر حفظ البيانات:</strong>
        <ul class="mb-0 mt-1" style="padding-inline-start:1.2rem">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
    </div>
@endif
