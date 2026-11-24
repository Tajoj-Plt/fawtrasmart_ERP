@extends('hr::master')

@section('title', 'وثائق ' . $employee->full_name)

@section('page-actions')
    @permission('employee_documents.create')
        <a href="#addDocument" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة وثيقة
        </a>
    @endpermission
@endsection

@section('content')
    @include('stock::components.page-head', [
        'title' => 'وثائق ' . $employee->full_name,
        'icon' => 'file-text',
        'sub' => 'الهويات والإقامات والرخص والشهادات — بأرقامها وتواريخ انتهائها',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الموظفون' => route('employee.index'),
            $employee->full_name => route('employee.show', $employee->id),
            'الوثائق' => null,
        ],
    ])

    <div class="content-body">

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-file-text"></i> الوثائق المسجَّلة
                </h6>
                <span class="stk-count">{{ $documents->count() }}</span>
            </div>

            <div class="card-body">
                @if ($documents->isEmpty())
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-file-text"></i></div>
                        <h5 class="stk-empty__title">لا وثائق مسجَّلة</h5>
                        <p class="stk-empty__hint">
                            سجّل الهوية أو الإقامة بتاريخ انتهائها، فيُنبَّه عليها قبل أن تنتهي
                        </p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>الوثيقة</th>
                                    <th>الرقم</th>
                                    <th>الجهة المُصدِرة</th>
                                    <th>الإصدار</th>
                                    <th>الانتهاء</th>
                                    <th>الحالة</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($documents as $document)
                                    <tr>
                                        <td>{{ $document->displayTitle() }}</td>
                                        <td>
                                            @if ($document->number)
                                                <code>{{ $document->number }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $document->issuing_authority ?: '—' }}</td>
                                        <td>{{ optional($document->issue_date)->format('Y-m-d') ?: '—' }}</td>
                                        <td>{{ optional($document->expiry_date)->format('Y-m-d') ?: '—' }}</td>
                                        <td>
                                            {{--
                                                ثلاثُ حالات لا اثنتان: ما لا ينتهي أصلاً غيرُ
                                                السارية غيرُ المنتهية — وجمعُها يُخفي ما يجب أن يُرى
                                            --}}
                                            @php $days = $document->daysUntilExpiry(); @endphp
                                            @if ($days === null)
                                                <span class="badge badge-secondary">لا تنتهي</span>
                                            @elseif ($days < 0)
                                                <span class="badge badge-danger">
                                                    منتهية منذ {{ abs($days) }} يوماً
                                                </span>
                                            @elseif ($days <= 30)
                                                <span class="badge badge-warning">
                                                    تنتهي خلال {{ $days }} يوماً
                                                </span>
                                            @else
                                                <span class="badge badge-success">سارية</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $canDownloadDoc = $document->file_path && hasPermission('employee_documents.index');
                                                $canManageDoc = hasPermission('employee_documents.destroy');
                                            @endphp
                                            @if ($canDownloadDoc || $canManageDoc)
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                        <i class="fa fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-end">
                                                        @if ($canDownloadDoc)
                                                            <a class="dropdown-item" href="{{ route('employee.documents.download', [$employee->id, $document->id]) }}">
                                                                <i class="fa fa-download me-2 text-primary"></i>تنزيل الملف
                                                            </a>
                                                        @endif
                                                        @if ($canManageDoc)
                                                            @if ($canDownloadDoc)<div class="dropdown-divider"></div>@endif
                                                            <form action="{{ route('employee.documents.destroy', [$employee->id, $document->id]) }}" method="POST" class="m-0"
                                                                onsubmit="return confirm('حذف هذه الوثيقة؟');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger"><i class="fa fa-trash me-2"></i>حذف</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @permission('employee_documents.create')
            <div class="card" id="addDocument">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-plus-circle"></i> إضافة وثيقة
                    </h6>
                </div>

                <div class="card-body">
                    <form action="{{ route('employee.documents.store', $employee->id) }}"
                        method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="type" class="stk-req">نوع الوثيقة</label>
                                <select name="type" id="type" class="form-control" required>
                                    @foreach ($types as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="title">تسمية إضافية</label>
                                <input type="text" name="title" id="title" class="form-control"
                                    value="{{ old('title') }}" placeholder="لنوع «أخرى» أو لتمييز وثيقتين">
                            </div>

                            <div class="form-group col-md-4">
                                <label for="number">رقم الوثيقة</label>
                                <input type="text" name="number" id="number" class="form-control"
                                    value="{{ old('number') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="issuing_authority">الجهة المُصدِرة</label>
                                <input type="text" name="issuing_authority" id="issuing_authority"
                                    class="form-control" value="{{ old('issuing_authority') }}">
                            </div>

                            <div class="form-group col-md-4">
                                <label for="issue_date">تاريخ الإصدار</label>
                                <input type="date" name="issue_date" id="issue_date" class="form-control"
                                    value="{{ old('issue_date') }}">
                            </div>

                            <div class="form-group col-md-4">
                                <label for="expiry_date">تاريخ الانتهاء</label>
                                <input type="date" name="expiry_date" id="expiry_date" class="form-control"
                                    value="{{ old('expiry_date') }}">
                                <small class="text-muted">يُترك فارغاً لما لا ينتهي — كالشهادات</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="file">صورة الوثيقة</label>
                                <input type="file" name="file" id="file" class="form-control"
                                    accept="image/*,application/pdf">
                                <small class="text-muted">PDF أو صورة، حتى 5 ميغابايت</small>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="notes">ملاحظات</label>
                                <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> حفظ الوثيقة
                        </button>
                    </form>
                </div>
            </div>
        @endpermission
    </div>

    @include('hr::components.table-menu')
@stop
