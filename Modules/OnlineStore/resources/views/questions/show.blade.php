@extends('onlinestore::master')

@section('title', 'تفاصيل السؤال')

@section('content')
    @php
        $questionStatus = $productQuestion->status->value ?? $productQuestion->status;
        $statusClass = match ($questionStatus) {
            'pending' => 'stk-state--warn',
            'answered' => 'stk-state--on',
            'rejected' => 'stk-state--off',
            default => 'stk-state--neutral',
        };
        $statusText = match ($questionStatus) {
            'pending' => 'قيد الانتظار',
            'answered' => 'تم الرد',
            'rejected' => 'مرفوض',
            default => 'غير معروف',
        };
        // اسم السائل: عميل المتجر، وإلا الاسم الحرّ للزائر، وإلا «زائر»
        $askerName = $productQuestion->client ? $productQuestion->client->display_name : ($productQuestion->asker_name ?: 'زائر');
        $productImages = $productQuestion->product ? json_decode((string) $productQuestion->product->images, true) : null;
        $productThumb = is_array($productImages) && ! empty($productImages[0]) ? $productImages[0] : null;
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.questions.edit'))
                <a href="{{ route('product_questions.edit', $productQuestion) }}" class="btn btn-primary">
                    <i class="feather icon-edit"></i> تعديل
                </a>
            @endif
            <a href="{{ route('product_questions.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للقائمة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل السؤال #' . $productQuestion->id,
            'icon' => 'help-circle',
            'sub' => $statusText . ' — ' . ($productQuestion->is_public ? 'منشور في المتجر' : 'مخفي عن المتجر'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'أسئلة المنتجات' => route('product_questions.index'),
                'سؤال #' . $productQuestion->id => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="row g-3">
            <!-- محتوى السؤال والإجابة -->
            <div class="col-lg-8">
                <!-- السؤال -->
                <div class="card">
                    <div class="stk-head-plain">
                        <h6 class="stk-table-head__title"><i class="feather icon-help-circle"></i> السؤال</h6>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="stk-state {{ $statusClass }}">{{ $statusText }}</span>
                            @if ($productQuestion->is_public)
                                <span class="badge badge-light-info"><i class="feather icon-globe"></i> منشور</span>
                            @else
                                <span class="badge badge-light-secondary"><i class="feather icon-lock"></i> مخفي</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <blockquote class="qshow-quote">
                            <i class="feather icon-message-square qshow-quote__mark"></i>
                            <p class="qshow-quote__text mb-0">{{ $productQuestion->question }}</p>
                        </blockquote>
                        <div class="qshow-meta">
                            <span><i class="feather icon-user"></i> {{ $askerName }}</span>
                            <span class="stk-num"><i class="feather icon-calendar"></i> {{ $productQuestion->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                </div>

                <!-- الإجابة -->
                <div class="card">
                    <div class="stk-head-plain">
                        <h6 class="stk-table-head__title"><i class="feather icon-message-circle"></i> الإجابة</h6>
                    </div>
                    <div class="card-body">
                        @if ($productQuestion->answer)
                            <div class="qshow-answer">
                                <p class="qshow-answer__text mb-2">{{ $productQuestion->answer }}</p>
                                <div class="qshow-answer__by">
                                    <i class="feather icon-check-circle"></i>
                                    {{ $productQuestion->answeredBy->name ?? 'مسؤول النظام' }}
                                    <span class="mx-1">·</span>
                                    <i class="feather icon-clock"></i>
                                    {{ $productQuestion->answered_at ? $productQuestion->answered_at->format('Y-m-d H:i') : '' }}
                                </div>
                            </div>

                            @if (hasPermission('online_store.questions.answer'))
                                <div class="mt-3">
                                    <button class="btn btn-outline-primary btn-sm" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#editAnswerForm"
                                            data-toggle="collapse" data-target="#editAnswerForm">
                                        <i class="feather icon-edit"></i> تعديل الإجابة
                                    </button>
                                </div>
                                <div class="collapse mt-2" id="editAnswerForm">
                                    <form action="{{ route('product_questions.update-answer', $productQuestion) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="form-group">
                                            <textarea class="form-control" name="answer" rows="4" required>{{ $productQuestion->answer }}</textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary mt-2">
                                            <i class="feather icon-save"></i> حفظ التعديلات
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @elseif (hasPermission('online_store.questions.answer'))
                            <form action="{{ route('product_questions.answer', $productQuestion) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label for="answer" class="stk-req">اكتب إجابتك</label>
                                    <textarea class="form-control" id="answer" name="answer" rows="5" required placeholder="اكتب الإجابة على هذا السؤال..."></textarea>
                                </div>
                                <input type="hidden" name="is_public" value="0">
                                <label class="d-inline-flex align-items-center gap-1 my-2" for="is_public" style="cursor:pointer">
                                    <input type="checkbox" class="stk-check" id="is_public" name="is_public" value="1" @checked($productQuestion->is_public)>
                                    <span>نشر السؤال والإجابة للعامة</span>
                                </label>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-send"></i> إرسال الإجابة
                                </button>
                            </form>
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-message-circle"></i></span>
                                <h6 class="stk-empty__title">لا توجد إجابة بعد</h6>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- أسئلة ذات صلة -->
                @if ($relatedQuestions->isNotEmpty())
                    <div class="card">
                        <div class="stk-head-plain">
                            <h6 class="stk-table-head__title"><i class="feather icon-list"></i> أسئلة أخرى لنفس المنتج</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="qshow-related">
                                @foreach ($relatedQuestions as $related)
                                    <a href="{{ route('product_questions.show', $related) }}" class="qshow-related__item">
                                        <div class="min-w-0">
                                            <strong class="d-block text-truncate">{{ Str::limit($related->question, 70) }}</strong>
                                            <small class="text-muted d-block text-truncate">{{ Str::limit($related->answer, 90) ?: 'بلا إجابة بعد' }}</small>
                                        </div>
                                        <i class="feather icon-chevron-left flex-none"></i>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <!-- معلومات المنتج -->
                <div class="card">
                    <div class="stk-head-plain">
                        <h6 class="stk-table-head__title"><i class="feather icon-box"></i> المنتج</h6>
                    </div>
                    <div class="card-body text-center">
                        @if ($productQuestion->product)
                            <div class="qshow-product-img mx-auto mb-2">
                                @if ($productThumb)
                                    <img src="{{ asset($productThumb) }}" alt="{{ $productQuestion->product->name }}">
                                @else
                                    <i class="feather icon-image"></i>
                                @endif
                            </div>
                            <h6 class="mb-2">{{ $productQuestion->product->name }}</h6>
                            @if (hasPermission('online_store.questions.view'))
                                <a href="{{ route('product_questions.product', $productQuestion->product) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="feather icon-external-link"></i> عرض أسئلة المنتج
                                </a>
                            @endif
                        @else
                            <p class="text-muted mb-0">المنتج محذوف</p>
                        @endif
                    </div>
                </div>

                <!-- معلومات العميل -->
                <div class="card">
                    <div class="stk-head-plain">
                        <h6 class="stk-table-head__title"><i class="feather icon-user"></i> السائل</h6>
                    </div>
                    <div class="card-body">
                        @if ($productQuestion->client)
                            <dl class="stk-def mb-0">
                                <div class="stk-def__row">
                                    <dt>الاسم</dt>
                                    <dd>{{ $productQuestion->client->display_name }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>البريد</dt>
                                    <dd class="text-break">{{ $productQuestion->client->email ?: '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الهاتف</dt>
                                    <dd class="stk-num">{{ $productQuestion->client->phone ?? 'غير محدد' }}</dd>
                                </div>
                            </dl>
                        @else
                            <dl class="stk-def mb-0">
                                <div class="stk-def__row">
                                    <dt>الاسم</dt>
                                    <dd>{{ $askerName }}</dd>
                                </div>
                            </dl>
                            <p class="text-muted mb-0 mt-1">
                                {{ $productQuestion->client_id ? 'حساب العميل محذوف.' : 'زائر بلا حساب في المتجر — سُجّل السؤال من لوحة التحكم.' }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- الإجراءات -->
                @if (hasPermission(['online_store.questions.toggle_public', 'online_store.questions.moderate', 'online_store.questions.delete']))
                    <div class="card">
                        <div class="stk-head-plain">
                            <h6 class="stk-table-head__title"><i class="feather icon-settings"></i> الإجراءات</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                @if (hasPermission('online_store.questions.toggle_public'))
                                    <button class="btn btn-outline-info w-100 toggle-public-btn" data-url="{{ route('product_questions.toggle-public', $productQuestion) }}">
                                        <i class="feather icon-{{ $productQuestion->is_public ? 'eye-off' : 'eye' }}"></i>
                                        {{ $productQuestion->is_public ? 'إخفاء من المتجر' : 'نشر في المتجر' }}
                                    </button>
                                @endif

                                @if (hasPermission('online_store.questions.moderate'))
                                    @if ($questionStatus === 'pending')
                                        <button class="btn btn-outline-warning w-100 reject-btn" data-url="{{ route('product_questions.reject', $productQuestion) }}">
                                            <i class="feather icon-x-circle"></i> رفض السؤال
                                        </button>
                                    @endif

                                    @if ($questionStatus === 'rejected')
                                        <button class="btn btn-outline-info w-100 reopen-btn" data-url="{{ route('product_questions.reopen', $productQuestion) }}">
                                            <i class="feather icon-refresh-cw"></i> إعادة فتح السؤال
                                        </button>
                                    @endif
                                @endif

                                @if (hasPermission('online_store.questions.delete'))
                                    <form action="{{ route('product_questions.destroy', $productQuestion) }}" method="POST" class="delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger w-100">
                                            <i class="feather icon-trash-2"></i> حذف السؤال
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

@section('css')
<style>
    .qshow-quote {
        position: relative;
        margin: 0;
        padding: 1rem 3rem 1rem 1.25rem;
        background: var(--stk-surface-2, #f8f8fc);
        border: 1px solid var(--stk-border, #ebe9f1);
        border-inline-start: 3px solid var(--stk-primary, #7367f0);
        border-radius: .6rem;
    }
    .qshow-quote__mark {
        position: absolute; inset-inline-start: 1rem; top: 1rem;
        color: var(--stk-primary, #7367f0); opacity: .5; font-size: 1.1rem;
    }
    .qshow-quote__text { font-size: 1.05rem; line-height: 1.7; color: var(--stk-body, #5e5873); white-space: pre-line; }
    .qshow-meta {
        display: flex; flex-wrap: wrap; gap: 1.25rem;
        margin-top: .85rem; font-size: .85rem; color: var(--stk-muted, #b9b9c3);
    }
    .qshow-meta i { margin-inline-end: .3rem; }

    .qshow-answer {
        padding: 1rem 1.25rem;
        background: color-mix(in srgb, var(--stk-primary, #7367f0) 6%, #fff);
        border: 1px solid color-mix(in srgb, var(--stk-primary, #7367f0) 22%, transparent);
        border-radius: .6rem;
    }
    .qshow-answer__text { line-height: 1.7; color: var(--stk-body, #5e5873); white-space: pre-line; }
    .qshow-answer__by { font-size: .8rem; color: var(--stk-muted, #b9b9c3); }
    .qshow-answer__by i { margin-inline-end: .25rem; }
    .qshow-answer__by .feather.icon-check-circle { color: var(--stk-success, #28c76f); }

    .qshow-related { display: flex; flex-direction: column; }
    .qshow-related__item {
        display: flex; align-items: center; justify-content: space-between; gap: .75rem;
        padding: .8rem 1.5rem;
        border-top: 1px solid var(--stk-border, #ebe9f1);
        color: var(--stk-body, #5e5873); text-decoration: none;
        transition: background-color .12s;
    }
    .qshow-related__item:first-child { border-top: 0; }
    .qshow-related__item:hover { background: var(--stk-surface-2, #f8f8fc); color: var(--stk-primary, #7367f0); }
    .qshow-related__item .min-w-0 { min-width: 0; }
    .qshow-related__item .flex-none { flex: none; }

    .qshow-product-img {
        width: 120px; height: 120px; border-radius: .75rem;
        border: 1px solid var(--stk-border, #ebe9f1);
        background: var(--stk-surface-2, #f8f8fc);
        display: flex; align-items: center; justify-content: center;
        overflow: hidden; color: var(--stk-muted, #b9b9c3);
    }
    .qshow-product-img img { width: 100%; height: 100%; object-fit: cover; }
    .qshow-product-img i { font-size: 2.5rem; }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // تبديل العام/الخاص
    $('.toggle-public-btn').click(function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // رفض السؤال
    $('.reject-btn').click(function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // إعادة فتح السؤال
    $('.reopen-btn').click(function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا السؤال نهائياً!',
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
