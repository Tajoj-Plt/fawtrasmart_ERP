{{--
    إضافة موعد جديد — بنفس نظام تصميم الوحدة (`.rsv-scope` + page-head)
    ونفس هيكل نموذج «إضافة عميل» بالضبط (بطاقة حفظ/إلغاء + بطاقات أقسام).

    **إصلاحٌ حقيقي بقي من الجولة السابقة**: حقل «المدّة» كان نصّاً حرّاً
    بقيمة افتراضية "00:00" وإلزاميّاً — بينما تحقّق الخادم يطلب رقماً صحيحاً
    بالدقائق (`integer|min:1|max:1440`)، فكل حفظٍ كان يفشل. صار حقل رقمٍ
    بالدقائق، مبدوءاً بالقيمة الافتراضية من الإعدادات (`$defaultDuration`).
--}}
@extends('client::reservations.master')
@section('title', 'إضافة موعد جديد')

@section('content')

<div class="rsv-scope">
    @include('client::reservations.components.page-head', [
        'title' => 'إضافة موعد جديد',
        'icon' => 'calendar',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'المواعيد' => route('appointments.index'),
            'إضافة' => null,
        ],
    ])

    <form action="{{ route('appointments.store') }}" method="POST" id="appointment-form">
        @csrf

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <label class="mb-0">الحقول التي عليها علامة <span class="text-danger">*</span> إلزامية</label>
                    <div>
                        <a href="{{ $lockedClient ? route('clients.show', $lockedClient->id) : route('appointments.index') }}" class="btn btn-outline-danger">
                            <i class="fa fa-ban"></i> إلغاء
                        </a>
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fa fa-save"></i> حفظ
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 col-12">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">معلومات الموعد</h4></div>
                    <div class="card-content">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="client_id">العميل <span class="text-danger">*</span></label>
                                        @if ($lockedClient)
                                            {{-- من شاشة العميل: العميل محدَّد ولا يُغيَّر من هنا --}}
                                            <input type="hidden" name="client_id" value="{{ $lockedClient->id }}">
                                            <div class="form-control bg-light d-flex align-items-center justify-content-between" style="height:auto">
                                                <span>
                                                    <i class="feather icon-user me-1"></i>
                                                    {{ $lockedClient->display_name }}
                                                    @if ($lockedClient->code)
                                                        <span class="text-muted">— {{ $lockedClient->code }}</span>
                                                    @endif
                                                </span>
                                                <a href="{{ route('appointments.create') }}" class="small">تغيير العميل</a>
                                            </div>
                                        @else
                                            <div class="rsv-select-add">
                                                <select class="form-control select2" id="client_id" name="client_id" required>
                                                    <option value="">اختر العميل</option>
                                                    @foreach ($clients as $client)
                                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>
                                                            {{ $client->display_name }} — {{ $client->code ?? '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @permission('clients_add_client')
                                                    @if (Route::has('clients.create'))
                                                        <a href="{{ route('clients.create') }}" target="_blank" rel="noopener"
                                                            class="btn btn-outline-primary rsv-select-add__btn" title="إضافة عميل جديد">
                                                            <i class="feather icon-plus"></i>
                                                        </a>
                                                    @endif
                                                @endpermission
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date">التاريخ <span class="text-danger">*</span></label>
                                        {{-- `$presetDate` من نقرة يومٍ في التقويم (`?date=`) --}}
                                        <input type="date" class="form-control" id="date" name="date"
                                            value="{{ old('date', $presetDate ?? date('Y-m-d')) }}" @unless ($allowPastDates) min="{{ date('Y-m-d') }}" @endunless required>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="time">الوقت <span class="text-danger">*</span></label>
                                        <input type="time" class="form-control" id="time" name="time"
                                            value="{{ old('time') }}" required>
                                        @if ($workingHours['enforced'] ?? false)
                                            <small class="form-text text-muted">
                                                <i class="feather icon-clock"></i>
                                                ساعات العمل: {{ $workingHours['start'] }} — {{ $workingHours['end'] }}
                                            </small>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="duration">المدّة (دقيقة) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="duration" name="duration"
                                            min="1" max="1440" value="{{ old('duration', $defaultDuration) }}" required>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="action_type">نوع الإجراء <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="action_type" name="action_type" required>
                                            <option value="">اختر نوع الإجراء</option>
                                            <option value="add_new" class="text-primary">+ تعديل قائمة الإجراءات</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-12">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">لمن الموعد ومن يعلم به</h4></div>
                    <div class="card-content">
                        <div class="card-body">
                            @include('client::reservations.appointments.partials.assignment-fields', [
                                'selectedEmployees' => $selectedEmployees,
                                'share' => (bool) old('share_with_client'),
                                'recurring' => (bool) old('is_recurring'),
                                'recurrenceType' => old('recurrence_type', 1),
                                'recurrenceDate' => old('recurrence_date'),
                            ])

                            <div class="form-group mt-2 mb-0">
                                <label for="notes">الملاحظات/الشروط</label>
                                <textarea id="tinyMCE" name="notes" class="form-control" rows="4">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal تعديل الإجراءات -->
        <div class="modal fade" id="proceduresModal" tabindex="-1" aria-labelledby="proceduresModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="proceduresModalLabel">تعديل قائمة الإجراءات</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="procedures-list"></div>
                        <div class="mt-3">
                            <div class="input-group">
                                <input type="text" class="form-control" id="newProcedureName" placeholder="اسم الإجراء الجديد">
                                <button class="btn btn-primary" type="button" id="addProcedureBtn">
                                    <i class="fas fa-plus"></i> إضافة
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="button" class="btn btn-success" id="saveProcedures">حفظ</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    @parent
    <script>
        $(document).ready(function() {
            /* `$('.select2').select2()` تُشغَّل تلقائياً على مستوى الموقع
               كاملاً (`public/assets/js/scripts.js`) — إعادة تهيئتها هنا
               بخياراتٍ مختلفة (`dir:'rtl'`) على عنصرٍ مُهيَّأً بالفعل هي ما
               كان يُفسد شكل القوائم؛ select2 يتجاهل التهيئة الثانية أو
               يتعارض معها بدل أن يستبدلها. */

            let procedures = ['متابعة', 'تدقيق', 'مراجعة', 'اجتماع', 'زيارة', 'ملاحظة'];
            // بعد خطأ تحقّق: القائمةُ تُبنى هنا، فيضيع الاختيار ما لم يُعَد
            let oldAction = @json(old('action_type'));
            if (oldAction && oldAction !== 'add_new' && !procedures.includes(oldAction)) {
                procedures.push(oldAction);
            }

            updateSelectOptions();

            $('#addProcedureBtn').on('click', function() {
                const name = $('#newProcedureName').val().trim();
                if (name && procedures.length < 6) {
                    procedures.push(name);
                    updateProceduresList();
                    updateSelectOptions();
                    $('#newProcedureName').val('');
                } else if (procedures.length >= 6) {
                    alert('لا يمكن إضافة أكثر من 6 إجراءات');
                }
            });

            function updateProceduresList() {
                let listHtml = '';
                procedures.forEach((proc, index) => {
                    listHtml += `
                <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                    <span>${proc}</span>
                    <button class="btn btn-sm btn-outline-danger delete-procedure" data-index="${index}">
                        <i class="fas fa-trash"></i> حذف
                    </button>
                </div>`;
                });
                $('#procedures-list').html(listHtml);
            }

            $('#proceduresModal').on('show.bs.modal', function() {
                updateProceduresList();
            });

            $(document).on('click', '.delete-procedure', function() {
                const index = $(this).data('index');
                procedures.splice(index, 1);
                updateProceduresList();
                updateSelectOptions();
            });

            function updateSelectOptions() {
                let selectHtml = '<option value="">اختر نوع الإجراء</option>';
                procedures.forEach(proc => {
                    selectHtml += `<option value="${proc}">${proc}</option>`;
                });
                selectHtml += '<option value="add_new" class="text-primary">+ تعديل قائمة الإجراءات</option>';
                $('#action_type').html(selectHtml);
                if (oldAction) {
                    $('#action_type').val(oldAction).trigger('change.select2');
                    oldAction = null;
                }
            }

            $('#saveProcedures').on('click', function() {
                $('#proceduresModal').modal('hide');
            });

            $('#newProcedureName').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#addProcedureBtn').click();
                }
            });

            $('#action_type').on('change', function() {
                if ($(this).val() === 'add_new') {
                    $('#proceduresModal').modal('show');
                    $(this).val('');
                }
            });
        });

        function toggleRecurringFields(checkbox) {
            document.getElementById('recurring-fields').style.display = checkbox.checked ? 'flex' : 'none';
        }

    </script>
@endsection
