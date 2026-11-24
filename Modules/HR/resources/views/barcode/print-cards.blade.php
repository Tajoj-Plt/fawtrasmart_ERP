{{--
    طباعةُ دفعةٍ من بطاقات الحضور.

    **الوجهُ الأمامي وحده** — بخلاف الطباعة المفردة. الخلفُ تعليماتٌ واحدةٌ
    لا تختلف بين موظفٍ وآخر، وتبديلُ وجهٍ بوجهٍ عبر عشرين بطاقةً يجعل
    تجميعَها بعد القصّ عملاً يدوياً طويلاً. ومن أراد الخلفَ طبعه مرّةً من
    شاشة البطاقة المفردة ونسخه.

    والمقاسُ والأنماطُ من القالب المشترك نفسِه، فلا تخرج الدفعةُ مخالفةً
    لما رآه من طبع بطاقةً واحدة.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة بطاقات الحضور ({{ count($cards) }})</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

    @include('hr::barcode._partials.card-styles')

    <style>
        .tally {
            max-width: 760px; margin: 0 auto 26px;
            background: #fff; border: 1px solid var(--line); border-radius: 14px;
            padding: 14px 18px; display: flex; align-items: center;
            justify-content: space-between; gap: 16px; flex-wrap: wrap;
            box-shadow: 0 1px 2px rgba(15,23,42,.04);
        }
        .tally__n    { font-weight: 700; font-size: 15px; }
        .tally__meta { font-size: 12.5px; color: var(--ink-mute); margin-top: 2px; }
        .tally__warn {
            width: 100%; margin-top: 10px; padding: 10px 12px;
            background: #fef3c7; border: 1px solid #fcd34d; border-radius: 9px;
            font-size: 12.5px; color: #92400e;
        }
        .empty {
            max-width: 760px; margin: 0 auto; background: #fff;
            border: 1px solid var(--line); border-radius: 14px;
            padding: 40px 20px; text-align: center; color: var(--ink-mute);
        }
    </style>
</head>
<body>

<div class="tally no-print">
    <div>
        <div class="tally__n">{{ count($cards) }} بطاقة جاهزة للطباعة</div>
        <div class="tally__meta">الوجه الأمامي فقط · ٨ بطاقات في صفحة A4 · اقصُصْ على الحدّ</div>
    </div>

    <div class="bar__acts">
        <button class="btn btn--go" onclick="window.print()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            طباعة الكل
        </button>
        <button class="btn" onclick="window.close()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            إغلاق
        </button>
    </div>

    @if ($truncated)
        {{-- حدٌّ يُقال لا يُسكَت عنه: صفحةٌ تطبع ٢٠٠ من ٥٠٠ بلا كلمةٍ تُوهم أنها طبعت الكلّ --}}
        <div class="tally__warn">
            عُرضت أوّل {{ $limit }} بطاقة من {{ $total }} مطابقة. ضيّق التصفية
            (بالقسم أو الحالة) لطباعة الباقي على دفعات.
        </div>
    @endif
</div>

@if (count($cards) === 0)
    <div class="empty no-print">
        لا توجد بطاقات مطابقة للتصفية. البطاقةُ تُطبَع لمن صدرت له بطاقةٌ فقط —
        ومن لا بطاقةَ له يُصدَر له أوّلاً من شاشة إدارة البطاقات.
    </div>
@else
    <div class="sheet">
        @foreach ($cards as $card)
            <div class="card__wrap">
                @include('hr::barcode._partials.card-front', [
                    'employee'   => $card['employee'],
                    'barcodeSvg' => $card['barcodeSvg'],
                    'photoUrl'   => $card['photoUrl'],
                    'nameSize'   => $card['nameSize'],
                ])
            </div>
        @endforeach
    </div>
@endif

</body>
</html>
