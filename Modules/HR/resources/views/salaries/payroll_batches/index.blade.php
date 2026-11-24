@extends('hr::master')

@section('title', 'مسير الصرفات')

@section('content')
    @include('stock::components.page-head', [
        'title' => 'مسير الصرفات',
        'icon' => 'layers',
        'sub' => 'اعتمادُ مجموعة صرفاتٍ معلّقة دفعةً واحدة قبل خروج النقد — لا اعتمادُ القسائم',
        'actions' => hasPermission('payroll.approve')
            ? '<a href="' . route('hr.payroll-batches.create') . '" class="btn btn-primary"><i class="fas fa-plus"></i> تجميع صرفات</a>'
            : '',
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-payroll-batches.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه مسيراتُ الصرف: مجموعةُ صرفاتٍ تُعتمَد معاً بدل اعتمادها واحدةً واحدة.',
            'blocks' => [
                'لماذا مسير' => [
                    'الاعتمادُ يقع على المسير كلِّه مرّةً واحدة، فيُعرَف من اعتمد صرفَ الشهر ومتى.',
                    'والمسيرُ يجمع صرفاتٍ قائمةً ولا يُنشئ استحقاقاً جديداً: ما ليس له قسيمةٌ لا يدخله.',
                ],
                'ما يترتّب على الاعتماد' => [
                    'تصير صرفاتُ المسير «معتمدة — بانتظار الصرف»، ثمّ «مدفوعة» عند صرف كلٍّ منها — سندٌ وقيدٌ لكلّ صرفة لا قيدٌ واحدٌ للمسير.',
                    'وما يُمنع صرفُه لسببه — موظفٌ غيرُ نشط، أو فترةٌ مقفلة، أو رصيدُ خزينةٍ لا يكفي — يُرفض وحدَه ولا يُوقف بقيّةَ المسير.',
                ],
            ],
            'facts' => [
                'المسيرُ تصريحٌ بالصرف لا صرف',
                'الاعتمادُ يُعرَف صاحبُه ووقتُه',
            ],
        ])

        <div class="card stk-filters">
            <div class="card-body">
                <form method="GET" action="{{ route('hr.payroll-batches.index') }}" class="d-flex align-items-end flex-wrap" style="gap:12px">
                    <div class="form-group mb-0" style="min-width:220px">
                        <label for="status">الحالة</label>
                        <select name="status" id="status" class="form-control hr-s2">
                            <option value="">الكل</option>
                            @foreach (\App\Models\PayrollBatch::statuses() as $k => $v)
                                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> تصفية</button>
                    @if (request()->filled('status'))
                        <a href="{{ route('hr.payroll-batches.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i></a>
                    @endif
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @if ($batches->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-list"></i> المسيرات
                        <span class="stk-count">{{ $batches->total() }}</span></h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>المسير</th>
                                <th>الفترة</th>
                                <th class="text-center">الصرفات</th>
                                <th class="text-center">حال الصرف</th>
                                <th>الاعتماد</th>
                                <th class="text-center" style="width:70px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($batches as $batch)
                                @php $state = $batch->disbursementState(); @endphp
                                <tr>
                                    <td>
                                        <span class="stk-media__title">{{ $batch->name }}</span>
                                        @if ($batch->payroll)
                                            <span class="stk-media__sub d-block">{{ $batch->payroll->name }}</span>
                                        @endif
                                    </td>
                                    <td class="stk-num">
                                        {{ $batch->period_from ? $batch->period_from->format('Y-m-d') . ' → ' . optional($batch->period_to)->format('Y-m-d') : '—' }}
                                    </td>
                                    <td class="text-center stk-num">{{ $state['paid'] }} / {{ $batch->runs_count }}
                                        <small class="d-block text-muted">مدفوعة / الكل</small>
                                    </td>
                                    <td class="text-center"><span class="stk-state stk-state--{{ $state['tone'] }}">{{ $state['label'] }}</span></td>
                                    <td>
                                        @if ($batch->approved_at)
                                            <span class="stk-num">{{ $batch->approved_at->format('Y-m-d H:i') }}</span>
                                            <small class="d-block text-muted">{{ optional($batch->approver)->name }}</small>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item" href="{{ route('hr.payroll-batches.show', $batch->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                </a>
                                                @if ($batch->isPending() && hasPermission('payroll.approve'))
                                                    <form method="POST" action="{{ route('hr.payroll-batches.approve', $batch->id) }}"
                                                          onsubmit="return confirm('اعتماد صرفات هذا المسير؟ تصير «معتمدة — بانتظار الصرف».');">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item"><i class="fa fa-check me-2 text-success"></i>اعتماد المسير</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($batches->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">عرض {{ $batches->firstItem() }}–{{ $batches->lastItem() }} من {{ $batches->total() }}</p>
                        {{ $batches->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-layers"></i></div>
                        <h5 class="stk-empty__title">لا مسيرات بعد</h5>
                        <p class="stk-empty__hint">جمّع صرفاتٍ معلّقة ثمّ اعتمدها دفعةً واحدة قبل الصرف.</p>
                        @if (hasPermission('payroll.approve'))
                            <a href="{{ route('hr.payroll-batches.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> تجميع صرفات</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @include('hr::components.table-menu')
@endpush
