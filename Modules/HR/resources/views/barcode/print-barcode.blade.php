{{--
    بطاقةُ حضورٍ تُطبَع وتُحمَل — لا صفحةُ ويب.

    **المقاس أوّلاً**: البطاقةُ ٨٥٫٦ × ٥٤ مم (CR80) — مقاسُ بطاقة البنك
    وحاملاتِ الرقبة الجاهزة. وكان التصميمُ السابق شريطاً رأسياً يمتدّ بعرض
    الشاشة ويطول بمحتواه، ونسختُه «للمحفظة» مصغَّرةٌ بـ`transform: scale(.7)`
    — والتحويلُ لا يُصغّر ما تحجزه الطابعةُ من ورق، فتخرج البطاقةُ صغيرةً
    في وسط صفحةٍ كاملةٍ فارغة.

    **والوجهان كوجهَي البطاقة الحقيقية**: الأمامُ هويّةٌ وباركود، والخلفُ
    تعليماتٌ و QR. وكانت التعليماتُ محشورةً تحت الباركود في الوجه الواحد
    فتطول البطاقةُ ويصغر الباركود — وهو وظيفتُها الوحيدة.

    **ولا شبكةَ تُنتظر**: الباركودُ و QR يصلان مُصيَّرَين من الخادم
    (SVG متّجه)، فتُطبع البطاقةُ على جهازٍ بلا إنترنت، وتبقى حادّةً مهما
    كبّرت الطابعة. انظر `BarcodeAttendanceController::printEmployeeBarcode`.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقة حضور — {{ $employee->full_name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

    @include('hr::barcode._partials.card-styles')
</head>
<body>

{{-- ══════════ الإجراءات ══════════ --}}
<div class="bar no-print">
    <div class="bar__who">
        <div class="bar__name">{{ $employee->full_name }}</div>
        <div class="bar__meta">
            بطاقة حضور وانصراف · {{ $employee->department->name ?? 'بلا قسم' }}
        </div>
    </div>

    <div class="bar__acts">
        <button class="btn btn--go" onclick="window.print()">
            {{-- أيقوناتٌ مضمّنة: كانت الصفحةُ تحمّل Font Awesome وتكتب أصنافَ Feather،
                 فلا تظهر أيقونةٌ واحدة — مكتبةٌ تُحمَّل لأجل لا شيء. --}}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            طباعة البطاقة
        </button>

        <button class="btn" onclick="downloadCard()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            تحميل الباركود
        </button>

        <button class="btn" onclick="window.close()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            إغلاق
        </button>
    </div>
</div>

<div class="sheet">

    {{-- ══════════ الوجه الأمامي ══════════ --}}
    <div class="card__wrap">
        @include('hr::barcode._partials.card-front', ['cardId' => 'card-front'])

        <div class="card__label no-print">الوجه الأمامي</div>
    </div>

    {{-- ══════════ الوجه الخلفي ══════════ --}}
    <div class="card__wrap">
        <div class="card card--back">
            <div class="back__head">تعليمات الاستخدام</div>

            <div class="back__grid">
                <div class="back__qr">
                    {!! $qrSvg !!}
                    <small>بديلٌ عن الباركود</small>
                </div>

                <ol class="back__list">
                    <li>امسح البطاقة عند الحضور وعند الانصراف.</li>
                    <li>البطاقة شخصية — استخدامها من غير صاحبها مخالفة.</li>
                    <li>حافظ على نظافة الباركود وسلامته من الثني.</li>
                    <li>عند الفقد أبلغ الموارد البشرية فوراً لإيقافها.</li>
                </ol>
            </div>

            <div class="back__foot">
                <span>صدرت {{ \Carbon\Carbon::now()->format('Y/m/d') }}</span>
                <span>{{ \App\Support\OrganizationInfo::name() }}</span>
            </div>
        </div>
        <div class="card__label no-print">الوجه الخلفي</div>
    </div>

</div>

<script>
    /**
     * تنزيلُ الباركود صورةً.
     *
     * كان يقرأ `canvas.toDataURL()` من عنصرٍ لم يعد موجوداً — الباركودُ
     * صار SVG من الخادم. فيُرسَم على `canvas` عند الطلب ثم يُنزَّل.
     */
    function downloadCard() {
        const svg = document.querySelector('#card-front .strip svg');
        if (!svg) return;

        const source = new XMLSerializer().serializeToString(svg);
        const url = URL.createObjectURL(new Blob([source], { type: 'image/svg+xml;charset=utf-8' }));
        const img = new Image();

        img.onload = function () {
            const scale = 3;                     // دقّةٌ تكفي الطباعة لا الشاشة
            const canvas = document.createElement('canvas');
            canvas.width = img.width * scale;
            canvas.height = img.height * scale;

            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            const a = document.createElement('a');
            a.download = 'barcode-{{ $employee->employee_code ?? $employee->id }}.png';
            a.href = canvas.toDataURL('image/png');
            a.click();

            URL.revokeObjectURL(url);
        };

        img.src = url;
    }
</script>

</body>
</html>
