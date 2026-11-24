{{--
    شريط نسبة الإنجاز.

    كان الإنجاز يُعرض رقماً عارياً («٦٣.٤٪») في عمودٍ ضيّق، فلا يُقرأ منه
    قربُ الهدف من بعده إلّا بحساب. الشريط يقول ذلك بالنظرة الأولى.

    اللون يتبع القرب من الهدف لا مجرّد وجود قيمة: أحمر دون الخمسين،
    وبرتقالي دونها إلى الثمانين، وأزرق حتى بلوغ الهدف، وأخضر عند بلوغه.
    والألوان من `--app-*` التي تحقنها `App\Support\ThemeSettings`، فتتبع
    «مظهر النظام» بلا سطرٍ يُعدَّل هنا.

    العرض مقصورٌ على ١٠٠٪ حتى مع تجاوز الهدف — والرقم فوقه يقول الحقيقة
    كاملةً، فلا يُخفي الشريطُ تجاوزاً بلغ ٢٤٠٪.

    الوسائط:
      percentage  نسبة الإنجاز (رقم، قد يتجاوز 100)
      compact     شريطٌ أنحف بلا رقم (اختياري)
--}}
@php
    $pct = (float) ($percentage ?? 0);
    $width = max(0, min(100, $pct));
    $compact = (bool) ($compact ?? false);

    $tone = match (true) {
        $pct >= 100 => 'done',
        $pct >= 80 => 'near',
        $pct >= 50 => 'mid',
        default => 'low',
    };
@endphp

@once
    <style>
        .sl-pg { display: flex; flex-direction: column; gap: .25rem; min-width: 84px; }
        .sl-pg__track {
            height: .5rem;
            border-radius: 999px;
            background: var(--app-border, rgba(0, 0, 0, .09));
            overflow: hidden;
        }
        .sl-pg__fill {
            display: block;
            height: 100%;
            border-radius: 999px;
            transition: width .3s ease;
        }
        .sl-pg__fill--low  { background: var(--app-danger, #e04f5f); }
        .sl-pg__fill--mid  { background: var(--app-warning, #e0a341); }
        .sl-pg__fill--near { background: var(--app-primary, #4b7bec); }
        .sl-pg__fill--done { background: var(--app-success, #35b47e); }
        .sl-pg__value {
            font-size: .75rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: var(--app-muted, #6b7280);
        }
        .sl-pg--compact .sl-pg__track { height: .375rem; }
    </style>
@endonce

<div class="sl-pg {{ $compact ? 'sl-pg--compact' : '' }}">
    <div class="sl-pg__track" role="progressbar"
         aria-valuenow="{{ round($pct, 1) }}" aria-valuemin="0" aria-valuemax="100"
         aria-label="{{ __('sales_targets.stats.percentage') }}">
        <span class="sl-pg__fill sl-pg__fill--{{ $tone }}" style="width: {{ $width }}%"></span>
    </div>

    @unless ($compact)
        <span class="sl-pg__value">{{ \App\Support\CurrencySettings::number($pct, 1) }}%</span>
    @endunless
</div>
