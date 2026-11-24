@extends('manufacturing::master')

@section('title', $plan->name)

@section('content')
    @php
        $ratio = $plan->generatedRatio();
        // ما يُولَّد الآن: ما لم يُولَّد قطّ وما أُلغي أمرُه
        $pending = $ratio['generable'];
        $canGenerate = $plan->status->canGenerateOrders() && $pending > 0;
        $fmtQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');

        // ما سيحدث للمواد عند التوليد — يُقال في نافذة التأكيد قبل الضغط
        $reservationHint = match (true) {
            ! app(\Modules\Manufacturing\Services\ReservationService::class)->enabled() => '',
            (bool) $plan->storehouse_id => 'وتُحجز موادُّه في مستودع «' . ($plan->storeHouse->name ?? '') . '» إن كفى المتاح.',
            default => 'ولن تُحجز المواد: الخطّة بلا «مستودع المواد».',
        };
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('stock.production_plans.edit')
                @if ($plan->status->isEditable())
                    <a href="{{ route('manufacturing.production-plans.edit', $plan->id) }}" class="btn btn-outline-primary">
                        <i class="feather icon-edit-2"></i> تعديل
                    </a>
                @endif
            @endpermission

            @permission('stock.production_plans.approve')
                @if ($plan->status->canApprove())
                    <form action="{{ route('manufacturing.production-plans.approve', $plan->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-check-square"></i> اعتماد الخطّة
                        </button>
                    </form>
                @endif

                @if ($plan->status->canRevertToDraft())
                    <form action="{{ route('manufacturing.production-plans.revert', $plan->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="feather icon-corner-up-left"></i> فكّ الاعتماد
                        </button>
                    </form>
                @endif
            @endpermission

            @permission('stock.production_plans.generate_orders')
                @if ($canGenerate)
                    <button type="button" class="btn btn-success" id="generateBtn">
                        <i class="feather icon-play"></i> توليد أوامر التصنيع ({{ $pending }})
                    </button>
                @endif
            @endpermission

            @permission('stock.production_plans.approve')
                @if ($plan->status->canCancel())
                    <button type="button" class="btn btn-outline-danger" id="cancelBtn">
                        <i class="feather icon-x-circle"></i> إلغاء الخطّة
                    </button>
                @endif
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => $plan->name,
            'icon' => 'calendar',
            'sub' => '#' . $plan->code . ' — ' . $plan->status->label(),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'خطط الإنتاج' => route('manufacturing.production-plans.index'),
                $plan->name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        @if (session('warning'))
            {{-- `pre-line`: رسالةُ نواقص الحجز سطرٌ لكلّ مادّة --}}
            <div class="alert alert-warning" style="white-space:pre-line">{{ session('warning') }}</div>
        @endif

        {{--
            الخطّةُ بلا مسؤول: الخانةُ تُفرَّغ وحدَها حين يُحذف سجلّ الموظّف
            (`nullOnDelete`)، والتوليدُ يرفض بعدها. يُقال هنا قبل الضغط.
        --}}
        @if (! $plan->employee_id)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                الخطّة بلا مسؤول — الأرجح أنّ سجلّ الموظّف حُذف. عيِّن مسؤولاً من التعديل قبل توليد الأوامر.
            </div>
        @endif

        {{-- ══════════ المؤشّرات ══════════ --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $plan->status->icon() }}"></i></span>
                <span class="stk-metric__value">{{ $plan->status->label() }}</span>
                <span class="stk-metric__label">الحالة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                <span class="stk-metric__value stk-num">{{ $ratio['total'] }}</span>
                <span class="stk-metric__label">بنود الخطّة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clipboard"></i></span>
                <span class="stk-metric__value stk-num">{{ $ratio['generated'] }}</span>
                <span class="stk-metric__label">أوامر مولَّدة</span>
            </div>
            {{-- الخطّةُ تكتمل حين تكتمل أوامرُها القائمة كلُّها — لا حين تُولَّد --}}
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__value stk-num">{{ $ratio['finished'] }} / {{ $ratio['generated'] }}</span>
                <span class="stk-metric__label">أوامر مكتملة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num">{{ $fmtQty($plan->plannedQuantity()) }}</span>
                <span class="stk-metric__label">إجمالي الكميّة المخطّطة</span>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#items" role="tab">
                            <i class="feather icon-list"></i> البنود
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#info" role="tab">
                            <i class="feather icon-info"></i> بيانات الخطّة
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#activity" role="tab">
                            <i class="feather icon-activity"></i> سجلّ النشاط
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content p-2">

                {{-- ══════════ البنود ══════════ --}}
                <div class="tab-pane active" id="items" role="tabpanel">
                    @if ($plan->items->isEmpty())
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-list"></i></div>
                            <h5 class="stk-empty__title">لا بنود</h5>
                            <p class="stk-empty__hint">أضف بنوداً من شاشة التعديل — كلُّ بندٍ يصير أمرَ تصنيع</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>قائمة المواد</th>
                                        <th>مسار الإنتاج</th>
                                        <th class="text-center">الكميّة المخطّطة</th>
                                        <th>أمر التصنيع</th>
                                        <th>ملاحظة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($plan->items as $item)
                                        <tr>
                                            <td>{{ $item->product->name ?? '—' }}</td>
                                            <td>
                                                {{ $item->productionMaterial->name ?? '—' }}
                                                @if (($item->productionMaterial->quantity ?? 0) > 0)
                                                    <span class="stk-help">الوصفة لـ{{ $fmtQty($item->productionMaterial->quantity) }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $item->productionPath->name ?? '—' }}</td>
                                            <td class="text-center stk-num">{{ $fmtQty($item->planned_quantity) }}</td>
                                            <td>
                                                @if ($item->manufacturOrder)
                                                    @permission('stock.manufacturing_orders.show')
                                                        <a class="stk-num" href="{{ route('manufacturing.orders.show', $item->manufactur_order_id) }}">
                                                            #{{ $item->manufacturOrder->code }}
                                                        </a>
                                                    @else
                                                        <span class="stk-num">#{{ $item->manufacturOrder->code }}</span>
                                                    @endpermission
                                                    <span class="badge bg-{{ $item->manufacturOrder->status->tone() }}">
                                                        {{ $item->manufacturOrder->status->label() }}
                                                    </span>
                                                    @if ($item->isGenerable() && $plan->status->canGenerateOrders())
                                                        <span class="stk-help">أُلغي الأمر — يُولَّد للبند أمرٌ جديد عند التوليد</span>
                                                    @endif
                                                @else
                                                    <span class="stk-media__sub">لم يُولَّد بعد</span>
                                                @endif
                                            </td>
                                            <td><span class="stk-media__sub">{{ $item->notes ?: '—' }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ══════════ بيانات الخطّة ══════════ --}}
                <div class="tab-pane" id="info" role="tabpanel">
                    <dl class="stk-def">
                        <div class="stk-def__row"><dt>الكود</dt><dd class="stk-num">{{ $plan->code }}</dd></div>
                        <div class="stk-def__row">
                            <dt>الفترة</dt>
                            <dd class="stk-num">{{ optional($plan->from_date)->format('Y-m-d') }} ← {{ optional($plan->to_date)->format('Y-m-d') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>المسؤول</dt>
                            <dd>{{ $plan->employee ? trim($plan->employee->first_name . ' ' . ($plan->employee->middle_name ?? '')) : '—' }}</dd>
                        </div>
                        <div class="stk-def__row"><dt>العميل</dt><dd>{{ $plan->client->trade_name ?? 'إنتاجٌ للمخزون' }}</dd></div>
                        <div class="stk-def__row"><dt>مستودع المواد</dt><dd>{{ $plan->storeHouse->name ?? '—' }}</dd></div>
                        <div class="stk-def__row"><dt>الحساب</dt><dd>{{ $plan->account->name ?? 'من إعدادات الحسابات' }}</dd></div>
                        <div class="stk-def__row"><dt>ملاحظات</dt><dd style="white-space:pre-line">{{ $plan->notes ?: '—' }}</dd></div>
                    </dl>
                </div>

                {{-- ══════════ سجلّ النشاط ══════════ --}}
                <div class="tab-pane" id="activity" role="tabpanel">
                    @forelse ($logs as $day => $entries)
                        <h6 class="stk-table-head__title mt-1">{{ $day }}</h6>
                        <ul class="list-unstyled">
                            @foreach ($entries as $log)
                                <li class="mb-1">
                                    <span class="stk-media__sub stk-num">{{ $log->created_at->format('H:i') }}</span>
                                    —
                                    @include('manufacturing::partials.log-description', ['log' => $log])
                                </li>
                            @endforeach
                        </ul>
                    @empty
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                            <h5 class="stk-empty__title">لا نشاط بعد</h5>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{--
        التوليدُ والإلغاءُ POST لا GET — درسُ `Paths/delete`: رابطٌ يغيّر الحالة
        بمجرّد فتحه تكفيه صورةٌ في بريد. النموذجان يُرسَلان بعد تأكيد.
    --}}
    @permission('stock.production_plans.generate_orders')
        @if ($canGenerate)
            <form id="generateForm" action="{{ route('manufacturing.production-plans.generate-orders', $plan->id) }}" method="POST" class="d-none">
                @csrf
            </form>
        @endif
    @endpermission

    @permission('stock.production_plans.approve')
        @if ($plan->status->canCancel())
            <form id="cancelForm" action="{{ route('manufacturing.production-plans.cancel', $plan->id) }}" method="POST" class="d-none">
                @csrf
                <input type="hidden" name="reason" id="cancelReason">
            </form>
        @endif
    @endpermission
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            $('#generateBtn').on('click', function () {
                Swal.fire({
                    icon: 'question',
                    title: 'توليد أوامر التصنيع؟',
                    html: 'سيُنشأ أمرُ تصنيعٍ لكلّ بندٍ لم يُولَّد أمرُه (<b>{{ $pending }}</b>)، '
                        + 'وتُنسخ بنودُ الوصفة إليه مقيسةً على الكميّة المخطّطة (المصروفُ الثابت لا يُقاس). '
                        + @json($reservationHint) + ' '
                        + 'ويُحتسب كلُّ أمرٍ من عدّاد أوامر التصنيع في الباقة.',
                    showCancelButton: true,
                    confirmButtonText: 'توليد',
                    cancelButtonText: 'إلغاء',
                }).then(result => {
                    if (result.isConfirmed) $('#generateForm').trigger('submit');
                });
            });

            $('#cancelBtn').on('click', function () {
                Swal.fire({
                    icon: 'warning',
                    title: 'إلغاء الخطّة؟',
                    text: 'الإلغاء نهائيّ. الأوامرُ المولَّدة منها لا تُمَسّ — تُلغى كلٌّ منها من شاشتها.',
                    input: 'text',
                    inputPlaceholder: 'سبب الإلغاء (اختياري)',
                    showCancelButton: true,
                    confirmButtonText: 'إلغاء الخطّة',
                    cancelButtonText: 'تراجع',
                    confirmButtonColor: '#dc3545',
                }).then(result => {
                    if (! result.isConfirmed) return;

                    $('#cancelReason').val(result.value || '');
                    $('#cancelForm').trigger('submit');
                });
            });
        });
    </script>
@endsection
