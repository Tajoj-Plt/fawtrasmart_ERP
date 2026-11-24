{{--
    صفوف أوراق الجرد.

    كانت الحالة سلسلة `@switch` من ستّة فروع، والنوع أخرى من خمسة، ونافذة حذف
    كاملة داخل كل صفّ (مع أنّ الحذف يجري بـSweetAlert فلا تُفتح أبداً).
    هنا خريطتان ولا نوافذ.
--}}

@php
    // الحالة: [التسمية، اللون]
    $statuses = [
        'draft' => ['مسودة', 'secondary'],
        'in_progress' => ['قيد التنفيذ', 'warning'],
        'completed' => ['مكتمل', 'info'],
        'adjusted' => ['تمت التسوية', 'success'],
        'cancelled' => ['ملغي', 'danger'],
    ];

    $types = [
        'full' => 'شامل',
        'partial' => 'دوري',
        'spot' => 'مفاجئ',
        'category' => 'حسب الصنف',
    ];
@endphp

@foreach ($adjustments as $adjustment)
    @php
        [$statusLabel, $statusTone] = $statuses[$adjustment->status] ?? [$adjustment->status, 'secondary'];
        $reference = $adjustment->reference_number ?: $adjustment->id;
        $editable = in_array($adjustment->status, ['draft', 'in_progress'], true);
        $at = \Illuminate\Support\Carbon::parse($adjustment->inventory_time ?? $adjustment->created_at);
    @endphp

    <tr class="inventory-row"
        data-storehouse="{{ Str::lower(optional($adjustment->storeHouse)->name ?? '') }}"
        data-status="{{ $adjustment->status }}"
        data-type="{{ $adjustment->type }}">

        <td>
            <div class="stk-media">
                <span class="stk-media__thumb"><i class="feather icon-home"></i></span>
                <div class="stk-media__body">
                    <a href="{{ route('inventory.show', $adjustment->id) }}" class="stk-media__title">
                        {{ optional($adjustment->storeHouse)->name ?? 'مستودع محذوف' }}
                    </a>
                    <span class="stk-media__sub stk-num">#{{ $reference }}</span>
                </div>
            </div>
        </td>

        <td class="text-center">
            <span class="badge badge-light-{{ $statusTone }}">{{ $statusLabel }}</span>
            <span class="stk-media__sub d-block stk-num">{{ $at->format('Y-m-d H:i') }}</span>
        </td>

        <td class="text-center">{{ $types[$adjustment->type] ?? $adjustment->type }}</td>

        <td class="text-center">
            <span class="stk-num">{{ $adjustment->items->count() }}</span>
            <span class="stk-media__sub d-block">صنف</span>
        </td>

        <td class="text-center">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="dropdown"
                        aria-expanded="false" aria-label="خيارات ورقة الجرد {{ $reference }}">
                    <i class="feather icon-more-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="{{ route('inventory.show', $adjustment->id) }}">
                        <i class="feather icon-eye"></i> عرض
                    </a>

                    @if ($editable && hasPermission('stock.adjustments.edit'))
                        <a class="dropdown-item" href="{{ route('inventory.edit', $adjustment->id) }}">
                            <i class="feather icon-edit-2"></i> تعديل
                        </a>
                    @endif

                    @if ($adjustment->status === 'completed' && hasPermission('stock.adjustments.count'))
                        <a class="dropdown-item" href="{{ route('inventory.do_stock', $adjustment->id) }}">
                            <i class="feather icon-check-square"></i> تنفيذ الجرد
                        </a>
                    @endif

                    @if ($editable && hasPermission('stock.adjustments.delete'))
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger delete-inventory" href="#"
                           data-id="{{ $adjustment->id }}" data-name="{{ $reference }}">
                            <i class="feather icon-trash-2"></i> حذف
                        </a>
                    @endif
                </div>
            </div>
        </td>
    </tr>
@endforeach
