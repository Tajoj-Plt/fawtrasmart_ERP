@extends('sitting::master')

@section('title', 'صلاحية جديدة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('settings.permissions.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="permission_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ الصلاحية
            </button>
        @endsection

        @include('sitting::components.page-head', [
            'title' => 'صلاحية جديدة',
            'icon' => 'key',
            'sub' => 'إضافة صلاحيةٍ يدوياً — أغلب الصلاحيات تُزرع من كتالوجات الوحدات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات' => route('SittingAccount.dashboard'),
                'الصلاحيات' => route('settings.permissions.index'),
                'جديدة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger" style="border-radius:10px">
                <strong>تعذّر الحفظ</strong>
                <ul class="mb-0 mt-1" style="padding-inline-start:1.2rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="permission_form" method="POST" action="{{ route('settings.permissions.store') }}">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-key"></i> بيانات الصلاحية</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="name" class="stk-req">اسم الصلاحية</label>
                            <input type="text" id="name" name="name" class="form-control stk-num"
                                value="{{ old('name') }}" required maxlength="190"
                                placeholder="module.feature.action">
                            <small class="stk-help">
                                النمط المعتمد نقطي: <span class="stk-num">settings.users.view</span>.
                            </small>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="guard_display">الحارس (guard)</label>
                            <input type="text" id="guard_display" class="form-control stk-num" value="web" readonly>
                            <small class="stk-help">ثابتٌ على "web" — يُطابق حارس المستخدمين في هذا النظام.</small>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="feather icon-info"></i>
                        الصلاحية الجديدة لا تمنح شيئاً حتى تُسنَد إلى دور، ولا يعرفها الكود ما لم
                        يُحرَس بها مسارٌ أو زر. وهي تظهر في شاشة الأدوار تحت «صلاحيات غير مصنَّفة»
                        حتى تُضاف إلى كتالوج قسمها في <span class="stk-num">app/Constants</span>
                        ويُشغَّل <span class="stk-num">permissions:sync-catalog</span>.
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
