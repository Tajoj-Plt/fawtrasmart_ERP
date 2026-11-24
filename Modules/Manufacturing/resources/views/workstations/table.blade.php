{{--
    جدول محطات العمل — يُستبدل وحده عند البحث والتنقّل بـAjax.

    على قياس جدول فواتير المبيعات: مُنتقي عدد السجلّات فوق الجدول، وعمودُ
    «تابع لـ»، وترقيمٌ بأرقام الصفحات في الذيل.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $workstations
--}}

@php
    $canEdit = hasPermission('stock.workstations.edit');
    $canDelete = hasPermission('stock.workstations.delete');
@endphp

@if ($workstations->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">
        @include('stock::components.per-page', ['onchange' => 'loadData(1)'])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr>
                    <th style="width:24%">المحطة</th>
                    <th>الوصف</th>
                    <th style="width:14%">تابع لـ</th>
                    <th style="width:10%">الوحدة</th>
                    <th style="width:14%">تكلفة الساعة</th>
                    <th style="width:14%">أُضيفت</th>
                    <th class="text-center" style="width:7%">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workstations as $workstation)
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-cpu"></i></span>
                                <div class="stk-media__body">
                                    <a href="{{ route('manufacturing.workstations.show', $workstation->id) }}" class="stk-media__title">
                                        {{ $workstation->name }}
                                    </a>
                                    <span class="stk-media__sub stk-num">#{{ $workstation->code ?: '—' }}</span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="stk-media__sub">{{ \Illuminate\Support\Str::limit($workstation->description, 80) ?: '—' }}</span>
                        </td>

                        <td>{{ \Modules\Manufacturing\Support\BranchTree::name($workstation->branch_id) }}</td>

                        {{-- الوحدةُ ثابتة — تكلفةُ المحطّة تُحسب للساعة دائماً --}}
                        <td>{{ \App\Models\WorkStations::UNIT }}</td>

                        <td class="stk-num stk-balance">{{ $workstation->formatMoney($workstation->total_cost) }}</td>

                        <td>
                            <span class="stk-num">{{ optional($workstation->created_at)->format('Y-m-d') ?: '—' }}</span>
                            <span class="stk-media__sub d-block">{{ optional($workstation->createdByUser)->name ?: '—' }}</span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="خيارات {{ $workstation->name }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('manufacturing.workstations.show', $workstation->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>
                                    @if ($canEdit)
                                        <a class="dropdown-item" href="{{ route('manufacturing.workstations.edit', $workstation->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endif
                                    @if ($canDelete)
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-btn" href="#"
                                           data-id="{{ $workstation->id }}" data-name="{{ $workstation->name }}">
                                            <i class="feather icon-trash-2"></i> حذف
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('manufacturing::partials.table-foot', ['paginator' => $workstations])
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-cpu"></i></div>
        <h5 class="stk-empty__title">لا محطات عمل</h5>
        <p class="stk-empty__hint">لا محطات تطابق البحث في هذه الجهة — غيّر الفلتر أو أضف محطة عمل جديدة</p>
    </div>
@endif
