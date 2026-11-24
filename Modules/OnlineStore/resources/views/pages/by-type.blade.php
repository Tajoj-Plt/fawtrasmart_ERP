@extends('onlinestore::master')

@section('title', 'صفحات ' . $pageType->label())

@section('content')
    <div class="content-body">

        @php
            $typeTitle = match($pageType->value) {
                'page' => 'الصفحات العادية',
                'policy' => 'السياسات',
                'faq' => 'الأسئلة الشائعة',
                default => 'صفحات المتجر',
            };
        @endphp

        @section('page-actions')
            @if (hasPermission('online_store.pages.create'))
                <a href="{{ route('store_pages.create') }}?type={{ $pageType->value }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة صفحة جديدة
                </a>
            @endif
            <a href="{{ route('store_pages.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> الكل
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => $typeTitle,
            'icon' => match($pageType->value) { 'page' => 'file', 'policy' => 'shield', 'faq' => 'help-circle', default => 'file-text' },
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'صفحات المتجر' => route('store_pages.index'),
                $typeTitle => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon">
                    <i class="feather icon-{{ match($pageType->value) { 'page' => 'file', 'policy' => 'shield', 'faq' => 'help-circle', default => 'file-text' } }}"></i>
                </span>
                <span class="stk-metric__label">{{ match($pageType->value) { 'page' => 'إجمالي الصفحات', 'policy' => 'إجمالي السياسات', 'faq' => 'إجمالي الأسئلة', default => 'الإجمالي' } }}</span>
                <span class="stk-metric__value">{{ $stats['total'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">نشط</span>
                <span class="stk-metric__value">{{ $stats['active'] ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i>
                    {{ match($pageType->value) { 'page' => 'قائمة الصفحات', 'policy' => 'قائمة السياسات', 'faq' => 'قائمة الأسئلة الشائعة', default => 'القائمة' } }}
                </h6>
                <span class="badge badge-light-primary">{{ $pages->total() }} صفحة</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>العنوان</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center">الترتيب</th>
                            <th class="text-center">التاريخ</th>
                            @if (hasPermission(['online_store.pages.view', 'online_store.pages.edit', 'online_store.pages.delete']))
                                <th class="text-center" style="width: 120px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr>
                                <td class="text-center stk-num">{{ $page->id }}</td>
                                <td>
                                    <span class="stk-media__title">{{ $page->title }}</span>
                                    <small class="text-muted d-block">{{ $page->slug }}</small>
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.pages.toggle_status'))
                                        <span class="stk-state {{ $page->is_active ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $page->id }}" data-url="{{ route('store_pages.toggle-status', $page) }}">
                                            {{ $page->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $page->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $page->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center stk-num">{{ $page->sort_order }}</td>
                                <td class="text-center">
                                    <small class="text-muted">{{ $page->created_at->format('Y-m-d') }}</small>
                                </td>
                                @if (hasPermission(['online_store.pages.view', 'online_store.pages.edit', 'online_store.pages.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.pages.view'))
                                                    <a class="dropdown-item" href="{{ route('store_pages.show', $page) }}">
                                                        <i class="feather icon-eye"></i> عرض
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('store_pages.preview', $page) }}" target="_blank">
                                                        <i class="feather icon-external-link"></i> معاينة
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.pages.edit'))
                                                    <a class="dropdown-item" href="{{ route('store_pages.edit', $page) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.pages.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_pages.destroy', $page) }}" method="POST" class="delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="feather icon-trash-2"></i> حذف
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لا توجد صفحات من هذا النوع</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة صفحة جديدة.</p>
                                        @if (hasPermission('online_store.pages.create'))
                                            <a href="{{ route('store_pages.create') }}?type={{ $pageType->value }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة صفحة
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $pages, 'label' => 'نتيجة'])
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // تبديل الحالة
    $('.toggle-status').click(function() {
        var btn = $(this);
        var url = btn.data('url');

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    btn.toggleClass('stk-state--on stk-state--off');
                    btn.text(response.is_active ? 'نشط' : 'غير نشط');
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث الحالة');
            }
        });
    });

    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه الصفحة نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
@endsection
