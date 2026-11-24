@extends('onlinestore::master')

@section('title', 'أسئلة المنتج: ' . $product->name)

@section('content')
    @php
        $askerName = fn ($q) => $q->client ? $q->client->display_name : ($q->asker_name ?: 'زائر');
        $productImages = json_decode((string) $product->images, true);
        $productThumb = is_array($productImages) && ! empty($productImages[0]) ? $productImages[0] : null;
        $canRowActions = hasPermission([
            'online_store.questions.view', 'online_store.questions.edit', 'online_store.questions.delete',
        ]);
    @endphp
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('product_questions.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> كل الأسئلة
            </a>
            @if (hasPermission('online_store.products.show'))
                <a href="{{ route('store_products.show', $product) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-box"></i> صفحة المنتج
                </a>
            @endif
            @if (hasPermission('online_store.questions.create'))
                <a href="{{ route('product_questions.create', ['product_id' => $product->id]) }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة سؤال لهذا المنتج
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'أسئلة المنتج',
            'icon' => 'help-circle',
            'sub' => $product->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'أسئلة المنتجات' => route('product_questions.index'),
                $product->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- معلومات المنتج -->
        <div class="card mb-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        @if ($productThumb)
                            <img src="{{ asset($productThumb) }}" alt="{{ $product->name }}" class="img-fluid rounded" style="max-height: 120px;">
                        @else
                            <div>
                                <i class="feather icon-image" style="font-size: 48px;"></i>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-7">
                        <h5>{{ $product->name }}</h5>
                        <p class="text-muted mb-0">{{ Str::limit(strip_tags((string) $product->description), 150) }}</p>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="mb-0 stk-num">{{ $stats['total'] }}</h4>
                        <small class="text-muted">سؤال</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-help-circle"></i></span>
                <span class="stk-metric__label">إجمالي الأسئلة</span>
                <span class="stk-metric__value">{{ $stats['total'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">تم الرد</span>
                <span class="stk-metric__value">{{ $stats['answered'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">قيد الانتظار</span>
                <span class="stk-metric__value">{{ $stats['pending'] }}</span>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> الأسئلة</h6>
                <span class="badge badge-light-primary">{{ $questions->total() }} سؤال</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>السائل</th>
                            <th>السؤال</th>
                            <th>الإجابة</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center">النشر</th>
                            <th class="text-center">التاريخ</th>
                            @if ($canRowActions)
                                <th class="text-center" style="width: 130px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($questions as $question)
                            @php
                                $questionStatus = $question->status->value ?? $question->status;
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
                            @endphp
                            <tr>
                                <td class="text-center stk-num">{{ $question->id }}</td>
                                <td>
                                    <span class="stk-media__title">{{ $askerName($question) }}</span>
                                    <span class="stk-media__sub d-block">{{ $question->client ? ($question->client->email ?: $question->client->phone) : 'بلا حساب في المتجر' }}</span>
                                </td>
                                <td>
                                    <span>{{ Str::limit($question->question, 50) }}</span>
                                </td>
                                <td>
                                    @if ($question->answer)
                                        <span class="text-success">
                                            <i class="feather icon-check-circle"></i>
                                            {{ Str::limit($question->answer, 40) }}
                                        </span>
                                    @else
                                        <span class="text-muted">
                                            <i class="feather icon-minus-circle"></i> لا توجد إجابة
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="stk-state {{ $statusClass }}">{{ $statusText }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="stk-state {{ $question->is_public ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $question->is_public ? 'منشور' : 'مخفي' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <small>{{ $question->created_at->format('Y-m-d') }}</small>
                                </td>
                                @if ($canRowActions)
                                    <td class="text-center text-nowrap">
                                        @if (hasPermission('online_store.questions.view'))
                                            <a href="{{ route('product_questions.show', $question) }}" class="btn btn-sm btn-outline-primary"
                                                title="{{ $question->answer ? 'عرض' : 'عرض والإجابة' }}">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                        @endif
                                        @if (hasPermission('online_store.questions.edit'))
                                            <a href="{{ route('product_questions.edit', $question) }}" class="btn btn-sm btn-outline-secondary" title="تعديل">
                                                <i class="feather icon-edit"></i>
                                            </a>
                                        @endif
                                        @if (hasPermission('online_store.questions.delete'))
                                            <form action="{{ route('product_questions.destroy', $question) }}" method="POST" class="d-inline pq-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="back" value="product">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canRowActions ? 8 : 7 }}">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لا توجد أسئلة</h6>
                                        <p class="stk-empty__hint">لم يُطرح أي سؤال عن هذا المنتج بعد. تصل الأسئلة من صفحة المنتج في المتجر، أو أضفها من زر «إضافة سؤال لهذا المنتج».</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $questions, 'label' => 'نتيجة'])
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(function () {
    // تأكيد الحذف
    $('.pq-delete-form').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        if (!window.Swal) { if (confirm('سيتم حذف هذا السؤال نهائياً. متابعة؟')) form.submit(); return; }
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا السؤال نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then(function (result) { if (result.isConfirmed) form.submit(); });
    });
});
</script>
@endsection
