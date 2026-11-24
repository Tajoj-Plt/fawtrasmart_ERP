{{-- شبكة مؤشرات. $items: [['label','value','icon','tone','note'?,'url'?], …] — والبطاقة ذات الرابط تنقيبٌ إلى ما يكوّن الرقم. --}}
<div class="re-kpis">
    @foreach ($items as $kpi)
        @php $tag = empty($kpi['url']) ? 'div' : 'a'; @endphp
        <{{ $tag }} class="re-kpi" @if ($tag === 'a') href="{{ $kpi['url'] }}" @endif>
            <span class="re-kpi__icon bg-rgba-{{ $kpi['tone'] ?? 'primary' }} text-{{ $kpi['tone'] ?? 'primary' }}"><i class="feather icon-{{ $kpi['icon'] }}"></i></span>
            <span>
                <span class="re-kpi__value d-block">{{ $kpi['value'] }}</span>
                <span class="re-kpi__label d-block">{{ $kpi['label'] }}</span>
                @isset($kpi['note']) <span class="re-kpi__note d-block">{{ $kpi['note'] }}</span> @endisset
            </span>
        </{{ $tag }}>
    @endforeach
</div>
