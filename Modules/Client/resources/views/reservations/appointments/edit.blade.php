{{--
    تعديل موعد — نفس هيكل create.blade.php بنظام تصميم الوحدة الحقيقي.

    (`AppointmentController::update()` أُصلحت في جولةٍ سابقة: كانت بلا
    معرِّف موعدٍ في توقيعها فيفشل كل تحديثٍ بـModelNotFoundException؛ وحقل
    التاريخ هنا يُنسَّق `Y-m-d` صراحةً لأن العمود مصبوغٌ `datetime` على
    النموذج، و`<input type="date">` يرفض أي صيغة أخرى بصمت.)
--}}
@extends('client::reservations.master')
@section('title', 'تعديل موعد')

@section('content')

<div class="rsv-scope">
    @include('client::reservations.components.page-head', [
        'title' => 'تعديل موعد',
        'icon' => 'edit-3',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'المواعيد' => route('appointments.index'),
            'تعديل' => null,
        ],
    ])

    <form action="{{ route('appointments.update', $appointment->id) }}" method="POST" id="appointment-form">
        @csrf
        @method('PUT')

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
                        <a href="{{ route('appointments.index') }}" class="btn btn-outline-danger">
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
                                        <div class="rsv-select-add">
                                            <select class="form-control select2" id="client_id" name="client_id" required>
                                                @forelse ($clients as $client)
                                                    <option value="{{ $client->id }}" @selected($appointment->client_id == $client->id)>
                                                        {{ $client->trade_name }} — {{ $client->code ?? '' }}
                                                    </option>
                                                @empty
                                                    <option value="">لا يوجد عملاء متاحون حالياً</option>
                                                @endforelse
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
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date">التاريخ <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="date" name="date"
                                            value="{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d') }}"
                                            @if (! $allowPastDates && \Carbon\Carbon::parse($appointment->appointment_date)->startOfDay()->gte(today())) min="{{ date('Y-m-d') }}" @endif required>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="time">الوقت <span class="text-danger">*</span></label>
                                        {{-- `H:i` لا `H:i:s`: عمود TIME يعيد الثواني، والمتصفّح يعيدها كما جاءت فترفضها القاعدة --}}
                                        <input type="time" class="form-control" id="time" name="time"
                                            value="{{ old('time', substr((string) $appointment->time, 0, 5)) }}" required>
                                        @if ($workingHours['enforced'])
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
                                            {{-- صفوفٌ قديمة تحمل `00:00` في العمود النصّي — تُعرض بالافتراضي بدل قيمةٍ يرفضها الحقل --}}
                                            min="1" max="1440" value="{{ old('duration', (int) $appointment->duration > 0 ? (int) $appointment->duration : $defaultDuration) }}" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status">الحالة <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="status" name="status" required>
                                            <option value="1" @selected((int) $appointment->status === 1 || empty($appointment->status))>قيد الانتظار</option>
                                            <option value="2" @selected((int) $appointment->status === 2)>مكتمل</option>
                                            <option value="3" @selected((int) $appointment->status === 3)>ملغي</option>
                                            <option value="4" @selected((int) $appointment->status === 4)>معاد جدولته</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="action_type">نوع الإجراء</label>
                                        <select class="form-control select2" id="action_type" name="action_type">
                                            <option value="">-- اختر الإجراء --</option>
                                            <option value="متابعة" @selected($appointment->action_type == 'متابعة')>متابعة</option>
                                            <option value="تدقيق" @selected($appointment->action_type == 'تدقيق')>تدقيق</option>
                                            <option value="مراجعة" @selected($appointment->action_type == 'مراجعة')>مراجعة</option>
                                            <option value="اجتماع" @selected($appointment->action_type == 'اجتماع')>اجتماع</option>
                                            <option value="زيارة" @selected($appointment->action_type == 'زيارة')>زيارة</option>
                                            <option value="ملاحظة" @selected($appointment->action_type == 'ملاحظة')>ملاحظة</option>
                                            @if ($appointment->action_type && ! in_array($appointment->action_type, ['متابعة', 'تدقيق', 'مراجعة', 'اجتماع', 'زيارة', 'ملاحظة']))
                                                <option value="{{ $appointment->action_type }}" selected>{{ $appointment->action_type }}</option>
                                            @endif
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
                                'share' => (bool) old('share_with_client', $appointment->share_with_client),
                                'recurring' => (bool) old('is_recurring', $appointment->is_recurring),
                                'recurrenceType' => old('recurrence_type', $appointment->recurrence_type ?: 1),
                                'recurrenceDate' => old('recurrence_date', $appointment->recurrence_date),
                                'isOccurrence' => (bool) $appointment->recurrence_parent_id,
                            ])

                            <div class="form-group mt-2 mb-0">
                                <label for="notes">الملاحظات/الشروط</label>
                                <textarea id="tinyMCE" name="notes" class="form-control" rows="4">{{ old('notes', $appointment->notes) }}</textarea>
                            </div>
                        </div>
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
        function toggleRecurringFields(checkbox) {
            document.getElementById('recurring-fields').style.display = checkbox.checked ? 'flex' : 'none';
        }


        $(document).ready(function() {
            /* `scripts.js` يهيّئ `.select2` تلقائياً على مستوى الموقع؛
               إعادة التهيئة هنا بخياراتٍ مختلفة كانت تُفسد شكل القوائم. */
        });
    </script>
@endsection
