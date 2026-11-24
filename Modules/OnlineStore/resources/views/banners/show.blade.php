@extends('onlinestore::master')

@section('title', 'تفاصيل البانر: ' . $storeBanner->title)

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.banners.edit'))
                <a href="{{ route('store_banners.edit', $storeBanner) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('store_banners.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل البانر',
            'icon' => 'image',
            'sub' => $storeBanner->title,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'البانرات' => route('store_banners.index'),
                $storeBanner->title => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="row">
            <!-- معاينة البانر -->
            <div class="col-lg-8">
                <div class="card">
                    @if ($storeBanner->image)
                        <img src="{{ asset($storeBanner->image) }}" alt="{{ $storeBanner->title }}" class="img-fluid" style="width:100%;max-height:280px;object-fit:cover;">
                    @else
                        <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                            <i class="feather icon-image text-muted" style="font-size: 48px;"></i>
                        </div>
                    @endif
                    <div class="card-body">
                        <h5 class="mb-1">{{ $storeBanner->title }}</h5>
                        @if ($storeBanner->description)
                            <p class="text-muted mb-0">{{ $storeBanner->description }}</p>
                        @endif
                    </div>
                </div>

                <!-- معاينة الجوال إن وجدت -->
                @if ($storeBanner->mobile_image)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-smartphone"></i> معاينة الجوال</h6>
                        </div>
                        <div class="card-body text-center">
                            <img src="{{ asset($storeBanner->mobile_image) }}" alt="Mobile Preview" style="max-width:220px;border-radius:12px;">
                        </div>
                    </div>
                @endif

                <!-- معلومات الرابط -->
                @if ($storeBanner->link || $storeBanner->link_id)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-link"></i> رابط البانر</h6>
                        </div>
                        <div class="card-body">
                            @php
                                $linkIcon = match($storeBanner->link_type?->value ?? 'url') {
                                    'product' => 'icon-box',
                                    'category' => 'icon-folder',
                                    'page' => 'icon-file-text',
                                    'offer' => 'icon-tag',
                                    default => 'icon-link'
                                };
                            @endphp
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather {{ $linkIcon }}"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__sub">{{ $storeBanner->link_type?->name ?? 'رابط خارجي' }}</span>
                                    @if ($storeBanner->link)
                                        <a href="{{ $storeBanner->link }}" target="_blank">{{ $storeBanner->link }}</a>
                                    @elseif ($storeBanner->link_id)
                                        <span class="stk-media__title">معرف: {{ $storeBanner->link_id }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- التفاصيل الجانبية -->
            <div class="col-lg-4">
                <!-- حالة البانر -->
                <div class="card">
                    <div class="card-body text-center">
                        <span class="stk-state {{ $storeBanner->is_active ? 'stk-state--on' : 'stk-state--off' }}" style="font-size:1rem;">
                            <i class="feather icon-{{ $storeBanner->is_active ? 'check-circle' : 'x-circle' }}"></i>
                            {{ $storeBanner->is_active ? 'البانر نشط' : 'البانر متوقف' }}
                        </span>

                        @if (hasPermission('online_store.banners.toggle_status'))
                            <form action="{{ route('store_banners.toggle-status', $storeBanner) }}" method="POST" class="d-inline mt-2">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $storeBanner->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    <i class="feather icon-{{ $storeBanner->is_active ? 'pause' : 'play' }}"></i>
                                    {{ $storeBanner->is_active ? 'إيقاف' : 'تفعيل' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <!-- صلاحية العرض -->
                <div class="card">
                    <div class="card-body text-center">
                        @if ($storeBanner->isExpired())
                            <i class="feather icon-x-circle text-danger" style="font-size: 24px;"></i>
                            <h6 class="mt-1 text-danger">انتهت صلاحية العرض</h6>
                        @elseif (!$storeBanner->isStarted())
                            <i class="feather icon-clock text-warning" style="font-size: 24px;"></i>
                            <h6 class="mt-1 text-warning">لم يبدأ بعد</h6>
                        @else
                            <i class="feather icon-check-circle text-success" style="font-size: 24px;"></i>
                            <h6 class="mt-1 text-success">ساري المفعول</h6>
                        @endif
                        @if ($storeBanner->days_remaining !== null)
                            <small class="text-muted">{{ $storeBanner->days_remaining }} يوم متبقي</small>
                        @endif
                    </div>
                </div>

                <!-- معلومات البانر -->
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات البانر</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>رقم البانر</dt>
                                <dd class="stk-num">#{{ $storeBanner->id }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>موقع العرض</dt>
                                <dd>{{ $storeBanner->position?->name ?? '-' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>ترتيب العرض</dt>
                                <dd class="stk-num">{{ $storeBanner->sort_order }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ البداية</dt>
                                <dd class="stk-num">{{ $storeBanner->start_date?->format('Y-m-d H:i') ?? 'غير محدد' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ النهاية</dt>
                                <dd class="stk-num">{{ $storeBanner->end_date?->format('Y-m-d H:i') ?? 'غير محدد' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإنشاء</dt>
                                <dd class="stk-num">{{ $storeBanner->created_at->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تحديث</dt>
                                <dd class="stk-num">{{ $storeBanner->updated_at->format('Y-m-d H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- إجراءات سريعة -->
                @if (hasPermission(['online_store.banners.edit', 'online_store.banners.create', 'online_store.banners.delete']))
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-zap"></i> إجراءات سريعة</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-1">
                                @if (hasPermission('online_store.banners.edit'))
                                    <a href="{{ route('store_banners.edit', $storeBanner) }}" class="btn btn-outline-primary btn-block">
                                        <i class="feather icon-edit-2"></i> تعديل البانر
                                    </a>
                                @endif
                                @if (hasPermission('online_store.banners.create'))
                                    <form action="{{ route('store_banners.duplicate', $storeBanner) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-info btn-block">
                                            <i class="feather icon-copy"></i> نسخ البانر
                                        </button>
                                    </form>
                                @endif
                                @if (hasPermission('online_store.banners.delete'))
                                    <form action="{{ route('store_banners.destroy', $storeBanner) }}" method="POST" class="delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-block">
                                            <i class="feather icon-trash-2"></i> حذف البانر
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف البانر نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endsection
