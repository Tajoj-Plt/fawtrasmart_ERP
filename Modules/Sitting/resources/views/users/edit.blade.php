@extends('sitting::master')

@section('title', 'تعديل مستخدم')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('settings.users.show', $user->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="user_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ التغييرات
            </button>
        @endsection

        @include('sitting::components.page-head', [
            'title' => 'تعديل: '.$user->name,
            'icon' => 'user-check',
            'sub' => 'تعديل بيانات الدخول والدور والفرع',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المستخدمون' => route('settings.users.index'),
                $user->name => route('settings.users.show', $user->id),
                'تعديل' => null,
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

        <form id="user_form" method="POST" action="{{ route('settings.users.update', $user->id) }}">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-user"></i> بيانات الدخول</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="name" class="stk-req">الاسم</label>
                            <input type="text" id="name" name="name" class="form-control"
                                value="{{ old('name', $user->name) }}" required maxlength="255">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="email" class="stk-req">البريد الإلكتروني</label>
                            <input type="email" id="email" name="email" class="form-control"
                                value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="password">كلمة المرور</label>
                            <input type="password" id="password" name="password" class="form-control" minlength="6">
                            <small class="stk-help">اتركها فارغة للإبقاء على كلمة المرور الحالية.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="phone">رقم الهاتف</label>
                            <input type="text" id="phone" name="phone" class="form-control"
                                value="{{ old('phone', $user->phone) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-shield"></i> الدور والفرع</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="role_id" class="stk-req">الدور</label>
                            <select id="role_id" name="role_id" class="form-control select2" required>
                                <option value="" disabled>اختر الدور</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="stk-help">يحدّد صلاحيات المستخدم عبر صلاحيات هذا الدور.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="branch_id" class="stk-req">الفرع</label>
                            <select id="branch_id" name="branch_id" class="form-control select2" required>
                                <option value="" disabled>اختر الفرع</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ dir: 'rtl', width: '100%' });
            }
        });
    </script>
@endsection
