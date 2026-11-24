{{--
    جدول تقرير أهداف العملاء — على نمط تقارير العملاء (`rpt-table ag-table`)
    داخل بطاقة `report.blade.php`.

    القالبُ نفسُه يُعاد مستقلّاً من المتحكّم بـ`export=1` (كلُّ الصفوف
    المطابقة، `exportMode`)، فتقرأ الشاشةُ `tbody tr` منه وتنسخها إلى
    `#targetsExportTable`. لذا يبقى جدولاً واحداً بـtbody واحد، وتبقى
    نصوصُ الخلايا كما هي: التصديرُ والطباعةُ يأخذان `textContent`.

    رابطُ العميل مكتوبٌ هنا لا `@include` لكلّ صفّ: كلُّ include يُشغّل
    مُركِّبَ العرض العامّ باستعلام، والتصديرُ يرسم العملاءَ كلَّهم (حتى ٣٨٥٢).
--}}
@php
    $tgPill = fn ($model, string $empty) => $model
        ? '<span class="rpt-pill" style="background:'.e($model->color ?: '#6c757d').'; color:#fff">'.e($model->name).'</span>'
        : '<span class="text-muted small">'.e($empty).'</span>';
@endphp
@once
    <style>
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
    </style>
@endonce
<table class="rpt-table ag-table">
    <thead>
        <tr>
            <th>كود العميل</th>
            <th>اسم العميل</th>
            <th>الحالة</th>
            <th>الوضع</th>
            <th>الفرع</th>
            <th>الموظف المسؤول</th>
            <th>الهدف / التصنيف الحالي</th>
            <th>تاريخ آخر تقييم</th>
        </tr>
    </thead>
    <tbody>
        @forelse($clients as $client)
            @php $tgName = $client->trade_name ?: trim($client->first_name . ' ' . $client->last_name); @endphp
            <tr data-row="1">
                <td class="rpt-num">{{ $client->code }}</td>
                <td class="rpt-name">
                    <a href="{{ route('clients.show', $client->id) }}" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل" onclick="event.stopPropagation()">{{ $tgName !== '' ? $tgName : '-' }}</a>
                </td>
                <td>{!! $tgPill($client->status_client, 'بدون حالة') !!}</td>
                <td>{!! $tgPill($client->registrationStage, 'بدون وضع') !!}</td>
                <td>
                    <i class="fas fa-building me-1 text-muted"></i>
                    {{ optional($client->branch)->name ?? 'غير محدد' }}
                </td>
                <td>
                    <i class="fas fa-user me-1 text-muted"></i>
                    {{ optional($client->employee)->full_name ?? 'غير محدد' }}
                </td>
                <td>
                    @if($client->customerTarget)
                        <span class="ag-bucket" style="--c: {{ $client->customerTarget->color ?: '#64748b' }};">
                            <i class="fas fa-star" style="font-size: .7rem;"></i> {{ $client->customerTarget->name }}
                        </span>
                    @else
                        <span class="ag-bucket">غير مقيّم</span>
                    @endif
                </td>
                <td>
                    @if($client->target_evaluated_at)
                        <span style="font-size: .85rem; white-space: nowrap;">{{ \Carbon\Carbon::parse($client->target_evaluated_at)->format('Y-m-d H:i') }}</span>
                        {{-- «منذ …» للشاشة وحدها: في التصدير تلتصق بالتاريخ في الخلية نفسها --}}
                        @unless($exportMode ?? false)
                            <small class="text-muted d-block">{{ \Carbon\Carbon::parse($client->target_evaluated_at)->diffForHumans() }}</small>
                        @endunless
                    @else
                        <span class="text-muted small">لم يتم التقييم بعد</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="rpt-empty"><i class="fas fa-search-minus"></i> لا توجد بيانات مطابقة للبحث</td>
            </tr>
        @endforelse
    </tbody>
</table>
