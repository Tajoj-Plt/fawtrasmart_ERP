{{--
    خياراتُ «حالة العميل» في قوائم الفلترة — `Statuses::clientFilterOptions()`.

    المعتمدةُ (نشط/موقوف) أوّلاً، ثمّ «حالات قديمة» ما زال عليها عملاء: لا تُختار
    حالةً جديدة (`assignable()`) لكنّ عملاءها يجب أن يُوصَل إليهم بالفلتر.

    @param string|int|null $sfSelected  القيمةُ المختارة (الافتراض `request('status')`)
--}}
@php
    $sfSelected = (string) ($sfSelected ?? request('status', ''));
    $sfOptions = \App\Models\Statuses::clientFilterOptions();
    $sfGroups = [
        'الحالات المعتمدة' => $sfOptions->where('is_legacy', false),
        'حالات قديمة (ما زال عليها عملاء)' => $sfOptions->where('is_legacy', true),
    ];
@endphp
@foreach ($sfGroups as $sfLabel => $sfItems)
    @continue ($sfItems->isEmpty())
    <optgroup label="{{ $sfLabel }}">
        @foreach ($sfItems as $sfStatus)
            <option value="{{ $sfStatus->id }}" @selected($sfSelected === (string) $sfStatus->id)>{{ $sfStatus->name }} ({{ number_format($sfStatus->clients_count) }})</option>
        @endforeach
    </optgroup>
@endforeach
