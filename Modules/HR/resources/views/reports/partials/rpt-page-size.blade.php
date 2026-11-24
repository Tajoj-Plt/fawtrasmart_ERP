{{-- عددُ الصفوف في الصفحة — داخل `.ag-tools` بجانب التصدير والطباعة (السكربت في `rpt-scripts`) --}}
@php $psCurrent = (int) request('per_page', 50); @endphp
<select id="hrPerPage" class="form-select form-select-sm d-inline-block w-auto" aria-label="عدد الصفوف">
    @foreach ([25, 50, 100, 200] as $n)
        <option value="{{ $n }}" @selected($psCurrent === $n)>{{ $n }} صف</option>
    @endforeach
</select>
