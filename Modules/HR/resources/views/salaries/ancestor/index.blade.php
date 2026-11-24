@extends('hr::master')

@section('title')
    سلفة الراتب
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <div class="d-flex align-items-center">
                <i class="feather icon-check-circle me-2"></i>
                <strong>{{ session('success') }}</strong>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'السلفة الراتب',
        'crumbs' => ['الرئيسية' => null, 'عرض' => null],
    ])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-advances-ledger.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه السلفُ القائمة على الموظفين وأقساطُها: ما صُرف، وما سُدِّد، وما بقي. وهي متابعةُ ذممٍ لا شاشةُ طلبات.',
            'blocks' => [
                'من أين تأتي السلف' => [
                    'من طلبٍ اعتُمد، أو من إثباتٍ مباشرٍ لسلفةٍ على الموظف. وفي الحالين تُقيَّد على حساب سلفه.',
                ],
                'السدادُ والأقساط' => [
                    'القسطُ يُقتطَع من الراتب عند الصرف فيُنقص المتبقّي؛ ويجوز إثباتُ سدادٍ نقديٍّ خارج الراتب.',
                    'وكلُّ سدادٍ يكتب قيدَه ويحرّك خزينةً إن كان نقداً — فليس تعديلَ رقمٍ في جدول.',
                ],
                'نهايةُ الخدمة' => [
                    'ما بقي من سلفةٍ على موظفٍ تنتهي خدمتُه يُسوّى ضمن تسوية نهاية الخدمة، فلا تُترَك ذمّةٌ معلَّقة.',
                ],
            ],
            'facts' => [
                'السلفةُ ذمّةٌ على الموظف بحسابٍ محاسبيّ',
                'كلُّ سدادٍ يكتب قيدَه',
            ],
        ])


    <div class="content-body">
        <div class="card">
          
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                 
                    <div class="d-flex" style="gap: 15px">
                        <a href="{{ route('ancestor.create') }}" class="btn btn-primary">
                            <i class="feather icon-plus me-2"></i>
                            أضف سلفة
                        </a>

                    </div>
                </div>
            </div>
        </div>

        <form class="form" method="GET" action="{{ route('ancestor.index') }}">
            <div class="card">
                <div class="card-body">
                    <div class="form-body row">
                        <div class="form-group col-md-4">
                            <label for="advance_search">السلف</label>
                            <input type="text" id="advance_search" class="form-control" placeholder="البحث بواسطة السلف"
                                name="advance_search" value="{{ request('advance_search') }}">
                        </div>

                        <div class="form-group col-md-4">
                            <label>إختر فترة القسط</label>
                            <select class="form-control hr-s2" name="payment_rate">
                                <option value="">إختر فترة القسط</option>
                                @foreach ($paymentRates as $key => $rate)
                                    <option value="{{ $key }}"
                                        {{ request('payment_rate') == $key ? 'selected' : '' }}>
                                        {{ $rate }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="employee_search">البحث بواسطة الموظف</label>


                            <select class="form-control hr-s2" name="employee_search" id="employee_search">
                                <option value="">كل الموظفين</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                        {{ (string) request('employee_search') === (string) $employee->id ? 'selected' : '' }}>
                                        {{ $employee->first_name }} {{ $employee->middle_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-body row">
                        <div class="form-group col-md-4">
                            <label>الدفعة القادمة (من)</label>
                            <input type="date" class="form-control text-start" name="next_payment_from"
                                value="{{ request('next_payment_from') }}">
                        </div>

                        <div class="form-group col-md-4">
                            <label>الدفعة القادمة (إلى)</label>
                            <input type="date" class="form-control text-start" name="next_payment_to"
                                value="{{ request('next_payment_to') }}">
                        </div>

                        <div class="form-group col-md-4">
                            <label>الحالة</label>
                            <select class="form-control hr-s2" name="status">
                                <option value="">إختر الحالة</option>
                                @foreach ($statuses as $key => $status)
                                    <option value="{{ $key }}"
                                        {{ request('status') == $key ? 'selected' : '' }}>
                                        {{ $status }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="collapse {{ request()->hasAny(['branch_id', 'tag']) ? 'show' : '' }}"
                        id="advancedSearchForm">
                        <div class="form-body row">
                            <div class="form-group col-12">
                                @include('reports.partials.affiliation-tree', [
                                    'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                                    'name' => 'branch_id',
                                    'showExact' => false,
                                    'label' => 'تابع لـ',
                                ])
                            </div>

                            <div class="form-group col-md-4">
                                <label>اختر وسم</label>
                                <input type="text" class="form-control" name="tag">


                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary mr-1">
                            <i class="feather icon-search"></i> بحث
                        </button>

                        <a class="btn btn-outline-secondary ml-2 mr-2" data-toggle="collapse"
                            data-target="#advancedSearchForm">
                            <i class="bi bi-sliders"></i> بحث متقدم
                        </a>

                        <a href="{{ route('ancestor.index') }}" class="btn btn-outline-warning">
                            <i class="feather icon-refresh-cw"></i> إعادة تعيين
                        </a>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div></div>
                    <div class="ms-auto">
                        <label style="margin-bottom: 0;">
                            عرض
                            <select name="per_page" aria-controls="DataTables_Table_0" class="form-select form-select-sm d-inline-block w-auto" onchange="window.location.href='{{ url()->current() }}?{{ http_build_query(request()->except('per_page')) }}&per_page='+this.value;">
                                <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page', 10) == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
                            </select>
                            سجل
                        </label>
                    </div>
                </div>
                <div class="table-responsive">
                <table class="table zero-configuration dataTable" id="ancestor-table">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الموظف</th>
                            <th>الأقساط المدفوعة</th>
                            <th>رصيد حساب السلف</th>
                            <th>الدفعة القادمة</th>
                            <th>وسوم</th>
                            <th style="width: 10%">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ancestors as $ancestor)
                            @php
                                $totalPaid = $ancestor->payments->where('status', 'paid')->sum('amount');
                                $paidInstallments = $ancestor->payments->where('status', 'paid')->count();
                                $progressPercentage =
                                    $ancestor->amount > 0 ? ($totalPaid / $ancestor->amount) * 100 : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-2" style="background-color: var(--app-info, #007bff)">
                                            <span class="avatar-content">#</span>
                                        </div>
                                        <div>
                                            #{{ $ancestor->id }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <strong>{{ trim(($ancestor->employee->first_name ?? '') . ' ' . ($ancestor->employee->middle_name ?? '')) ?: '--' }}</strong>
                                        <small class="stk-help">#{{ $ancestor->employee->id ?? '--' }}</small>
                                    </div>
                                </td>
                                @php
                                    $currency = $account_setting->currency ?? 'SAR';
                                    $currencySymbol =
                                        $currency == 'SAR' || empty($currency)
                                            ? '<img src="' .
                                                asset('assets/images/Saudi_Riyal.svg') .
                                                '" alt="ريال سعودي" width="15" style="vertical-align: middle;">'
                                            : $currency;
                                @endphp

                                <!-- عمود الأقساط المدفوعة -->
                                <td>
                                    <div class="mb-2">
                                        <label class="text-muted">حالة السداد:</label>
                                        <div class="mt-1">
                                            <div style="width: fit-content;">
                                                <div style="font-weight: bold; margin-bottom: 4px; position: relative;">
                                                    <div
                                                        style="border-bottom: 2px solid var(--app-warning, #ffc107); width: {{ $progressPercentage }}%; position: absolute; bottom: -2px;">
                                                    </div>
                                                    <div style="border-bottom: 1px solid var(--app-border, #dee2e6); width: fit-content;">
                                                        {{ \App\Support\CurrencySettings::format($ancestor->amount) }} {!! $currencySymbol !!}
                                                        (إجمالي السلفة)
                                                    </div>
                                                </div>
                                                <div style="color: #666; font-size: 0.9em;">
                                                    <span>{{ \App\Support\CurrencySettings::format($totalPaid) }} {!! $currencySymbol !!} مدفوعة
                                                        ({{ $paidInstallments }} قسط)</span>
                                                    <span class="mx-2">|</span>
                                                    <span>{{ \App\Support\CurrencySettings::format($ancestor->amount - $totalPaid) }}
                                                        {!! $currencySymbol !!} متبقي</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $accountBalance = $ancestor->employee ? $ancestor->employee->getAdvanceAccountBalance() : 0;
                                        $hasAccount = $ancestor->employee && $ancestor->employee->employee_advance_account_id;
                                    @endphp
                                    @if($hasAccount)
                                        <strong class="{{ $accountBalance > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($accountBalance, 2) }} {!! $currencySymbol !!}
                                        </strong>
                                        <small class="d-block text-muted">{{ optional($ancestor->employee->advanceAccount)->name ?: '—' }}</small>
                                    @else
                                        <span class="badge bg-warning"><i class="feather icon-alert-triangle"></i> لا يوجد حساب سلف</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($ancestor->next_payment)
                                        {{ $ancestor->next_payment->due_date }}
                                        <span class="badge badge-warning">قادم</span>
                                    @else
                                        @if ($ancestor->payments->where('status', 'unpaid')->isEmpty())
                                            <span class="badge badge-success">مكتمل</span>
                                        @else
                                            <span class="badge badge-danger">متأخر</span>
                                        @endif
                                    @endif
                                </td>


                                <td>{{ $ancestor->tags ?? '' }}</td>
                                <td>
                                    <div class="btn-group">
                                        <div class="dropdown">
                                            <button class="btn bg-gradient-info feather icon-more-vertical mr-1 mb-1 btn-sm"
                                                type="button" id="dropdownMenuButton{{ $ancestor->id }}"
                                                data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right"
                                                aria-labelledby="dropdownMenuButton{{ $ancestor->id }}">
                                                <li>
                                                    @permission('loans.show')
                                                        <a class="dropdown-item"
                                                            href="{{ route('ancestor.show', $ancestor->id) }}">
                                                            <i class="feather icon-eye me-2 text-primary"></i>عرض
                                                        </a>
                                                    @endpermission
                                                </li>
                                                <li>
                                                    @permission('loans.edit')
                                                        <a class="dropdown-item"
                                                            href="{{ route('ancestor.edit', $ancestor->id) }}">
                                                            <i class="feather icon-edit-2 me-2 text-success"></i>تعديل
                                                        </a>
                                                    @endpermission
                                                </li>
                                                @permission('loans.destroy')
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#"
                                                        data-toggle="modal"
                                                        data-target="#delete-modal-{{ $ancestor->id }}">
                                                        <i class="feather icon-trash-2 me-2"></i>حذف
                                                    </a>
                                                </li>
                                                @endpermission
                                                <li>
                                                    <a class="dropdown-item text-success" href="#"
                                                        data-toggle="modal"
                                                        data-target="#copy-modal-{{ $ancestor->id }}">
                                                        <i class="feather icon-copy me-2"></i>نسخ
                                                    </a>
                                                </li>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Delete -->
                                    <div class="modal fade" id="delete-modal-{{ $ancestor->id }}" tabindex="-1"
                                        role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">تأكيد الحذف</h5>
                                                    <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>هل أنت متأكد من حذف هذه السلفة؟</p>
                                                </div>
                                                <div class="modal-footer">
                                                    @permission('loans.destroy')
                                                        <form action="{{ route('ancestor.destroy', $ancestor->id) }}"
                                                            method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="button" class="btn btn-secondary"
                                                                data-dismiss="modal">إلغاء</button>
                                                            <button type="submit" class="btn btn-danger">حذف</button>
                                                        </form>
                                                    @endpermission
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Copy -->
                                    <div class="modal fade" id="copy-modal-{{ $ancestor->id }}" tabindex="-1"
                                        role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">نسخ السلفة</h5>
                                                    <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>هل تريد نسخ هذه السلفة؟</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-dismiss="modal">إلغاء</button>
                                                    <a href="" class="btn btn-success">نسخ</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">لا توجد سلف</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

               

            </div>
             <div class="pagination-wrapper d-flex justify-content-between align-items-center mt-3 gap-3">
                    <div class="pagination-info" style="margin-left: auto;">
                        صفحة {{ $ancestors->currentPage() }} من {{ $ancestors->lastPage() }}
                        (عرض {{ $ancestors->firstItem() }} إلى {{ $ancestors->lastItem() }} من {{ $ancestors->total() }} سجل)
                    </div>
                    <nav aria-label="Page navigation" style="margin-right: auto;">
                        <ul class="pagination mb-0">
                            @if ($ancestors->onFirstPage())
                                <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $ancestors->previousPageUrl() }}">&laquo;</a></li>
                            @endif
                            @for ($i = 1; $i <= $ancestors->lastPage(); $i++)
                                <li class="page-item {{ $ancestors->currentPage() == $i ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $ancestors->url($i) }}">{{ $i }}</a>
                                </li>
                            @endfor
                            @if ($ancestors->hasMorePages())
                                <li class="page-item"><a class="page-link" href="{{ $ancestors->nextPageUrl() }}">&raquo;</a></li>
                            @else
                                <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                            @endif
                        </ul>
                    </nav>
                </div>
        </div>




    @endsection
