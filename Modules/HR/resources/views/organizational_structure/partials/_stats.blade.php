{{--
    صفُّ الإحصاءات أعلى قوائم الهيكل التنظيمي.

      statItems  [['label' => '…', 'value' => 0, 'icon' => 'layers', 'tone' => 'primary|success|muted|info'], …]
--}}
@php
    $orgStatTones = [
        'primary' => 'background: var(--app-primary-soft); color: var(--app-primary);',
        'success' => 'background: var(--app-success-soft); color: var(--app-success);',
        'muted' => 'background: color-mix(in srgb, var(--app-muted) 12%, transparent); color: var(--app-muted);',
        'info' => 'background: color-mix(in srgb, var(--app-info) 12%, transparent); color: var(--app-info);',
    ];
@endphp
<div class="row">
    @foreach ($statItems as $statItem)
        <div class="col-lg-3 col-sm-6 col-12">
            <div class="card">
                <div class="stk-stat">
                    <span class="stk-stat__icon" style="{{ $orgStatTones[$statItem['tone'] ?? 'primary'] ?? $orgStatTones['primary'] }}" aria-hidden="true">
                        <i class="feather icon-{{ $statItem['icon'] }}"></i>
                    </span>
                    <div class="min-width-0">
                        <p class="stk-stat__value">{{ number_format((int) $statItem['value']) }}</p>
                        <p class="stk-stat__label">{{ $statItem['label'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
