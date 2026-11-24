@extends('account::master')

@section('title')
عرض الشيك المستلم
@stop

@php
    use App\Enums\ChequeStatus;
    $status = $received_cheque->status ?? ChequeStatus::PENDING;
    $statusName = $status instanceof ChequeStatus ? $status->getName() : 'معلق';
    $badgeClass = $status instanceof ChequeStatus ? $status->getBadgeClass() : 'bg-secondary';
    $canDeposit = $status instanceof ChequeStatus && $status === ChequeStatus::PENDING;
    $canCollect = $status instanceof ChequeStatus && $status === ChequeStatus::UNDER_COLLECTION;
    $canReject = $status instanceof ChequeStatus && in_array($status, [ChequeStatus::PENDING, ChequeStatus::UNDER_COLLECTION]);
    $canEndorse = $status instanceof ChequeStatus && $status === ChequeStatus::PENDING;
    $canEdit = $status instanceof ChequeStatus && $status === ChequeStatus::PENDING;
@endphp

@section('content')
    @include('account::components.page-head', [
        'title' => 'شيك # '.$received_cheque->cheque_number,
        'crumbs' => [
            'الرئيسيه' => null,
            'الشيكات المستلمة' => route('received_cheques.index'),
            'عرض' => null,
            $statusName => null,
        ],
    ])

    <div class="card">
        <div class="card-title p-2">
            @if($canEdit)
                <a href="{{ route('received_cheques.edit', $received_cheque->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-edit"></i> تعديل
                </a>
            @endif
            
            @if($canDeposit)
                <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#depositModal">
                    <i class="fa fa-university"></i> إيداع للتحصيل
                </button>
            @endif
            
            @if($canCollect)
                <form action="{{ route('received_cheques.collect', $received_cheque->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('هل تريد تأكيد تحصيل الشيك؟');">
                    @csrf
                    <button type="submit" class="btn btn-outline-success btn-sm">
                        <i class="fa fa-check"></i> تحصيل
                    </button>
                </form>
            @endif
            
            @if($canReject)
                <form action="{{ route('received_cheques.reject', $received_cheque->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('هل تريد رفض الشيك؟');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="fa fa-ban"></i> رفض
                    </button>
                </form>
            @endif
            
            @if($canEndorse)
                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#endorseModal">
                    <i class="fa fa-exchange-alt"></i> تظهير
                </button>
            @endif
            
            @php
                $allEntries = $received_cheque->allJournalEntries();
            @endphp
            @if($allEntries->count() > 0)
                <a href="#journal" class="btn btn-outline-secondary btn-sm" onclick="$('#journal-tab').click();">
                    <i class="fa fa-book"></i> عرض القيود ({{ $allEntries->count() }})
                </a>
            @endif
        </div>
        
        <div class="card-body">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="details-tab" data-bs-toggle="tab" href="#details" role="tab"
                        aria-controls="details" aria-selected="true">تفاصيل </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="journal-tab" data-bs-toggle="tab" href="#journal" role="tab"
                        aria-controls="journal" aria-selected="false">القيود المحاسبية</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="activity-log-tab" data-bs-toggle="tab" href="#activity-log" role="tab"
                        aria-controls="activity-log" aria-selected="false">سجل النشاطات</a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- تفاصيل  -->
                <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                    <div class="tab-content">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <table class="table">
                                        <thead style="background: #f8f8f8">
                                            <tr>
                                                <th style="width: 50%">معلومات الشيك</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <p><small>المبلغ</small>: </p><strong>{{ number_format($received_cheque->amount, 2) }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>الحالة</small>: </p>
                                                    <span class="badge {{ $badgeClass }}">{{ $statusName }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>تاريخ الإصدار </small>: </p><strong>{{ $received_cheque->issue_date }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>تاريخ الاستحقاق </small>: </p><strong>{{ $received_cheque->due_date }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>رقم الشيك </small>: </p><strong>{{ $received_cheque->cheque_number }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>العميل </small>: </p><strong>{{ $received_cheque->client->name ?? $received_cheque->payee_name ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>بنك الساحب </small>: </p><strong>{{ $received_cheque->drawer_bank ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>فرع الساحب </small>: </p><strong>{{ $received_cheque->drawer_branch ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>حساب شيكات تحت التحصيل </small>: </p><strong>{{ $received_cheque->collectionAccount->name ?? 'شيكات تحت التحصيل' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>حساب البنك (للإيداع) </small>: </p><strong>{{ $received_cheque->bankAccount->name ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>الوصف </small>: </p><strong>{{ $received_cheque->description ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>المرفق </small>:</p>
                                                    @if ($received_cheque->attachment !== null)
                                                        <a href="{{ asset('uploads/' . $received_cheque->attachment) }}" target="_blank">
                                                            <strong>{{ $received_cheque->attachment }}</strong>
                                                        </a>
                                                    @else
                                                        --
                                                    @endif
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    
                                    @if($status === ChequeStatus::ENDORSED && $received_cheque->endorsedToAccount)
                                    <table class="table mt-3">
                                        <thead style="background: #fff3cd">
                                            <tr>
                                                <th colspan="2">معلومات التظهير</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <p><small>مظهر إلى </small>: </p><strong>{{ $received_cheque->endorsedToAccount->name ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>تاريخ التظهير </small>: </p><strong>{{ $received_cheque->endorsed_at ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    @endif
                                    
                                    @if($status === ChequeStatus::COLLECTED)
                                    <table class="table mt-3">
                                        <thead style="background: #d4edda">
                                            <tr>
                                                <th colspan="2">معلومات التحصيل</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <p><small>تاريخ التحصيل </small>: </p><strong>{{ $received_cheque->collected_at ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>حساب البنك </small>: </p><strong>{{ $received_cheque->bankAccount->name ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    @endif
                                    
                                    @if($status === ChequeStatus::REJECTED)
                                    <table class="table mt-3">
                                        <thead style="background: #f8d7da">
                                            <tr>
                                                <th colspan="2">معلومات الرفض</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <p><small>تاريخ الرفض </small>: </p><strong>{{ $received_cheque->rejected_at ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>سبب الرفض </small>: </p><strong>{{ $received_cheque->rejection_reason ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- القيود المحاسبية -->
                <div class="tab-pane fade" id="journal" role="tabpanel" aria-labelledby="journal-tab">
                    <div class="card">
                        <div class="card-body">
                            @php
                                $allJournalEntries = $received_cheque->allJournalEntries();
                                $entryTypeNames = [
                                    \App\Enums\ReferenceType::RECEIVED_CHEQUE->value => 'قيد استلام الشيك',
                                    \App\Enums\ReferenceType::RECEIVED_CHEQUE_COLLECT->value => 'قيد تحصيل الشيك',
                                    \App\Enums\ReferenceType::RECEIVED_CHEQUE_ENDORSE->value => 'قيد تظهير الشيك',
                                    \App\Enums\ReferenceType::RECEIVED_CHEQUE_REJECT->value => 'قيد رفض الشيك',
                                ];
                                $entryTypeColors = [
                                    \App\Enums\ReferenceType::RECEIVED_CHEQUE->value => 'primary',
                                    \App\Enums\ReferenceType::RECEIVED_CHEQUE_COLLECT->value => 'success',
                                    \App\Enums\ReferenceType::RECEIVED_CHEQUE_ENDORSE->value => 'warning',
                                    \App\Enums\ReferenceType::RECEIVED_CHEQUE_REJECT->value => 'danger',
                                ];
                            @endphp
                            
                            @if($allJournalEntries->count() > 0)
                                @foreach($allJournalEntries as $entry)
                                    @php
                                        $entryTypeName = $entryTypeNames[$entry->reference_type->value ?? ''] ?? 'قيد محاسبي';
                                        $entryColor = $entryTypeColors[$entry->reference_type->value ?? ''] ?? 'secondary';
                                    @endphp
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="mb-0">
                                                <span class="badge bg-{{ $entryColor }}">{{ $entryTypeName }}</span>
                                                <small class="text-muted ms-2">#{{ $entry->reference_number }} - {{ $entry->date?->format('Y-m-d') }}</small>
                                            </h5>
                                            <a href="{{ route('journal.show', $entry->id) }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="fa fa-eye"></i> عرض القيد
                                            </a>
                                        </div>
                                        <p class="text-muted small mb-2">{{ $entry->description }}</p>
                                        <table class="table table-bordered table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>الحساب</th>
                                                    <th class="text-center" style="width: 150px;">مدين</th>
                                                    <th class="text-center" style="width: 150px;">دائن</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($entry->details as $detail)
                                                <tr>
                                                    <td>{{ $detail->account->name ?? '-' }}</td>
                                                    <td class="text-center">{{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}</td>
                                                    <td class="text-center">{{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <th>المجموع</th>
                                                    <th class="text-center">{{ number_format($entry->details->sum('debit'), 2) }}</th>
                                                    <th class="text-center">{{ number_format($entry->details->sum('credit'), 2) }}</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    @if(!$loop->last)
                                        <hr>
                                    @endif
                                @endforeach
                            @else
                                <div class="alert alert-info">لا توجد قيود محاسبية مرتبطة بهذا الشيك</div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- سجل النشاطات -->
                <div class="tab-pane fade" id="activity-log" role="tabpanel" aria-labelledby="activity-log-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-3 w-100">

                            <!-- أزرار للأعلى وللأسفل -->
                            <div class="btn-group">
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>

                            <!-- حقل اختيار الإجراءات -->
                            <div class="input-group flex-grow-1">
                                <select class="form-control form-control-sm select2">
                                    <option value="">كل الاجراءات</option>
                                    <option value="1">الكل</option>
                                </select>
                            </div>

                            <!-- حقل اختيار الفاعلين -->
                            <div class="input-group flex-grow-1">
                                <select class="form-control form-control-sm select2">
                                    <option value="">كل الفاعلين</option>
                                    <option value="1">الكل</option>
                                </select>
                            </div>

                            <!-- حقل الفترة -->
                            <div class="input-group flex-grow-1">
                                <input type="text" class="form-control form-control-sm"
                                    placeholder="الفترة من / إلى">
                            </div>

                            <!-- أزرار لليمين ولليسار -->
                            <div class="btn-group">
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                            </div>

                        </div>
                    </div>

                    <div class="position-relative">
                        <div class="position-absolute end-0 me-4">
                            <button class="btn btn-light btn-sm rounded-pill shadow-sm">اليوم</button>
                        </div>
                        <div class="car" style="background: #f8f9fa">
                            <div class="activity-list p-4">
                                <div class="card-content">
                                    <div class="card border-0">
                                        <ul class="activity-timeline timeline-left list-unstyled">
                                            <li class="d-flex position-relative mb-4">
                                                <div class="timeline-icon position-absolute"
                                                    style="left: -43px; top: 0;">
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                                        style="width: 35px; height: 35px;">
                                                        <i class="fas fa-plus text-white"></i>
                                                    </div>
                                                </div>
                                                <div class="timeline-info position-relative"
                                                    style="padding-left: 20px; border-left: 2px solid #e9ecef;">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <span class="badge bg-purple me-2">{{ auth()->user()->name ?? 'النظام' }}</span>
                                                        <span class="text-dark">قام بإضافة الشيك #{{ $received_cheque->cheque_number }}</span>
                                                    </div>
                                                    <div class="d-flex align-items-center mt-2">
                                                        <small class="text-muted me-3">
                                                            <i class="fas fa-building me-1"></i>
                                                            {{ auth()->user()->branch->name ?? 'الفرع الرئيسي' }}
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="far fa-clock me-1"></i>
                                                            {{ $received_cheque->created_at->format('H:i:s') }}
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="ms-auto">
                                                    <a href="#" class="text-muted">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    
    @if($canDeposit)
    <!-- Modal للإيداع -->
    <div class="modal fade" id="depositModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('received_cheques.deposit', $received_cheque->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">إيداع الشيك #{{ $received_cheque->cheque_number }} للتحصيل</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">سيتم نقل الشيك من "شيكات تحت التحصيل" إلى "شيكات برسم التحصيل" في البنك المختار</p>
                        <div class="mb-3">
                            <label class="form-label">مبلغ الشيك</label>
                            <input type="text" class="form-control" value="{{ number_format($received_cheque->amount, 2) }}" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">حساب البنك للإيداع <span class="text-danger">*</span></label>
                            <select name="bank_account_id" class="form-control select2" required>
                                <option value="">اختر البنك</option>
                                @if(isset($bank_accounts))
                                    @foreach($bank_accounts as $account)
                                        <option value="{{ $account->id }}"
                                            {{ $received_cheque->bank_account_id == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            @if ($received_cheque->bank_account_id)
                                <small class="form-text text-muted">مُحدَّد مسبقاً عند الاستلام — يمكن تغييره هنا</small>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-info">إيداع للتحصيل</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    
    @if($canEndorse)
    <!-- Modal للتظهير -->
    <div class="modal fade" id="endorseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('received_cheques.endorse', $received_cheque->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">تظهير الشيك #{{ $received_cheque->cheque_number }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">التظهير هو تحويل ملكية الشيك لطرف ثالث (مورد/دائن) لسداد مديونية له</p>
                        <div class="mb-3">
                            <label class="form-label">حساب المورد/الدائن (المظهر إليه) <span class="text-danger">*</span></label>
                            <select name="endorsed_to_account_id" class="form-control select2" required>
                                <option value="">اختر الحساب</option>
                                @if(isset($supplier_accounts))
                                    @foreach($supplier_accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning">تظهير</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    
@endsection
