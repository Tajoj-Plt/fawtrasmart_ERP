@extends('account::master')

@section('title', 'تعيين خزينة لموظف')

@section('content')
    {{--
        كانت الشاشة تفتح `<div class="content-body">` ثم تغلقه فوراً بسطرٍ
        تالٍ، فتقع البطاقةُ خارج جسم المحتوى وتَخرج عن هوامش بقيّة الشاشات.

        وكان زرّ الحفظ مرسوماً مرّتين — في ترويسة الصفحة وفي تذييل النموذج —
        فيسأل المستخدمَ أيّهما الحقيقي. بقي زرّ الترويسة وحده كما في بقيّة
        شاشات الإدخال.

        و`stk-req` صنفٌ يُلحِق النجمة بالتسمية من CSS، وكان مكتوباً على
        `<span>` يحمل نجمةً نصّية أيضاً — فتظهر نجمتان.
    --}}
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('finance_settings.treasury_employee') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="treasury_employee_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ
            </button>
        @endsection

        @include('account::components.page-head', [
            'title' => 'تعيين خزينة لموظف',
            'icon' => 'users',
            'sub' => 'الخزينة التي تُفتح افتراضياً في سندات هذا الموظف',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'خزائن الموظفين' => route('finance_settings.treasury_employee'),
                'تعيين جديد' => null,
            ],
        ])

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-user-check"></i> الربط
                </h6>
            </div>

            <form id="treasury_employee_form" action="{{ route('treasury_employee.store') }}" method="POST">
                @csrf

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="employee_id" class="stk-req">الموظف</label>
                            <select class="form-control select2" name="employee_id" id="employee_id" required>
                                <option value="">اختر الموظف</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($employees ?? [])"
                                route="employee.create"
                                label="موظفاً"
                                hint="الموظفون يُدارون في وحدة الموارد البشرية، ولا موظف مسجَّل بعد." />
                            @error('employee_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group col-md-6">
                            <label for="treasury_id" class="stk-req">الخزينة الافتراضية</label>
                            <select class="form-control select2" name="treasury_id" id="treasury_id" required>
                                <option value="">اختر الخزينة</option>
                                @foreach ($treasuries as $treasury)
                                    <option value="{{ $treasury->id }}" @selected(old('treasury_id') == $treasury->id)>
                                        {{ $treasury->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($treasuries ?? [])"
                                route="treasury.create"
                                permission="account.treasury.create"
                                :plan="\App\Constants\planFeatures\AccountPlanFeatures::TREASURY"
                                label="خزينة"
                                hint="لا خزائن مسجَّلة بعد — والعملية تحتاج خزينةً تُقيَّد عليها." />
                            @error('treasury_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
