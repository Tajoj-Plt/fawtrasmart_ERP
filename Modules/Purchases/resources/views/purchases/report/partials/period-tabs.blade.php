{{--
    أزرار الفترة — روابط لا أزرار، فتبقى الفترة في الرابط قابلةً للحفظ
    والمشاركة، ويعمل زرّ الرجوع في المتصفّح كما يتوقّع القارئ.

    الأصناف هي `period-tabs` نفسها التي تحملها أزرار تقارير المبيعات،
    فيتطابق المظهر بين القسمين.

    @param string $route     اسم مسار التقرير
    @param array  $query     معاملات الرابط بلا التواريخ
    @param mixed  $period    ReportPeriod الحالية
    @param string|null $customTarget  معرّف الحقل الذي يُركَّز عليه عند «مخصص»
--}}
@php
    use Modules\Purchases\Support\ReportPeriod;

    $customTarget = $customTarget ?? 'rpt_date_from';

    // أيقونة لكل مفتاح فترة — تُقرأ مرّة، فلا تتكرّر داخل الحلقة
    $icons = [
        'today' => 'calendar-day',
        'week' => 'calendar-week',
        'month' => 'calendar-alt',
        'quarter' => 'calendar',
        'year' => 'calendar-check',
    ];
@endphp

<div class="period-tabs">
    @foreach (ReportPeriod::TABS as $key => $label)
        <a href="{{ route($route, array_merge($query, ['period' => $key])) }}"
           class="btn {{ $period->key === $key ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fa fa-{{ $icons[$key] ?? 'calendar' }}"></i> {{ $label }}
        </a>
    @endforeach
    <a href="#rptCustom" class="btn {{ $period->key === 'custom' ? 'btn-primary' : 'btn-outline-primary' }}"
       onclick="document.getElementById('{{ $customTarget }}').focus(); return false;">
        <i class="fa fa-calendar-plus"></i> مخصص
    </a>
</div>
