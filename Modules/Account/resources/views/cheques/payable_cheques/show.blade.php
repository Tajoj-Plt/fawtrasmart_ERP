@extends('account::master')

@section('title')
عرض الشيك الصادر
@stop

@php
    use App\Enums\ChequeStatus;
    $status = $payable_cheque->status ?? ChequeStatus::ISSUED;
    $statusName = $status instanceof ChequeStatus ? $status->getName() : 'صادر';
    $badgeClass = $status instanceof ChequeStatus ? $status->getBadgeClass() : 'bg-secondary';
    $canCash = $status instanceof ChequeStatus && $status === ChequeStatus::ISSUED;
    $canReject = $status instanceof ChequeStatus && $status === ChequeStatus::ISSUED;
    $canCancel = $status instanceof ChequeStatus && $status === ChequeStatus::ISSUED;
    $canEdit = $status instanceof ChequeStatus && $status === ChequeStatus::ISSUED;
@endphp

@section('content')
    @include('account::components.page-head', [
        'title' => 'شيك # '.$payable_cheque->cheque_number,
        'crumbs' => [
            'الرئيسيه' => null,
            'الشيكات الصادرة' => route('payable_cheques.index'),
            'عرض' => null,
            $statusName => null,
        ],
    ])

    <div class="card">
        <div class="card-title p-2">
            @if($canEdit)
                <a href="{{ route('payable_cheques.edit', $payable_cheque->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-edit"></i> تعديل
                </a>
            @endif
            
            @if($canCash)
                <form action="{{ route('payable_cheques.cash', $payable_cheque->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('هل تريد تأكيد صرف الشيك؟');">
                    @csrf
                    <button type="submit" class="btn btn-outline-success btn-sm">
                        <i class="fa fa-check"></i> صرف
                    </button>
                </form>
            @endif
            
            @if($canReject)
                <form action="{{ route('payable_cheques.reject', $payable_cheque->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('هل تريد رفض الشيك؟');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="fa fa-ban"></i> رفض
                    </button>
                </form>
            @endif
            
            @if($canCancel)
                <form action="{{ route('payable_cheques.cancel', $payable_cheque->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('هل تريد إلغاء الشيك؟');">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning btn-sm">
                        <i class="fa fa-times"></i> إلغاء
                    </button>
                </form>
            @endif
            
            @if($payable_cheque->journalEntry)
                <a href="{{ route('journal.show', $payable_cheque->journal_entry_id) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-book"></i> عرض قيد الإصدار
                </a>
            @endif
            
            @if($payable_cheque->cashJournalEntry)
                <a href="{{ route('journal.show', $payable_cheque->cash_journal_entry_id) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-book"></i> عرض قيد الصرف
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
                                                    <p><small>المبلغ</small>: </p><strong>{{ number_format($payable_cheque->amount, 2) }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>الحالة</small>: </p>
                                                    <span class="badge {{ $badgeClass }}">{{ $statusName }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>تاريخ الإصدار </small>: </p><strong>{{ $payable_cheque->issue_date }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>تاريخ الاستحقاق </small>: </p><strong>{{ $payable_cheque->due_date }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>رقم دفتر الشيكات </small>: </p><strong>{{ $payable_cheque->cheque_book->cheque_book_number ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>اسم البنك </small>: </p><strong>{{ $payable_cheque->bank->name ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>رقم الشيك </small>: </p><strong>{{ $payable_cheque->cheque_number }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>المستفيد (المورد) </small>: </p><strong>{{ $payable_cheque->supplier->name ?? $payable_cheque->payee_name ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>الاسم على الشيك </small>: </p><strong>{{ $payable_cheque->payee_name }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>الوصف </small>: </p><strong>{{ $payable_cheque->description ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <p><small>حساب المستفيد </small>: </p><strong>{{ $payable_cheque->recipientAccount->name ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>المرفق </small>:</p>
                                                    @if ($payable_cheque->attachment !== null)
                                                        <a href="{{ asset('uploads/' . $payable_cheque->attachment) }}" target="_blank">
                                                            <strong>{{ $payable_cheque->attachment }}</strong>
                                                        </a>
                                                    @else
                                                        --
                                                    @endif
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    
                                    @if($status === ChequeStatus::CASHED)
                                    <table class="table mt-3">
                                        <thead style="background: #d4edda">
                                            <tr>
                                                <th colspan="2">معلومات الصرف</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <p><small>تاريخ الصرف </small>: </p><strong>{{ $payable_cheque->cashed_at ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>حساب البنك </small>: </p><strong>{{ $payable_cheque->bank->name ?? '-' }}</strong>
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
                                                    <p><small>تاريخ الرفض </small>: </p><strong>{{ $payable_cheque->rejected_at ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>سبب الرفض </small>: </p><strong>{{ $payable_cheque->rejection_reason ?? '-' }}</strong>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    @endif
                                    
                                    @if($status === ChequeStatus::CANCELLED)
                                    <table class="table mt-3">
                                        <thead style="background: #fff3cd">
                                            <tr>
                                                <th colspan="2">معلومات الإلغاء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <p><small>تاريخ الإلغاء </small>: </p><strong>{{ $payable_cheque->cancelled_at ?? '-' }}</strong>
                                                </td>
                                                <td>
                                                    <p><small>سبب الإلغاء </small>: </p><strong>{{ $payable_cheque->cancellation_reason ?? '-' }}</strong>
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
                            @if($payable_cheque->journalEntry)
                            <h5 class="mb-3">قيد إصدار الشيك</h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>الحساب</th>
                                        <th>مدين</th>
                                        <th>دائن</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payable_cheque->journalEntry->details as $detail)
                                    <tr>
                                        <td>{{ $detail->account->name ?? '-' }}</td>
                                        <td>{{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}</td>
                                        <td>{{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @endif
                            
                            @if($payable_cheque->cashJournalEntry)
                            <h5 class="mb-3 mt-4">قيد صرف/عملية الشيك</h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>الحساب</th>
                                        <th>مدين</th>
                                        <th>دائن</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payable_cheque->cashJournalEntry->details as $detail)
                                    <tr>
                                        <td>{{ $detail->account->name ?? '-' }}</td>
                                        <td>{{ $detail->debit > 0 ? number_format($detail->debit, 2) : '-' }}</td>
                                        <td>{{ $detail->credit > 0 ? number_format($detail->credit, 2) : '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @endif
                            
                            @if(!$payable_cheque->journalEntry && !$payable_cheque->cashJournalEntry)
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
                                                        <span class="text-dark">قام بإصدار الشيك #{{ $payable_cheque->cheque_number }}</span>
                                                    </div>
                                                    <div class="d-flex align-items-center mt-2">
                                                        <small class="text-muted me-3">
                                                            <i class="fas fa-building me-1"></i>
                                                            {{ auth()->user()->branch->name ?? 'الفرع الرئيسي' }}
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="far fa-clock me-1"></i>
                                                            {{ $payable_cheque->created_at->format('H:i:s') }}
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

@endsection
