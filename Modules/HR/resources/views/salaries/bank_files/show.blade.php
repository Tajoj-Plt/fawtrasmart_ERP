@extends('hr::master')

@section('title', 'ملف التحويل البنكي')

@section('page-actions')
    @permission('bank_files.download')
        <a href="{{ route('hr.bank-files.download', $file->id) }}" class="btn btn-primary">
            <i class="fa fa-download"></i> تحميل الملف
        </a>
    @endpermission
@endsection

@section('content')
    @include('stock::components.page-head', [
        'title' => 'ملف التحويل البنكي',
        'icon' => 'credit-card',
        'crumbs' => [
            'الملف البنكي' => route('hr.bank-files.index'),
            $file->filename => null,
        ],
    ])

    <div class="content-body">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div>{{ $file->period_from?->toDateString() }} → {{ $file->period_to?->toDateString() }}</div>
                    <small class="stk-help">صيغة {{ $file->formatLabel() }} · فيه {{ $file->row_count }} موظف · ما دخل {{ $file->skipped_count }} · المجموع {{ \App\Support\CurrencySettings::format($file->total_amount) }}</small>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">الموظفين اللي في الملف</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>الآيبان</th>
                                <th>البنك</th>
                                <th>المبلغ اللي بيتحول</th>
                                <th>فترة الراتب</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($file->includedRows() as $row)
                                <tr>
                                    <td>{{ $row['employee_name'] ?? '' }}</td>
                                    <td dir="ltr">{{ $row['iban'] ?? '' }}</td>
                                    <td>{{ $row['bank_name'] ?? '' }}</td>
                                    <td>{{ \App\Support\CurrencySettings::format($row['amount'] ?? 0) }}</td>
                                    <td>{{ ($row['period_from'] ?? '') }} → {{ ($row['period_to'] ?? '') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">لا صفوف</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($file->skippedRows() !== [])
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">ما دخلوا الملف — صلّحهم وولّد الملف من جديد</h6>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>ليش ما دخل؟</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($file->skippedRows() as $row)
                                <tr>
                                    <td>{{ $row['employee_name'] ?? '' }}</td>
                                    <td>{{ $row['reason'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
