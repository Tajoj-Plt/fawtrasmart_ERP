@extends('taskmanager::master')

@section('title')
    إضافة مشروع جديد
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إضافة مشروع جديد</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.manager') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">المشاريع</a></li>
                            <li class="breadcrumb-item active">إضافة</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <div class="pm-wizard-card">
                    {{-- شريط الخطوات --}}
                    <div class="pm-stepper" id="pmStepper">
                        <div class="pm-step active" data-step="1">
                            <span class="pm-step-circle"><i class="feather icon-check pm-step-check"></i><span class="pm-step-num">1</span></span>
                            <span class="pm-step-label">طريقة الإدارة</span>
                        </div>
                        <div class="pm-step-connector"></div>
                        <div class="pm-step" data-step="2">
                            <span class="pm-step-circle"><i class="feather icon-check pm-step-check"></i><span class="pm-step-num">2</span></span>
                            <span class="pm-step-label">الأساسيات</span>
                        </div>
                        <div class="pm-step-connector"></div>
                        <div class="pm-step" data-step="3">
                            <span class="pm-step-circle"><i class="feather icon-check pm-step-check"></i><span class="pm-step-num">3</span></span>
                            <span class="pm-step-label">الجدولة</span>
                        </div>
                        <div class="pm-step-connector"></div>
                        <div class="pm-step" data-step="4">
                            <span class="pm-step-circle"><i class="feather icon-check pm-step-check"></i><span class="pm-step-num">4</span></span>
                            <span class="pm-step-label">الفريق</span>
                        </div>
                        <div class="pm-step-connector"></div>
                        <div class="pm-step" data-step="5">
                            <span class="pm-step-circle"><i class="feather icon-check pm-step-check"></i><span class="pm-step-num">5</span></span>
                            <span class="pm-step-label">المراجعة</span>
                        </div>
                    </div>

                    <form id="project-form">
                        @csrf

                        {{-- ===================== الخطوة 1: طريقة الإدارة ===================== --}}
                        <section class="pm-panel" data-panel="1">
                            <h4 class="pm-panel-title"><i class="feather icon-git-branch"></i> طريقة إدارة المشروع</h4>
                            <p class="pm-panel-subtitle">اختر الأسلوب الذي يناسب طبيعة عمل فريقك — لا يمكن تغيير هذا الخيار بعد إنشاء المشروع</p>

                            <input type="hidden" name="management_type" id="managementTypeInput" value="scrum" required>

                            <div class="pm-method-grid">
                                <button type="button" class="pm-method-card selected" data-value="scrum">
                                    <span class="pm-method-check"><i class="feather icon-check"></i></span>
                                    <div class="pm-method-icon pm-method-icon-scrum"><i class="feather icon-repeat"></i></div>
                                    <h5 class="pm-method-title">أجايل سكرم</h5>
                                    <p class="pm-method-desc">لفرق التطوير المنهجية التي تعمل بسباقات (سبرنتات) متكررة</p>
                                    <ul class="pm-method-features">
                                        <li><i class="feather icon-check-circle"></i> قائمة المهام (Product Backlog)</li>
                                        <li><i class="feather icon-check-circle"></i> ملاحم وقصص مستخدم (Epics & Stories)</li>
                                        <li><i class="feather icon-check-circle"></i> تخطيط وتنفيذ السبرنت</li>
                                        <li><i class="feather icon-check-circle"></i> مراجعة واستعادة السبرنت</li>
                                        <li><i class="feather icon-check-circle"></i> مقاييس سكرم (Velocity, Burndown)</li>
                                    </ul>
                                </button>

                                <button type="button" class="pm-method-card" data-value="manual">
                                    <span class="pm-method-check"><i class="feather icon-check"></i></span>
                                    <div class="pm-method-icon pm-method-icon-normal"><i class="feather icon-list"></i></div>
                                    <h5 class="pm-method-title">إدارة مهام عادية</h5>
                                    <p class="pm-method-desc">لفرق العمل التي تحتاج تتبعاً بسيطاً ومباشراً للمهام بدون طقوس سكرم</p>
                                    <ul class="pm-method-features">
                                        <li><i class="feather icon-check-circle"></i> مهام ومهام فرعية</li>
                                        <li><i class="feather icon-check-circle"></i> تكليفات وأولويات ومواعيد نهائية</li>
                                        <li><i class="feather icon-check-circle"></i> لوحة كانبان تفاعلية</li>
                                        <li><i class="feather icon-check-circle"></i> تقويم وجدول زمني</li>
                                        <li><i class="feather icon-check-circle"></i> تقارير</li>
                                    </ul>
                                </button>
                            </div>
                        </section>

                        {{-- ===================== الخطوة 2: الأساسيات ===================== --}}
                        <section class="pm-panel" data-panel="2" hidden>
                            <h4 class="pm-panel-title"><i class="feather icon-file-text"></i> أساسيات المشروع</h4>
                            <p class="pm-panel-subtitle">ابدأ بمعلومات المشروع الأساسية ومساحة العمل التي سينتمي إليها</p>

                            <div class="form-group">
                                <label>اسم المشروع <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="أدخل اسم المشروع" required>
                                <small class="form-text text-muted">مثال: تطوير موقع الشركة، حملة تسويقية، نظام إدارة المحتوى</small>
                            </div>

                            <div class="form-group">
                                <label>وصف المشروع</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="وصف تفصيلي للمشروع والأهداف المطلوب تحقيقها..."></textarea>
                            </div>

                            <div class="form-group">
                                <label>مساحة العمل <span class="text-danger">*</span></label>
                                <select name="workspace_id" class="form-control select2" required>
                                    <option value="">اختر مساحة العمل</option>
                                    @foreach($workspaces as $workspace)
                                        <option value="{{ $workspace->id }}">{{ $workspace->title }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">المساحة التي سيتم تخصيص المشروع لها</small>
                            </div>
                        </section>

                        {{-- ===================== الخطوة 3: الجدولة ===================== --}}
                        <section class="pm-panel" data-panel="3" hidden>
                            <h4 class="pm-panel-title"><i class="feather icon-calendar"></i> التفاصيل والجدولة</h4>
                            <p class="pm-panel-subtitle">حدد الحالة والأولوية والإطار الزمني والميزانية</p>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>الحالة</label>
                                        <select name="status" class="form-control select2">
                                            <option value="planning" selected>تخطيط</option>
                                            <option value="active">نشط</option>
                                            <option value="on_hold">متوقف</option>
                                            <option value="completed">مكتمل</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>الأولوية</label>
                                        <select name="priority" class="form-control select2">
                                            <option value="low">منخفضة</option>
                                            <option value="medium" selected>متوسطة</option>
                                            <option value="high">عالية</option>
                                            <option value="urgent">عاجلة</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>تاريخ البداية <span class="text-danger">*</span></label>
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>تاريخ النهاية <span class="text-danger">*</span></label>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>الميزانية</label>
                                        <div class="input-group">
                                            <input type="number" name="budget" class="form-control" placeholder="0.00" min="0" step="0.01">
                                            <div class="input-group-append"><span class="input-group-text">ر.س</span></div>
                                        </div>
                                        <small class="form-text text-muted">اختياري — يمكن تحديدها لاحقاً</small>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- ===================== الخطوة 4: الفريق ===================== --}}
                        <section class="pm-panel" data-panel="4" hidden>
                            <h4 class="pm-panel-title"><i class="feather icon-users"></i> أعضاء الفريق</h4>
                            <p class="pm-panel-subtitle">اختر الأعضاء الذين سيعملون على هذا المشروع، أو تخطَّ هذه الخطوة وأضفهم لاحقاً</p>

                            <div class="form-group">
                                <label>أعضاء الفريق</label>
                                <select name="team_members[]" class="form-control select2" multiple>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">يمكنك اختيار عدة أعضاء أو تركه فارغاً</small>
                            </div>
                        </section>

                        {{-- ===================== الخطوة 5: المراجعة ===================== --}}
                        <section class="pm-panel" data-panel="5" hidden>
                            <h4 class="pm-panel-title"><i class="feather icon-check-square"></i> مراجعة وتأكيد</h4>
                            <p class="pm-panel-subtitle">تحقق من البيانات قبل إنشاء المشروع</p>

                            <div class="pm-review-grid" id="pmReviewGrid">
                                <div class="pm-review-item">
                                    <span class="pm-review-label">اسم المشروع</span>
                                    <span class="pm-review-value" data-review="title">—</span>
                                </div>
                                <div class="pm-review-item">
                                    <span class="pm-review-label">مساحة العمل</span>
                                    <span class="pm-review-value" data-review="workspace_id">—</span>
                                </div>
                                <div class="pm-review-item">
                                    <span class="pm-review-label">طريقة الإدارة</span>
                                    <span class="pm-review-value" data-review="management_type">—</span>
                                </div>
                                <div class="pm-review-item">
                                    <span class="pm-review-label">الحالة</span>
                                    <span class="pm-review-value" data-review="status">—</span>
                                </div>
                                <div class="pm-review-item">
                                    <span class="pm-review-label">الأولوية</span>
                                    <span class="pm-review-value" data-review="priority">—</span>
                                </div>
                                <div class="pm-review-item">
                                    <span class="pm-review-label">الفترة الزمنية</span>
                                    <span class="pm-review-value" data-review="dates">—</span>
                                </div>
                                <div class="pm-review-item">
                                    <span class="pm-review-label">الميزانية</span>
                                    <span class="pm-review-value" data-review="budget">—</span>
                                </div>
                                <div class="pm-review-item pm-review-item-wide">
                                    <span class="pm-review-label">أعضاء الفريق</span>
                                    <span class="pm-review-value" data-review="team_members">—</span>
                                </div>
                                <div class="pm-review-item pm-review-item-wide">
                                    <span class="pm-review-label">الوصف</span>
                                    <span class="pm-review-value" data-review="description">—</span>
                                </div>
                            </div>
                        </section>

                        {{-- ===================== أزرار التنقل ===================== --}}
                        <div class="pm-wizard-actions">
                            <button type="button" class="btn btn-outline-secondary" id="pmCancelBtn">
                                <i class="feather icon-x"></i> إلغاء
                            </button>
                            <div class="pm-wizard-actions-end">
                                <button type="button" class="btn btn-outline-primary" id="pmPrevBtn" hidden>
                                    <i class="feather icon-arrow-right"></i> السابق
                                </button>
                                <button type="button" class="btn btn-primary" id="pmNextBtn">
                                    التالي <i class="feather icon-arrow-left"></i>
                                </button>
                                <button type="submit" class="btn btn-success" id="pmSubmitBtn" hidden>
                                    <i class="feather icon-save"></i> إنشاء المشروع
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('styles')
    <style>
        .pm-wizard-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 24px rgba(23, 43, 77, 0.08);
            padding: 1.75rem 2rem 2rem;
        }

        /* ---------- Stepper ---------- */
        .pm-stepper {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 2.25rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #f0f1f6;
        }

        .pm-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            flex: 0 0 auto;
            min-width: 70px;
        }

        .pm-step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f1f6;
            color: #8892a8;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.25s ease;
            position: relative;
        }

        .pm-step-check { display: none; font-size: 1rem; }
        .pm-step-num { display: inline; }

        .pm-step.active .pm-step-circle {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            box-shadow: 0 4px 14px rgba(102, 126, 234, 0.4);
            transform: scale(1.08);
        }

        .pm-step.completed .pm-step-circle {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .pm-step.completed .pm-step-check { display: inline; }
        .pm-step.completed .pm-step-num { display: none; }

        .pm-step-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #8892a8;
            text-align: center;
            white-space: nowrap;
        }

        .pm-step.active .pm-step-label { color: #5b4fd6; }
        .pm-step.completed .pm-step-label { color: #2e7d32; }

        .pm-step-connector {
            flex: 1 1 auto;
            height: 2px;
            background: #f0f1f6;
            margin-top: 20px;
            min-width: 20px;
        }

        /* ---------- Panels ---------- */
        .pm-panel {
            animation: pmPanelFade 0.35s ease;
        }

        @keyframes pmPanelFade {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pm-panel-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.35rem;
        }

        .pm-panel-title i { color: #667eea; }

        .pm-panel-subtitle {
            color: #7f8c8d;
            font-size: 0.88rem;
            margin-bottom: 1.5rem;
        }

        /* ---------- Method choice cards ---------- */
        .pm-method-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .pm-method-card {
            position: relative;
            text-align: right;
            background: #fbfbfd;
            border: 2px solid #eef0f5;
            border-radius: 14px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.22s ease;
        }

        .pm-method-card:hover {
            border-color: #d8dcf5;
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(23, 43, 77, 0.08);
        }

        .pm-method-card.selected {
            border-color: #7367f0;
            background: #f6f5ff;
            box-shadow: 0 10px 24px rgba(102, 126, 234, 0.18);
        }

        .pm-method-check {
            position: absolute;
            top: 1rem;
            left: 1rem;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #d8dcf5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: transparent;
            transition: all 0.2s ease;
        }

        .pm-method-card.selected .pm-method-check {
            background: #7367f0;
            border-color: #7367f0;
            color: #fff;
        }

        .pm-method-check i { font-size: 0.75rem; }

        .pm-method-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #fff;
            margin-bottom: 1rem;
        }

        .pm-method-icon-scrum { background: linear-gradient(135deg, #7367f0, #5b4fd6); }
        .pm-method-icon-normal { background: linear-gradient(135deg, #4CAF50, #2E7D32); }

        .pm-method-title { font-weight: 700; color: #2c3e50; margin-bottom: 0.35rem; }
        .pm-method-desc { font-size: 0.83rem; color: #7f8c8d; margin-bottom: 1rem; }

        .pm-method-features {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .pm-method-features li {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            color: #5e6c84;
        }

        .pm-method-features i { color: #7367f0; font-size: 0.85rem; flex-shrink: 0; }

        /* ---------- Review ---------- */
        .pm-review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .pm-review-item {
            background: #fbfbfd;
            border: 1px solid #eef0f5;
            border-radius: 10px;
            padding: 0.85rem 1rem;
        }

        .pm-review-item-wide { grid-column: 1 / -1; }

        .pm-review-label {
            display: block;
            font-size: 0.75rem;
            color: #8892a8;
            margin-bottom: 0.25rem;
        }

        .pm-review-value {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.92rem;
            word-break: break-word;
        }

        /* ---------- Actions ---------- */
        .pm-wizard-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f0f1f6;
        }

        .pm-wizard-actions-end {
            display: flex;
            gap: 0.6rem;
        }

        @media (max-width: 576px) {
            .pm-wizard-card { padding: 1.25rem 1rem 1.5rem; }
            .pm-step-label { display: none; }
            .pm-stepper { justify-content: space-around; }
        }
    </style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ width: '100%', allowClear: true });
    $('select[name="team_members[]"]').select2({ placeholder: 'اختر أعضاء الفريق', allowClear: true, width: '100%' });
    $('select[name="workspace_id"]').select2({ placeholder: 'اختر مساحة العمل', allowClear: false, width: '100%' });

    let today = new Date().toISOString().split('T')[0];
    $('input[name="start_date"]').val(today);

    // ============ منطق المُعالج المرحلي (Stepper) ============
    const totalSteps = 5;
    let currentStep = 1;

    function goToStep(step) {
        $('.pm-panel').attr('hidden', true);
        $('.pm-panel[data-panel="' + step + '"]').removeAttr('hidden');

        $('.pm-step').each(function () {
            const s = parseInt($(this).data('step'));
            $(this).removeClass('active completed');
            if (s < step) $(this).addClass('completed');
            else if (s === step) $(this).addClass('active');
        });

        $('#pmPrevBtn').prop('hidden', step === 1);
        $('#pmNextBtn').prop('hidden', step === totalSteps);
        $('#pmSubmitBtn').prop('hidden', step !== totalSteps);

        if (step === totalSteps) {
            fillReview();
        }

        currentStep = step;
        $('.pm-wizard-card')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function validateStep(step) {
        if (step === 2) {
            if (!$('input[name="title"]').val().trim()) {
                notifyError('يرجى إدخال اسم المشروع');
                return false;
            }
            if (!$('select[name="workspace_id"]').val()) {
                notifyError('يرجى اختيار مساحة العمل');
                return false;
            }
        }

        if (step === 3) {
            const startDate = new Date($('input[name="start_date"]').val());
            const endDate = new Date($('input[name="end_date"]').val());
            if (!$('input[name="start_date"]').val()) {
                notifyError('يرجى تحديد تاريخ البداية');
                return false;
            }
            if (!$('input[name="end_date"]').val()) {
                notifyError('يرجى تحديد تاريخ النهاية');
                return false;
            }
            if (startDate >= endDate) {
                notifyError('تاريخ النهاية يجب أن يكون بعد تاريخ البداية');
                return false;
            }
        }

        return true;
    }

    function notifyError(text) {
        Swal.fire({ title: 'تنبيه', text: text, icon: 'warning', confirmButtonText: 'حسناً', confirmButtonColor: '#ffc107' });
    }

    function fillReview() {
        $('[data-review="title"]').text($('input[name="title"]').val() || '—');
        $('[data-review="workspace_id"]').text($('select[name="workspace_id"] option:selected').text() || '—');
        $('[data-review="management_type"]').text($('#managementTypeInput').val() === 'scrum' ? 'أجايل سكرم' : 'إدارة مهام عادية');
        $('[data-review="status"]').text($('select[name="status"] option:selected').text() || '—');
        $('[data-review="priority"]').text($('select[name="priority"] option:selected').text() || '—');

        const start = $('input[name="start_date"]').val() || '—';
        const end = $('input[name="end_date"]').val() || '—';
        $('[data-review="dates"]').text(start + '  →  ' + end);

        const budget = $('input[name="budget"]').val();
        $('[data-review="budget"]').text(budget ? budget + ' ر.س' : 'غير محددة');

        const members = $('select[name="team_members[]"]').val() || [];
        $('[data-review="team_members"]').text(members.length ? members.length + ' عضو مختار' : 'لم يتم اختيار أعضاء');

        $('[data-review="description"]').text($('textarea[name="description"]').val().trim() || 'لا يوجد وصف');
    }

    $('#pmNextBtn').on('click', function () {
        if (!validateStep(currentStep)) return;
        if (currentStep < totalSteps) goToStep(currentStep + 1);
    });

    $('#pmPrevBtn').on('click', function () {
        if (currentStep > 1) goToStep(currentStep - 1);
    });

    $('#pmCancelBtn').on('click', function () {
        window.history.back();
    });

    // منع إرسال النموذج عبر Enter من أي خطوة غير الأخيرة
    $('#project-form').on('keydown', 'input, select', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (currentStep < totalSteps) {
                $('#pmNextBtn').trigger('click');
            }
        }
    });

    // ============ اختيار طريقة الإدارة ============
    $('.pm-method-card').on('click', function () {
        $('.pm-method-card').removeClass('selected');
        $(this).addClass('selected');
        $('#managementTypeInput').val($(this).data('value'));
    });

    // ============ تنبيه التكلفة أعلى من الميزانية (إن استُخدم لاحقاً) ============
    $('input[name="budget"]').on('input', function() {
        let budget = parseFloat($(this).val()) || 0;
        let cost = parseFloat($('input[name="cost"]').val()) || 0;
        if (cost > budget && budget > 0) {
            Swal.fire({ title: 'تنبيه', text: 'التكلفة الحالية أكبر من الميزانية المحددة', icon: 'warning', timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
        }
    });

    // ============ إرسال النموذج ============
    $('#project-form').on('submit', function(e) {
        e.preventDefault();

        if (!validateStep(2) || !validateStep(3)) {
            return;
        }

        let submitBtn = $('#pmSubmitBtn');

        let formData = {
            title: $('input[name="title"]').val().trim(),
            description: $('textarea[name="description"]').val().trim(),
            workspace_id: $('select[name="workspace_id"]').val(),
            management_type: $('#managementTypeInput').val(),
            status: $('select[name="status"]').val(),
            priority: $('select[name="priority"]').val(),
            start_date: $('input[name="start_date"]').val(),
            end_date: $('input[name="end_date"]').val(),
            budget: $('input[name="budget"]').val() || 0,
            team_members: $('select[name="team_members[]"]').val() || [],
            _token: $('input[name="_token"]').val()
        };

        submitBtn.prop('disabled', true).html('<i class="feather icon-loader"></i> جاري الحفظ...');

        $.ajax({
            url: '{{ route("projects.store") }}',
            method: 'POST',
            data: formData,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        })
        .done(function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'تم بنجاح!',
                    text: response.message || 'تم إنشاء المشروع بنجاح',
                    icon: 'success',
                    confirmButtonText: 'موافق',
                    confirmButtonColor: '#28a745'
                }).then((result) => {
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        window.location.href = '{{ route("projects.index") }}';
                    }
                });
            } else {
                Swal.fire({
                    title: 'خطأ!',
                    text: response.message || 'حدث خطأ أثناء إنشاء المشروع',
                    icon: 'error',
                    confirmButtonText: 'موافق',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .fail(function(xhr) {
            console.error('Status:', xhr.status, 'Response:', xhr.responseJSON);

            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                const errors = xhr.responseJSON.errors;
                const firstField = Object.keys(errors)[0];
                const firstMessage = errors[firstField][0];

                // أعد المستخدم إلى الخطوة التي تحتوي على الحقل الخاطئ
                const fieldStepMap = {
                    management_type: 1,
                    title: 2, workspace_id: 2, description: 2,
                    status: 3, priority: 3, start_date: 3, end_date: 3, budget: 3,
                    team_members: 4,
                };
                const targetStep = fieldStepMap[firstField] || 1;
                goToStep(targetStep);

                Swal.fire({
                    title: 'تحقق من البيانات',
                    text: firstMessage,
                    icon: 'error',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#dc3545'
                });
            } else {
                Swal.fire({
                    title: 'خطأ في النظام',
                    text: (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ غير متوقع أثناء إنشاء المشروع',
                    icon: 'error',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .always(function() {
            submitBtn.prop('disabled', false).html('<i class="feather icon-save"></i> إنشاء المشروع');
        });
    });

    goToStep(1);
});
</script>
@endsection
