@extends('onlinestore::master')

@section('title', 'أسئلة المنتجات')

@section('content')
    @php
        // اسم السائل: عميل المتجر، وإلا الاسم الحرّ للزائر، وإلا «زائر»
        $askerName = fn ($q) => $q->client ? $q->client->display_name : ($q->asker_name ?: 'زائر');
        $productThumb = function ($product) {
            $images = $product ? json_decode((string) $product->images, true) : null;

            return is_array($images) && ! empty($images[0]) ? $images[0] : null;
        };
    @endphp
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.questions.create'))
                <a href="{{ route('product_questions.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة سؤال
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'أسئلة المنتجات',
            'icon' => 'help-circle',
            'sub' => 'أسئلة العملاء حول منتجات المتجر والإجابة عليها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'أسئلة المنتجات' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="alert alert-info d-flex align-items-start gap-1 mb-2" role="note">
            <i class="feather icon-info mt-25"></i>
            <div>
                <strong>ما هذه الشاشة؟</strong>
                عملاء المتجر يكتبون أسئلتهم عن المنتج من صفحته في واجهة المتجر، فتصل هنا «قيد الانتظار».
                أجب على السؤال فتظهر الإجابة تحت المنتج لكل الزوّار (ما دام السؤال «منشوراً»)، ويصل العميلَ بريدٌ بالإجابة.
                ويمكنك أيضاً «إضافة سؤال» بنفسك لسؤالٍ وصلك بالهاتف أو الواتساب.
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-help-circle"></i></span>
                <span class="stk-metric__label">إجمالي الأسئلة</span>
                <span class="stk-metric__value">{{ $stats['total'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">قيد الانتظار</span>
                <span class="stk-metric__value">{{ $stats['pending'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">تم الرد</span>
                <span class="stk-metric__value">{{ $stats['answered'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">مرفوض</span>
                <span class="stk-metric__value">{{ $stats['rejected'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-globe"></i></span>
                <span class="stk-metric__label">منشورة</span>
                <span class="stk-metric__value">{{ $stats['public'] ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('product_questions.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="بحث في السؤال أو الإجابة…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="pending" @selected(request('status') == 'pending')>قيد الانتظار</option>
                                    <option value="answered" @selected(request('status') == 'answered')>تم الرد</option>
                                    <option value="rejected" @selected(request('status') == 'rejected')>مرفوض</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="product_id">المنتج</label>
                                <select name="product_id" id="product_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="answered">الإجابة</label>
                                <select name="answered" id="answered" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="yes" @selected(request('answered') == 'yes')>له إجابة</option>
                                    <option value="no" @selected(request('answered') == 'no')>بلا إجابة</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="is_public">النشر</label>
                                <select name="is_public" id="is_public" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="yes" @selected(request('is_public') == 'yes')>منشور</option>
                                    <option value="no" @selected(request('is_public') == 'no')>مخفي</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('product_questions.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $canBulkQuestions = hasPermission([
                'online_store.questions.answer',
                'online_store.questions.moderate',
                'online_store.questions.delete',
            ]);
            $canQuestionActions = hasPermission([
                'online_store.questions.view',
                'online_store.questions.edit',
                'online_store.questions.answer',
                'online_store.questions.moderate',
                'online_store.questions.delete',
            ]);
            $questionColspan = 8 + ($canBulkQuestions ? 1 : 0) + ($canQuestionActions ? 1 : 0);
        @endphp

        @if ($canBulkQuestions)
            <div class="card mb-2 d-none" id="bulkActionsBar">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-1">
                    <div>
                        <span class="selected-count">0</span> سؤال محدد
                    </div>
                    <div>
                        @if (hasPermission('online_store.questions.answer'))
                            <button type="button" class="btn btn-success btn-sm" id="bulkAnswer">
                                <i class="feather icon-message-circle"></i> إجابة المحدد
                            </button>
                        @endif
                        @if (hasPermission('online_store.questions.moderate'))
                            <button type="button" class="btn btn-warning btn-sm" id="bulkReject">
                                <i class="feather icon-x"></i> رفض المحدد
                            </button>
                        @endif
                        @if (hasPermission('online_store.questions.delete'))
                            <button type="button" class="btn btn-danger btn-sm" id="bulkDelete">
                                <i class="feather icon-trash-2"></i> حذف المحدد
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الأسئلة</h6>
                <div>
                    <button type="button" id="printQuestionsBtn" class="btn btn-sm btn-success">
                        <i class="fa fa-print"></i> طباعة
                    </button>
                    <button type="button" id="exportQuestionsExcel" class="btn btn-sm btn-info">
                        <i class="fa fa-file-excel-o"></i> Excel
                    </button>
                    <span class="badge badge-light-primary">{{ $questions->total() }} سؤال</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="questionsTable">
                    <thead>
                        <tr>
                            @if ($canBulkQuestions)
                                <th class="text-center" style="width: 44px;">
                                    <input type="checkbox" id="selectAll" class="stk-check" title="تحديد الكل">
                                </th>
                            @endif
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>المنتج</th>
                            <th>السائل</th>
                            <th>السؤال</th>
                            <th>الإجابة</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center">النشر</th>
                            <th class="text-center">التاريخ</th>
                            @if ($canQuestionActions)
                                <th class="text-center" style="width: 120px;">الإجراءات</th>
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
                            <tr data-id="{{ $question->id }}">
                                @if ($canBulkQuestions)
                                    <td class="text-center">
                                        <input type="checkbox" class="stk-check question-checkbox" value="{{ $question->id }}">
                                    </td>
                                @endif
                                <td class="text-center stk-num">{{ $question->id }}</td>
                                <td>
                                    <div class="stk-media">
                                        @if ($thumb = $productThumb($question->product))
                                            <span class="stk-media__thumb"><img src="{{ asset($thumb) }}"
                                                    alt="{{ $question->product->name ?? '' }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                        @else
                                            <span class="stk-media__thumb"><i class="feather icon-box"></i></span>
                                        @endif
                                        <span class="stk-media__body">
                                            @if ($question->product && hasPermission('online_store.questions.view'))
                                                <a class="stk-media__title" href="{{ route('product_questions.product', $question->product) }}"
                                                    title="كل أسئلة هذا المنتج">{{ $question->product->name }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $question->product->name ?? 'منتج محذوف' }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="stk-media__title">{{ $askerName($question) }}</span>
                                    <span class="stk-media__sub d-block">{{ $question->client ? ($question->client->email ?: $question->client->phone) : 'بلا حساب في المتجر' }}</span>
                                </td>
                                <td>
                                    <span>{{ Str::limit($question->question, 60) }}</span>
                                </td>
                                <td>
                                    @if ($question->answer)
                                        <div class="text-success">
                                            <i class="feather icon-check-circle"></i>
                                            <span>{{ Str::limit($question->answer, 40) }}</span>
                                        </div>
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
                                    @if (hasPermission('online_store.questions.toggle_public'))
                                        <button type="button" class="btn btn-sm {{ $question->is_public ? 'btn-success' : 'btn-secondary' }} toggle-public"
                                            data-id="{{ $question->id }}"
                                            data-url="{{ route('product_questions.toggle-public', $question) }}"
                                            title="إظهار السؤال في المتجر أو إخفاؤه">
                                            <i class="feather icon-{{ $question->is_public ? 'eye' : 'eye-off' }}"></i>
                                        </button>
                                    @else
                                        <span class="stk-state {{ $question->is_public ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $question->is_public ? 'منشور' : 'مخفي' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">{{ $question->created_at->format('Y-m-d') }}</small>
                                </td>
                                @if ($canQuestionActions)
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" data-toggle="dropdown" aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-right">
                                                @if (hasPermission('online_store.questions.view'))
                                                    <a class="dropdown-item" href="{{ route('product_questions.show', $question) }}">
                                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.questions.edit'))
                                                    <a class="dropdown-item" href="{{ route('product_questions.edit', $question) }}">
                                                        <i class="feather icon-edit"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.questions.answer'))
                                                    <button type="button" class="dropdown-item quick-answer-btn"
                                                            data-id="{{ $question->id }}"
                                                            data-question="{{ $question->question }}"
                                                            data-answer="{{ $question->answer }}">
                                                        <i class="feather icon-message-circle"></i> {{ $question->answer ? 'تعديل الإجابة' : 'إجابة سريعة' }}
                                                    </button>
                                                @endif
                                                @if (hasPermission('online_store.questions.moderate'))
                                                    @if ($questionStatus === 'pending')
                                                        <button class="dropdown-item reject-question" data-id="{{ $question->id }}" data-url="{{ route('product_questions.reject', $question) }}">
                                                            <i class="feather icon-x-circle"></i> رفض
                                                        </button>
                                                    @endif
                                                    @if ($questionStatus === 'rejected')
                                                        <button class="dropdown-item reopen-question" data-id="{{ $question->id }}" data-url="{{ route('product_questions.reopen', $question) }}">
                                                            <i class="feather icon-refresh-cw"></i> إعادة فتح
                                                        </button>
                                                    @endif
                                                @endif
                                                @if (hasPermission('online_store.questions.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('product_questions.destroy', $question) }}" method="POST" class="delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item delete-item text-danger">
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
                                <td colspan="{{ $questionColspan }}">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لا توجد أسئلة</h6>
                                        <p class="stk-empty__hint">تصل الأسئلة من صفحة المنتج في واجهة المتجر، أو أضف سؤالاً بنفسك من زر «إضافة سؤال».</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- جدول مخفي بنصوص صافية، لأغراض الطباعة والتصدير فقط (بلا أيقونات/إجراءات) --}}
            <table id="questionsExportTable" class="d-none">
                <tbody>
                    @forelse ($questions as $question)
                        @php
                            $exportQuestionStatus = $question->status->value ?? $question->status;
                            $exportQuestionStatusText = match ($exportQuestionStatus) {
                                'pending' => 'قيد الانتظار',
                                'answered' => 'تم الرد',
                                'rejected' => 'مرفوض',
                                default => 'غير معروف',
                            };
                        @endphp
                        <tr>
                            <td>{{ $question->id }}</td>
                            <td>{{ $question->product->name ?? 'منتج محذوف' }}</td>
                            <td>{{ $askerName($question) }}</td>
                            <td>{{ $question->question }}</td>
                            <td>{{ $question->answer ?? 'لا توجد إجابة' }}</td>
                            <td>{{ $exportQuestionStatusText }}</td>
                            <td>{{ $question->is_public ? 'منشور' : 'مخفي' }}</td>
                            <td>{{ $question->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $questions, 'label' => 'نتيجة'])
        </div>
    </div>

    @if (hasPermission('online_store.questions.answer'))
        <!-- Quick Answer Modal -->
        <div class="modal fade" id="quickAnswerModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">الإجابة على السؤال</h5>
                        <button type="button" class="btn-close close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="quickAnswerForm">
                        <div class="modal-body">
                            <input type="hidden" id="quickAnswerQuestionId" name="question_id">
                            <div class="form-group">
                                <label>السؤال</label>
                                <div class="alert alert-light" id="questionText"></div>
                            </div>
                            <div class="form-group">
                                <label for="quick_answer">الإجابة</label>
                                <textarea class="form-control" id="quick_answer" name="answer" rows="4" required placeholder="اكتب إجابتك هنا..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">إرسال الإجابة</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bulk Answer Modal -->
        <div class="modal fade" id="bulkAnswerModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إجابة جماعية</h5>
                        <button type="button" class="btn-close close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="bulkAnswerForm">
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="feather icon-info"></i>
                                سيتم تطبيق هذه الإجابة على جميع الأسئلة المحددة
                            </div>
                            <div class="form-group">
                                <label for="bulk_answer">الإجابة</label>
                                <textarea class="form-control" id="bulk_answer" name="answer" rows="4" required placeholder="اكتب الإجابة الموحدة هنا..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">إرسال للمحدد</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
// هذا القالب يحمّل Bootstrap 5 (CDN) و Bootstrap 4 (داخل Vuexy vendors.min.js)
// معاً — نفتح/نغلق المودال بأي واجهة متاحة، ثم عرضاً يدوياً كملاذ أخير.
// jQuery أولاً: إضافة مودال Bootstrap 4 (من Vuexy vendors.min.js) هي المحمّلة
// فعلياً على هذه الصفحة. BS5 احتياطٌ إن غابت.
function openModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var jq = window.jQuery;
    if (jq && jq.fn && jq.fn.modal) { jq(el).modal('show'); return; }
    var B = window.bootstrap;
    if (B && B.Modal) {
        (B.Modal.getOrCreateInstance ? B.Modal.getOrCreateInstance(el) : new B.Modal(el)).show();
        return;
    }
    el.classList.add('show');
    el.style.display = 'block';
    document.body.classList.add('modal-open');
    if (!document.querySelector('.modal-backdrop')) {
        var bd = document.createElement('div');
        bd.className = 'modal-backdrop fade show';
        document.body.appendChild(bd);
    }
}
function closeModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var jq = window.jQuery;
    if (jq && jq.fn && jq.fn.modal) { jq(el).modal('hide'); return; }
    var B = window.bootstrap;
    if (B && B.Modal) {
        var inst = B.Modal.getInstance ? B.Modal.getInstance(el)
            : (B.Modal.getOrCreateInstance ? B.Modal.getOrCreateInstance(el) : null);
        if (inst) { inst.hide(); return; }
    }
    el.classList.remove('show');
    el.style.display = 'none';
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
}

$(document).ready(function() {
    // إغلاق يدوي لأزرار «إلغاء / ✕» داخل مودالات هذه الصفحة
    $(document).on('click', '#quickAnswerModal [data-bs-dismiss="modal"], #quickAnswerModal [data-dismiss="modal"], #bulkAnswerModal [data-bs-dismiss="modal"], #bulkAnswerModal [data-dismiss="modal"]', function () {
        closeModal($(this).closest('.modal').attr('id'));
    });

    // طباعة أسئلة المنتجات
    $('#printQuestionsBtn').click(function() {
        printQuestionsReport();
    });

    // تصدير أسئلة المنتجات إلى Excel
    $('#exportQuestionsExcel').click(function() {
        exportQuestionsReport();
    });

    // تهيئة Select2
    if ($.fn.select2) {
        $('.select2').select2({
            dir: 'rtl',
            language: {
                noResults: function() {
                    return "لا توجد نتائج";
                }
            },
            allowClear: true,
            width: '100%'
        });
    }

    // تحديد الكل
    $('#selectAll').change(function() {
        $('.question-checkbox').prop('checked', $(this).is(':checked'));
        toggleBulkActions();
    });

    $(document).on('change', '.question-checkbox', function() {
        toggleBulkActions();
    });

    function toggleBulkActions() {
        var $boxes = $('.question-checkbox');
        var checkedCount = $boxes.filter(':checked').length;

        // مزامنة مربّع «تحديد الكل» — نصف محدَّد عند اختيارٍ جزئي
        $('#selectAll')
            .prop('checked', $boxes.length > 0 && checkedCount === $boxes.length)
            .prop('indeterminate', checkedCount > 0 && checkedCount < $boxes.length);

        $('#bulkActionsBar').toggleClass('d-none', checkedCount === 0);
        $('.selected-count').text(checkedCount);
    }

    function getSelectedIds() {
        return $('.question-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }

    // فتح مودال الإجابة الجماعية
    $('#bulkAnswer').on('click', function() {
        if (getSelectedIds().length === 0) { toastr.warning('حدّد سؤالاً واحداً على الأقل'); return; }
        openModal('bulkAnswerModal');
    });

    // إجابة جماعية
    $('#bulkAnswerForm').submit(function(e) {
        e.preventDefault();
        var ids = getSelectedIds();
        var answer = $.trim($('#bulk_answer').val());
        if (ids.length === 0 || !answer) { toastr.warning('حدّد أسئلة واكتب الإجابة'); return; }

        $.ajax({
            url: '{{ route("product_questions.bulk-answer") }}',
            type: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                _token: '{{ csrf_token() }}',
                ids: ids,
                answer: answer
            },
            success: function(response) {
                if (response && response.success) {
                    toastr.success(response.message || 'تم');
                    closeModal('bulkAnswerModal');
                    location.reload();
                } else {
                    toastr.error((response && response.message) || 'تعذّر حفظ الإجابة');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && (xhr.responseJSON.message
                    || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]));
                toastr.error(msg || 'حدث خطأ أثناء العملية');
            }
        });
    });

    // رفض المحدد
    $('#bulkReject').click(function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;

        $.ajax({
            url: '{{ route("product_questions.bulk-reject") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ids: ids
            },
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

    // حذف المحدد
    $('#bulkDelete').click(function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف الأسئلة المحددة نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("product_questions.bulk-delete") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        }
                    },
                    error: function() {
                        toastr.error('حدث خطأ أثناء الحذف');
                    }
                });
            }
        });
    });

    // فتح مودال الإجابة السريعة
    $(document).on('click', '.quick-answer-btn', function() {
        $('#quickAnswerQuestionId').val($(this).data('id'));
        $('#questionText').text($(this).data('question') || '');
        $('#quick_answer').val($(this).attr('data-answer') || '');
        openModal('quickAnswerModal');
    });

    // إرسال إجابة سريعة
    $('#quickAnswerForm').submit(function(e) {
        e.preventDefault();
        var id = $('#quickAnswerQuestionId').val();
        var answer = $.trim($('#quick_answer').val());
        if (!id || !answer) { toastr.warning('اكتب نص الإجابة أولاً'); return; }

        $.ajax({
            url: '{{ route('product_questions.quick-answer', ['productQuestion' => '__ID__']) }}'.replace('__ID__', id),
            type: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                _token: '{{ csrf_token() }}',
                answer: answer
            },
            success: function(response) {
                if (response && response.success) {
                    toastr.success(response.message || 'تم');
                    closeModal('quickAnswerModal');
                    location.reload();
                } else {
                    toastr.error((response && response.message) || 'تعذّر حفظ الإجابة');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && (xhr.responseJSON.message
                    || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]));
                toastr.error(msg || 'حدث خطأ أثناء إرسال الإجابة');
            }
        });
    });

    // رفض سؤال
    $(document).on('click', '.reject-question', function() {
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

    // إعادة فتح سؤال
    $(document).on('click', '.reopen-question', function() {
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

    // تبديل العام/الخاص
    $(document).on('click', '.toggle-public', function() {
        var btn = $(this);
        var url = btn.data('url');

        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    if (response.is_public) {
                        btn.removeClass('btn-secondary').addClass('btn-success');
                        btn.find('i').removeClass('icon-eye-off').addClass('icon-eye');
                    } else {
                        btn.removeClass('btn-success').addClass('btn-secondary');
                        btn.find('i').removeClass('icon-eye').addClass('icon-eye-off');
                    }
                    toastr.success(response.message);
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

// =====================================
// طباعة أسئلة المنتجات
// =====================================
function printQuestionsReport() {
    printTable({
        tableSelector: '#questionsExportTable tbody tr',
        title: 'تقرير أسئلة المنتجات',
        infoCards: [
            { label: 'إجمالي الأسئلة', value: {{ $stats['total'] ?? 0 }} },
            { label: 'قيد الانتظار', value: {{ $stats['pending'] ?? 0 }} },
            { label: 'تم الرد', value: {{ $stats['answered'] ?? 0 }}, class: 'text-success' },
            { label: 'مرفوض', value: {{ $stats['rejected'] ?? 0 }}, class: 'text-danger' },
            { label: 'منشورة', value: {{ $stats['public'] ?? 0 }} }
        ],
        filterInfo: {
            @if (request('search'))
                'بحث': @json((string) request('search')),
            @endif
            @if (request('status'))
                'الحالة': '{{ ['pending' => 'قيد الانتظار', 'answered' => 'تم الرد', 'rejected' => 'مرفوض'][(string) request('status')] ?? '' }}',
            @endif
            @if (request('is_public'))
                'النشر': '{{ request('is_public') == 'yes' ? 'منشور' : 'مخفي' }}',
            @endif
        },
        tableHeaders: ['#', 'المنتج', 'السائل', 'السؤال', 'الإجابة', 'الحالة', 'النشر', 'التاريخ'],
        columnWidths: ['5%', '14%', '13%', '25%', '25%', '8%', '5%', '5%'],
        descriptionColumn: 4,
        windowWidth: 1400,
        windowHeight: 900
    });
}

// =====================================
// تصدير أسئلة المنتجات إلى Excel
// =====================================
function exportQuestionsReport() {
    var tableData = [];

    @foreach ($questions as $question)
        @php
            $exportQStatus2 = $question->status->value ?? $question->status;
            $exportQStatusText2 = match ($exportQStatus2) {
                'pending' => 'قيد الانتظار',
                'answered' => 'تم الرد',
                'rejected' => 'مرفوض',
                default => 'غير معروف',
            };
        @endphp
        tableData.push({
            values: [
                {{ $question->id }},
                @json($question->product->name ?? 'منتج محذوف'),
                @json($askerName($question)),
                @json($question->question),
                @json($question->answer ?? 'لا توجد إجابة'),
                @json($exportQStatusText2),
                @json($question->is_public ? 'منشور' : 'مخفي'),
                @json($question->created_at->format('Y-m-d'))
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        });
    @endforeach

    var config = {
        fileName: 'تقرير_أسئلة_المنتجات',
        mainTitle: 'تقرير أسئلة المنتجات',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            @if (request('search'))
                'بحث': @json((string) request('search')),
            @endif
            @if (request('status'))
                'الحالة': '{{ ['pending' => 'قيد الانتظار', 'answered' => 'تم الرد', 'rejected' => 'مرفوض'][(string) request('status')] ?? '' }}',
            @endif
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },

        statisticsCards: [
            {
                title: 'إجمالي الأسئلة',
                value: '{{ $stats['total'] ?? 0 }}',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'قيد الانتظار',
                value: '{{ $stats['pending'] ?? 0 }}',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            },
            {
                title: 'تم الرد',
                value: '{{ $stats['answered'] ?? 0 }}',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'مرفوض',
                value: '{{ $stats['rejected'] ?? 0 }}',
                titleColor: 'danger',
                valueColor: 'danger',
                backgroundColor: 'dangerLight',
                borderColor: 'danger'
            },
            {
                title: 'منشورة',
                value: '{{ $stats['public'] ?? 0 }}',
                titleColor: 'purple',
                valueColor: 'purple',
                backgroundColor: 'purpleLight',
                borderColor: 'purple'
            }
        ],

        tableTitle: 'قائمة الأسئلة',
        tableHeaders: ['#', 'المنتج', 'السائل', 'السؤال', 'الإجابة', 'الحالة', 'النشر', 'التاريخ'],
        columnWidths: [6, 20, 18, 35, 35, 14, 8, 14],

        tableData: tableData,

        buttonSelector: '#exportQuestionsExcel',
        successMessage: 'تم تصدير تقرير الأسئلة بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
