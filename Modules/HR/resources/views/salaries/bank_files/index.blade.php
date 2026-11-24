@extends('hr::master')

@section('title', 'ملف الرواتب البنكي')

@section('page-actions')
    @permission('payroll_settings.manage')
        <a href="{{ route('hr.bank-files.settings') }}" class="btn btn-outline-secondary">
            <i class="fa fa-cog"></i> الإعدادات
        </a>
    @endpermission
@endsection

@section('content')
    @include('stock::components.page-head', [
        'title' => 'ملف الرواتب البنكي',
        'icon' => 'credit-card',
        'sub' => 'ملف فيه رواتب الموظفين وآيباناتهم، ترفعه لموقع البنك عشان يحوّل الرواتب لحساباتهم',
        'crumbs' => [
            'سجل الصرف' => route('payroll-runs.index'),
            'الملف البنكي' => null,
        ],
    ])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-payroll-bank-files.index',
            'title' => 'وش هو ملف الرواتب البنكي؟',
            'lead' => 'بدل ما تحوّل راتب كل موظف لحاله، النظام يطلع لك ملف واحد فيه كل الموظفين: اسمه، آيبانه، وكم راتبه. ترفعه في موقع البنك، والبنك يحوّل للكل مرة وحدة.',
            'blocks' => [
                'الخطوات' => [
                    '1) اصرف الرواتب في النظام أول (من المسير أو سجل الصرف).',
                    '2) هنا اختر الفترة (من – إلى) واضغط «توليد الملف».',
                    '3) حمّل الملف وارفعه في موقع البنك.',
                ],
                'انتبه' => [
                    'الملف ما يصرف فلوس ولا يسوي قيد — هو بس قائمة ترفعها للبنك. القيد ينزل وقت الصرف في النظام.',
                    'اللي ما عنده آيبان أو آيبانه غلط ما يدخل في الملف — تشوف أسماءهم تحت وتصلحهم من بطاقة الموظف.',
                    'لو ولّدت الملف مرتين، انتبه ترفع الصح للبنك عشان ما يتحول الراتب مرتين.',
                ],
            ],
            'facts' => [
                'الملف ما يصرف فلوس',
                'بدون آيبان صحيح = ما يدخل الملف',
            ],
        ])


    <div class="content-body">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- موظفون بلا آيبان صالح: يُعرَفون قبل التوليد لا بعده --}}
        @if (($ibanIssues['count'] ?? 0) > 0)
            <div class="alert alert-warning">
                <div class="d-flex align-items-center flex-wrap" style="gap:.4rem">
                    <i class="feather icon-alert-triangle"></i>
                    <strong>{{ $ibanIssues['count'] }} موظف ما بيدخلون الملف</strong>
                    <span>— لأن الآيبان حقهم ناقص أو غلط. صلّحه من بطاقة الموظف قبل ما تولّد الملف.</span>
                </div>
                <details class="mt-50">
                    <summary style="cursor:pointer">عرض الأسماء</summary>
                    <ul class="mb-0 mt-50">
                        @foreach ($ibanIssues['rows'] as $issue)
                            <li>
                                @if (hasPermission('employees.edit'))
                                    <a href="{{ route('employee.edit', $issue['id']) }}">{{ $issue['name'] }}</a>
                                @else
                                    {{ $issue['name'] }}
                                @endif
                                — <span class="text-muted">{{ $issue['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            </div>
        @endif

        @permission('bank_files.create')
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="stk-table-head__title mb-0">طلّع ملف جديد</h6>
                </div>
                <p class="stk-help">اختر الفترة: كل راتب انصرف فيها يدخل الملف بصافيه. الملف بيطلع بصيغة {{ $bankFileSettings['format'] === 'xlsx' ? 'Excel' : 'CSV' }} (تغيّرها من الإعدادات).</p>
                <form action="{{ route('hr.bank-files.store') }}" method="POST" class="row">
                    @csrf
                    <div class="form-group col-md-4">
                        <label>من تاريخ</label>
                        <input type="date" name="period_from" class="form-control" required
                            value="{{ old('period_from', now()->startOfMonth()->toDateString()) }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>إلى تاريخ</label>
                        <input type="date" name="period_to" class="form-control" required
                            value="{{ old('period_to', now()->endOfMonth()->toDateString()) }}">
                    </div>
                    <div class="form-group col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary"><i class="feather icon-file-plus"></i> توليد الملف</button>
                    </div>
                </form>
            </div>
        </div>
        @endpermission

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">الملفات اللي طلّعتها</h6>
            </div>
            <div class="card-body">
                @if ($files->isEmpty())
                    <div class="stk-empty">
                        <h5 class="stk-empty__title">ما طلّعت ولا ملف للحين</h5>
                        <p>اختر فترة فيها رواتب مصروفة واضغط «توليد الملف».</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الفترة</th>
                                    <th>الصيغة</th>
                                    <th>عدد الموظفين</th>
                                    <th>ما دخلوا <i class="feather icon-help-circle stk-tip" title="موظفين انصرف لهم راتب في الفترة بس ما دخلوا الملف — غالباً لأن الآيبان ناقص. تشوف السبب في تفاصيل الملف."></i></th>
                                    <th>مجموع الرواتب</th>
                                    <th>تاريخ الإنشاء</th>
                                    <th class="text-center" style="width:70px">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($files as $row)
                                    <tr>
                                        <td>{{ $row->period_from?->toDateString() }} → {{ $row->period_to?->toDateString() }}</td>
                                        <td>{{ $row->formatLabel() }}</td>
                                        <td>{{ $row->row_count }}</td>
                                        <td>{{ $row->skipped_count }}</td>
                                        <td>{{ \App\Support\CurrencySettings::format($row->total_amount) }}</td>
                                        <td>{{ optional($row->generated_at)->format('Y-m-d H:i') }}</td>
                                        <td class="text-center">
                                            @if (canAny(['bank_files.show', 'bank_files.download']))
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="dropdown" data-bs-display="static"
                                                    aria-expanded="false" aria-label="خيارات">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    @permission('bank_files.show')
                                                        <a class="dropdown-item" href="{{ route('hr.bank-files.show', $row->id) }}">
                                                            <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                        </a>
                                                    @endpermission
                                                    @permission('bank_files.download')
                                                        <a class="dropdown-item" href="{{ route('hr.bank-files.download', $row->id) }}">
                                                            <i class="fa fa-download me-2 text-info"></i>تحميل الملف
                                                        </a>
                                                    @endpermission
                                                </div>
                                            </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $files->links() }}
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('hr::components.table-menu')
@endpush
