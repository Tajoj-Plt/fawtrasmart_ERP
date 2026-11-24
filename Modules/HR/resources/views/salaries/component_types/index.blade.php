@extends('hr::master')

@section('title', 'كتالوج البدلات والاستقطاعات')

@section('content')
    @include('stock::components.page-head', [
        'title' => 'كتالوج البدلات والاستقطاعات',
        'icon' => 'layers',
        'sub' => 'أنواع معيارية لبنود الراتب — السكن والمواصلات وطبيعة العمل، أو نوع تضيفه',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'بنود الراتب' => route('SalaryItems.index'),
            'الكتالوج' => null,
        ],
    ])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-salary-component-types.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه أنواعُ بنود الراتب: التصنيفُ الذي تُبنى عليه البنودُ — أهي استحقاقٌ يزيد الراتبَ أم حسمٌ ينقصه.',
            'blocks' => [
                'موضعُها من البنود' => [
                    'كلُّ بندِ راتبٍ ينتمي إلى نوع، ومن النوع يُعرَف اتجاهُ أثره في الصافي.',
                    'فالنوعُ أصلٌ للبند لا العكس — وتغييرُ اتجاهِ نوعٍ يقلب أثرَ كلّ بندٍ تحته فيما يُحتسَب بعده.',
                ],
                'الحذف' => [
                    'حذفُ نوعٍ تحته بنودٌ يترك تلك البنودَ بلا تصنيف — فانقلْها قبل حذفه.',
                ],
            ],
            'facts' => [
                'النوعُ يقرّر اتجاهَ أثر البند في الصافي',
            ],
        ])


    <div class="content-body">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-layers"></i> الأنواع</h6>
                <div>
                    @if (hasPermission('salary_items.create'))
                        <a href="{{ route('SalaryComponentTypes.create') }}" class="btn btn-primary btn-sm">
                            <i class="feather icon-plus"></i> إضافة نوع
                        </a>
                    @endif
                    <a href="{{ route('SalaryItems.index') }}" class="btn btn-outline-secondary btn-sm">بنود الراتب</a>
                </div>
            </div>
            <div class="card-body">
                @if ($types->isEmpty())
                    <div class="stk-empty">
                        <h5 class="stk-empty__title">لا أنواع في الكتالوج</h5>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الرمز</th>
                                    <th>الاسم</th>
                                    <th>الاتجاه</th>
                                    <th>افتراضي</th>
                                    <th>خاضع للتأمينات</th>
                                    <th>البنود المرتبطة</th>
                                    <th>الحالة</th>
                                    <th style="width: 14%">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($types as $type)
                                    <tr>
                                        <td>
                                            <code>{{ $type->code }}</code>
                                            @if ($type->is_system)
                                                <span class="badge badge-light-secondary">معياري</span>
                                            @endif
                                        </td>
                                        <td><strong>{{ $type->name }}</strong></td>
                                        <td>
                                            @if ($type->kind === \App\Models\SalaryItem::TYPE_ADDITION)
                                                <span class="badge bg-success">مستحق</span>
                                            @else
                                                <span class="badge bg-danger">مستقطع</span>
                                            @endif
                                        </td>
                                        <td>{{ $type->is_default ? 'نعم' : '—' }}</td>
                                        <td>{{ $type->is_insurable ? 'نعم' : '—' }}</td>
                                        <td>{{ $type->items_count }}</td>
                                        <td>
                                            @if ($type->status === \App\Models\SalaryComponentType::STATUS_ACTIVE)
                                                <span class="badge bg-success">نشط</span>
                                            @else
                                                <span class="badge bg-secondary">متوقف</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (hasPermission('salary_items.edit'))
                                                <a href="{{ route('SalaryComponentTypes.edit', $type->id) }}" class="btn btn-sm btn-outline-primary">تعديل</a>
                                                <form action="{{ route('SalaryComponentTypes.toggleStatus', $type->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                        {{ $type->status === \App\Models\SalaryComponentType::STATUS_ACTIVE ? 'إيقاف' : 'تفعيل' }}
                                                    </button>
                                                </form>
                                                @if (! $type->is_system)
                                                    <form action="{{ route('SalaryComponentTypes.destroy', $type->id) }}" method="POST" class="d-inline" onsubmit="return confirm('حذف هذا النوع من الكتالوج؟')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="stk-help mb-0">
                        «خاضع للتأمينات» عَلَم تصنيف فقط — حساب التأمينات في جولة لاحقة.
                        الأنواع المعيارية لا تُحذف.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endsection
