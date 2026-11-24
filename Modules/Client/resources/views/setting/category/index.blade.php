{{--
    تصنيفات العملاء — أُعيد بناؤها بنفس نظام تصميم الوحدة الفعلي
    (`.cl-scope` + `partials/settings-theme` + `components/page-head`/`guide`)
    بدل CSS مكتوبةٍ يدوياً بألوانٍ ثابتة، ونفس هيكل «بطاقة النتائج»
    (`cl-table-card`/`cl-table-head`) المستعمَل في `index.blade.php` الرئيسية
    لقائمة العملاء — لا تصميماً موازياً.

    أُصلحت أيضاً: أزرار BS4 (`data-toggle`/`data-target`) كانت لا تفتح شيئاً
    فعلياً لأن بقية الوحدة على Bootstrap 5 (`data-bs-toggle`/`data-bs-target`).
--}}
@extends('client::master')

@section('title')
    تصنيفات العملاء
@stop

@section('content')
    @include('layouts.alerts.success')
    @include('layouts.alerts.error')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'تصنيفات العملاء',
            'icon' => 'tag',
            'sub' => 'إدارة تصنيفات نشاط العملاء المستخدَمة في الفرز والتقارير',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'تصنيفات العملاء' => null,
            ],
        ])

        @include('client::components.guide', [
            'key' => 'clients.categories.index',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'تصنيفات العملاء تُستعمَل لفرز العملاء وتصفيتهم في القوائم والتقارير ولوحة التحكّم، ويُختار أحدها عند إضافة أو تعديل عميل.',
            'facts' => [
                'حذف تصنيفٍ لديه عملاء يزيل التصنيف عن ملفّاتهم — لا يحذف العملاء أنفسهم.',
            ],
        ])

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    @permission('settings_customer_categories|clients_edit_client_settings')
                    <a href="{{ route('categoriesClient.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        <span>أضف تصنيف جديد</span>
                    </a>
                    @endpermission
                </div>
            </div>
        </div>

        <div class="card cl-filters">
            <div class="card-body">
                <div class="cl-filters__head">
                    <div class="cl-filters__title">
                        <i class="feather icon-sliders"></i>
                        <span>بحث وتصفية</span>
                    </div>
                </div>
                <form action="{{ route('categoriesClient.index') }}" method="GET">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-pro"><i class="fa fa-tag"></i> اسم التصنيف</label>
                            <input type="text" name="name" class="form-control" value="{{ request('name') }}"
                                placeholder="ابحث باسم التصنيف">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-pro"><i class="fa fa-toggle-on"></i> الحالة</label>
                            <select name="active" class="form-control">
                                <option value="">-- جميع الحالات --</option>
                                <option value="1" {{ request('active') == '1' ? 'selected' : '' }}>نشط</option>
                                <option value="0" {{ request('active') == '0' ? 'selected' : '' }}>غير نشط</option>
                            </select>
                        </div>
                    </div>
                    <div class="cl-filters__foot">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>بحث</button>
                        <a href="{{ route('categoriesClient.index') }}" class="btn btn-outline-secondary"><i class="fas fa-undo me-1"></i>إعادة تعيين</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h6 class="cl-table-head__title">
                    <i class="fas fa-list"></i>
                    التصنيفات
                    <span class="cl-count">{{ $categories->count() }}</span>
                </h6>
            </div>

            <div class="card-body position-relative">
                @if ($categories->isEmpty())
                    <div class="cl-empty">
                        <span class="cl-empty__icon"><i class="feather icon-tag"></i></span>
                        <p class="cl-empty__title">لا توجد تصنيفات بعد</p>
                        <p class="cl-empty__hint">ابدأ بإضافة أوّل تصنيف لعملائك.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>اسم التصنيف</th>
                                    <th>الوصف</th>
                                    <th>الحالة</th>
                                    <th>عدد العملاء</th>
                                    <th style="width: 12%">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)
                                    <tr>
                                        <td class="cl-num">{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="cl-media">
                                                <span class="cl-media__thumb"><i class="fas fa-tag"></i></span>
                                                <div>
                                                    <div class="cl-media__title">{{ $category->name }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $category->description ?? '—' }}</td>
                                        <td>
                                            @if($category->active)
                                                <span class="cl-state cl-state--on">نشط</span>
                                            @else
                                                <span class="cl-state cl-state--off">غير نشط</span>
                                            @endif
                                        </td>
                                        <td class="cl-num">{{ $category->clients_count ?? 0 }}</td>
                                        <td>
                                            @permission('settings_customer_categories|clients_edit_client_settings')
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    id="dropdownMenuButton{{ $category->id }}"
                                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $category->id }}">
                                                    <a class="dropdown-item" href="{{ route('categoriesClient.edit', $category->id) }}">
                                                        <i class="fa fa-edit me-2 text-primary"></i>تعديل
                                                    </a>
                                                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal"
                                                        data-bs-target="#deleteModal{{ $category->id }}">
                                                        <i class="fa fa-trash me-2"></i>حذف
                                                    </a>
                                                </div>
                                            </div>
                                            @endpermission
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="deleteModal{{ $category->id }}" tabindex="-1"
                                        aria-labelledby="deleteModalLabel{{ $category->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="deleteModalLabel{{ $category->id }}">تأكيد الحذف</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>هل أنت متأكد من حذف تصنيف "{{ $category->name }}"؟</p>
                                                    @if($category->clients_count > 0)
                                                        <div class="alert alert-warning mb-0">
                                                            <i class="fa fa-exclamation-triangle"></i>
                                                            هذا التصنيف يحتوي على {{ $category->clients_count }} عميل. سيُزال التصنيف من هؤلاء العملاء.
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                    <form action="{{ route('categoriesClient.destroy', $category->id) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger">حذف</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
