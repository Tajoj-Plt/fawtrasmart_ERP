<!-- بطاقة رأس العميل -->
@php
    $clientDisplayName = $client->trade_name ?: trim($client->first_name . ' ' . $client->last_name);
    $clientInitial = mb_substr($clientDisplayName ?: '?', 0, 1);

    $clientNotifications = \App\Models\notifications::where('client_id', $client->id)
        ->where('type', 'client_popup')
        ->get();
    $unreadCount = $clientNotifications->where('read', 0)->count();
    $readCount = $clientNotifications->where('read', 1)->count();

    $branchTreePaths = $client->branchTreeDisplayPaths();
    $latestRelation = $client->latestRelation;
    $assignedEmployees = $client->employees ?? collect();

    $currentStatus = $statuses->firstWhere('id', $client->status_id);

    $clientTypeMap = [1 => ['label' => 'فرد', 'icon' => 'fa-user'], 2 => ['label' => 'شركة', 'icon' => 'fa-building']];
    $clientTypeInfo = $clientTypeMap[$client->client_type] ?? null;

    $clientCategory = $client->categoriesClient;

    $creditLimitValue = $creditLimit->value ?? null;

    $hasApprovePermissionHeader = false;
    try {
        $hasApprovePermissionHeader = hasPermission('clients_approve_status');
    } catch (\Exception $e) {
        $hasApprovePermissionHeader = false;
    }
    /* من يحتاج اعتماداً؟ من لا يملك صلاحية الاعتماد والإعدادُ يشترطه — وكفى.
       كان الشرط يبدأ بـ`isEmployee()` وهي حرفياً `users.employee_id !== null`:
       مستخدمٌ بلا ملفّ موظّف يملك `clients_change_status` ولا يملك الاعتماد كان
       يرى منسدلةً تُرسِل مباشرةً، تحت إعدادٍ اسمُه «يلزم اعتماد المدير» وهو
       مفعَّل. الشرط الآن مطابقٌ حرفياً لما يقرّره
       `ClientController::updateStatusClient()` بالخادم — واختلافُهما كان
       يُنتج نموذجاً بلا `manager_id` يردّه الخادم برسالة «يرجى اختيار المسؤول». */
    $needsApprovalHeader = !$hasApprovePermissionHeader
        && \App\Helpers\ClientSettingsHelper::clientStatusChangeRequiresApproval();

    /* المُعتمِدون: حاملو `clients_approve_status` عبر `users.role_id` — كانت
       القائمة `User::role('manager')` أي اسمَ الدور الحرفيّ وحده، فمنشأةٌ
       سمّت دورها «مشرف» تُظهِر قائمةً فارغة فلا يُرسَل طلبٌ أبداً. */
    $statusApproversHeader = \App\Models\ClientStatusRequest::approvers();
@endphp

<div class="cx-header">
    <div class="cx-header-row">
        <div class="cx-identity">
            <div class="cx-avatar">{{ $clientInitial }}</div>
            <div class="cx-title-block">
                <div class="cx-title-line">
                    <h1 class="cx-name">{{ $clientDisplayName }}</h1>
                    <span class="cx-code">#{{ $client->code }}</span>
                </div>

                <div class="cx-badges">
                    {{-- حالة العميل --}}
                    @if (hasPermission('clients_change_status'))
                        @if ($needsApprovalHeader)
                            <select id="clientStatusSelectApproval" class="form-select form-select-sm" style="width: auto; border-radius: 999px; font-weight: 600; font-size: .78rem; padding: .3rem .8rem;
                                    {{ $currentStatus ? 'background:' . ($currentStatus->color ?: '#7367f0') . '22; color:' . ($currentStatus->color ?: '#7367f0') . '; border-color:transparent;' : '' }}">
                                {{-- لا خيارَ «بدون حالة»: كلُّ عميلٍ يُنشأ بحالةٍ افتراضية
                                     مضبوطة في إعدادات وحدات العملاء، فالفراغُ ليس حالةً
                                     يختارها المستخدم. ويبقى سطرُ «اختر الحالة» المعطَّل
                                     نائباً عن عميلٍ قديمٍ لا حالةَ له، بلا أن يمكِّن من
                                     إفراغها من جديد. --}}
                                @unless ($client->status_id)
                                    <option value="" selected disabled>اختر الحالة</option>
                                @endunless
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}" @if ($client->status_id == $status->id) selected @endif>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <form method="POST" action="{{ route('clients.updateStatusClient') }}" class="d-inline-flex">
                                @csrf
                                <input type="hidden" name="client_id" value="{{ $client->id }}">
                                <select name="status_id" class="form-select form-select-sm" onchange="this.form.submit()"
                                    style="width: auto; border-radius: 999px; font-weight: 600; font-size: .78rem; padding: .3rem .8rem;
                                        {{ $currentStatus ? 'background:' . ($currentStatus->color ?: '#7367f0') . '22; color:' . ($currentStatus->color ?: '#7367f0') . '; border-color:transparent;' : '' }}">
                                    {{-- لا خيارَ «بدون حالة»: كلُّ عميلٍ يُنشأ بحالةٍ افتراضية
                                     مضبوطة في إعدادات وحدات العملاء، فالفراغُ ليس حالةً
                                     يختارها المستخدم. ويبقى سطرُ «اختر الحالة» المعطَّل
                                     نائباً عن عميلٍ قديمٍ لا حالةَ له، بلا أن يمكِّن من
                                     إفراغها من جديد. --}}
                                    @unless ($client->status_id)
                                    <option value="" selected disabled>اختر الحالة</option>
                                    @endunless
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->id }}" @if ($client->status_id == $status->id) selected @endif>{{ $status->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                    @else
                        <span class="cx-pill cx-pill-muted">
                            <i class="fa fa-circle"></i> {{ $currentStatus->name ?? 'غير محدد' }}
                        </span>
                    @endif

                    {{-- نوع العميل --}}
                    @if ($clientTypeInfo)
                        <span class="cx-pill cx-pill-muted"><i class="fa {{ $clientTypeInfo['icon'] }}"></i> {{ $clientTypeInfo['label'] }}</span>
                    @endif

                    {{-- تصنيف العميل --}}
                    @if ($clientCategory)
                        <span class="cx-pill cx-pill-primary"><i class="fa fa-tag"></i> {{ $clientCategory->name }}</span>
                    @endif

                    {{-- هدف العميل --}}
                    @if ($client->customerTarget)
                        <span class="cx-pill" style="background: {{ $client->customerTarget->color }}22; color: {{ $client->customerTarget->color }};">
                            <i class="fa fa-bullseye"></i> {{ $client->customerTarget->name }}
                        </span>
                    @endif

                    {{-- وضع العميل --}}
                    @if ($client->registration_stage_id && $client->registrationStage)
                        <span class="cx-pill" style="background: {{ $client->registrationStage->color ?: '#ff9f43' }}22; color: {{ $client->registrationStage->color ?: '#ff9f43' }};">
                            <i class="fa fa-hourglass-half"></i> {{ $client->registrationStage->name }}
                        </span>
                        {{-- زرّ «اعتماد» بجانب الوضع أُزيل (2026-09-15) بطلب المستخدم: لا فائدة
                             منه في الشاشة. مسار `clients.approveRegistrationStage` باقٍ بلا رابط. --}}
                    @endif

                    {{-- إشعارات العميل --}}
                    @if ($unreadCount > 0)
                        <span class="cx-pill cx-pill-danger" data-bs-toggle="tooltip" title="إشعارات غير مقروءة">
                            <i class="fa fa-bell"></i> {{ $unreadCount }} غير مقروء
                        </span>
                    @endif
                    @if ($readCount > 0)
                        <span class="cx-pill cx-pill-muted" data-bs-toggle="tooltip" title="إشعارات مقروءة">
                            <i class="fa fa-check-circle"></i> {{ $readCount }} مقروء
                        </span>
                    @endif
                </div>

                <div class="cx-sub-line">
                    @if ($client->account_client && $client->account_client->client_id == $client->id)
                        <a href="{{ route('journal.generalLedger', ['account_id' => $client->account_client->id]) }}">
                            <i class="fa fa-book"></i> حساب الأستاذ: {{ $client->account_client->name }} - {{ $client->account_client->code }}
                            <i class="fa fa-external-link-alt ms-1"></i>
                        </a>
                    @else
                        <span class="text-danger"><i class="fa fa-exclamation-triangle me-1"></i> لا يوجد حساب مرتبط</span>
                    @endif
                    @if ($client->target_evaluated_at)
                        <span><i class="fa fa-history"></i> آخر تقييم هدف: {{ \Carbon\Carbon::parse($client->target_evaluated_at)->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="cx-header-actions">
            @include('client::partials.actions-buttons')
        </div>
    </div>

    {{-- طلب اعتماد الحالة (يظهر مودالاً عند اختيار الموظف حالةً جديدة) --}}
    @if (hasPermission('clients_change_status') && $needsApprovalHeader)
        <div class="modal fade" id="statusApprovalModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title text-white"><i class="fas fa-paper-plane me-2"></i> طلب تغيير حالة العميل</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('clients.updateStatusClient') }}">
                        @csrf
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="status_id" id="modal_status_id">

                        <div class="modal-body">
                            <div class="mb-3 text-center">
                                <span class="badge bg-light-primary text-primary fs-6 p-2">
                                    الحالة المطلوبة: <strong id="selectedStatusName"></strong>
                                </span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">اختر المسؤول المعتمد <span class="text-danger">*</span></label>
                                <select name="manager_id" class="form-select" required>
                                    <option value="">اختر المسؤول...</option>
                                    @foreach($statusApproversHeader as $manager)
                                        <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                                    @endforeach
                                </select>
                                @if ($statusApproversHeader->isEmpty())
                                    <small class="text-danger">لا يوجد مستخدمٌ يملك صلاحية «اعتماد تغيير حالة العميل» — لا يمكن رفع الطلب حتى تُمنَح لأحدٍ من شاشة الصلاحيات.</small>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">سبب تغيير الحالة <span class="text-danger">*</span></label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="اكتب هنا سبب تغيير الحالة..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-share-square me-1"></i> إرسال الطلب</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const selectEl = document.getElementById('clientStatusSelectApproval');
                if (!selectEl) return;
                selectEl.addEventListener('change', function () {
                    const val = this.value;
                    const currentStatusId = "{{ $client->status_id }}";
                    if (val && val !== currentStatusId) {
                        document.getElementById('modal_status_id').value = val;
                        const text = this.options[this.selectedIndex].text;
                        document.getElementById('selectedStatusName').innerText = text;

                        const myModal = new bootstrap.Modal(document.getElementById('statusApprovalModal'));
                        myModal.show();
                    }
                });
            });
        </script>
    @endif

    {{-- شبكة المعلومات السريعة --}}
    <div class="cx-meta-grid">
        <div class="cx-meta-item">
            <span class="cx-meta-icon"><i class="fa fa-phone"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">الهاتف / الجوال</span>
                <span class="cx-meta-value">{{ $client->mobile ?: ($client->phone ?: 'غير محدد') }}</span>
            </span>
        </div>

        <div class="cx-meta-item">
            <span class="cx-meta-icon"><i class="fa fa-envelope"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">البريد الإلكتروني</span>
                <span class="cx-meta-value">{{ $client->email ?: 'غير محدد' }}</span>
            </span>
        </div>

        <div class="cx-meta-item cx-meta-item--wide">
            <span class="cx-meta-icon"><i class="fa fa-sitemap"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">تابع لـ</span>
                <span class="cx-meta-value" title="{{ $branchTreePaths['main'] ?? '' }}">{{ $branchTreePaths['main'] ?? 'غير محدد' }}</span>
            </span>
        </div>

        <div class="cx-meta-item">
            <span class="cx-meta-icon"><i class="fa fa-map-marker-alt"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">الحي</span>
                <span class="cx-meta-value">{{ optional($client->neighborhood)->name ?? 'غير محدد' }}</span>
            </span>
        </div>

        {{-- حُذف «مندوب العميل»: كان يقرأ `clients.employee_id` — عمودٌ
             قديمٌ يحمل مندوباً واحداً، بينما الإسنادُ الحقيقيّ متعدّدٌ في
             `client_employee` ويُعرَض أسفلَ هذه الشبكة تحت «الموظفون
             المعيَّنون». فكان في الشاشة مصدران لنفس المعنى يتناقضان. --}}

        <div class="cx-meta-item">
            <span class="cx-meta-icon"><i class="fa fa-clock"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">دوام موظف العميل</span>
                <span class="cx-meta-value">
                    @if($latestRelation && \App\Models\ClientEmployeeNote::isFullTimeRange(substr((string) $latestRelation->shift_start, 0, 5), substr((string) $latestRelation->shift_end, 0, 5)))
                        {{-- «دوام كامل» يُخزَّن 00:00 → 24:00، وCarbon يقرأ 24:00 صباحَ الغد فيعرض «12:00 ص - 12:00 ص» --}}
                        {{ \App\Models\ClientEmployeeNote::FULL_TIME_LABEL }}
                    @elseif($latestRelation && $latestRelation->shift_start && $latestRelation->shift_end)
                        @php
                            $start = \Carbon\Carbon::parse($latestRelation->shift_start);
                            $end = \Carbon\Carbon::parse($latestRelation->shift_end);
                            $fmt = fn($t) => $t->format('g:i') . ($t->format('A') === 'AM' ? ' ص' : ' م');
                        @endphp
                        {{ $fmt($start) }} - {{ $fmt($end) }}
                    @else
                        غير محدد
                    @endif
                </span>
            </span>
        </div>

        <div class="cx-meta-item">
            <span class="cx-meta-icon"><i class="fa fa-coins"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">الرصيد المستحق</span>
                <span class="cx-meta-value {{ ($due ?? 0) > 0 ? 'text-danger-emphasis' : '' }}">
                    {{ number_format($due ?? 0, 2) }} {!! $currencySymbol ?? ($account_setting->currency ?? 'SAR') !!}
                </span>
            </span>
        </div>

        <div class="cx-meta-item">
            <span class="cx-meta-icon"><i class="fa fa-dollar-sign"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">الحد الائتماني</span>
                <span class="cx-meta-value">
                    {{ $creditLimitValue !== null ? number_format($creditLimitValue, 2) . ' ' . ($account_setting->currency ?? 'SAR') : 'غير محدد' }}
                </span>
            </span>
        </div>

        {{-- المدّة الائتمانية — تُضبَط من نفس نافذة «الحد الائتماني» في
             أزرار هذه الشاشة، فتُعرَض إلى جانبه لا في شاشةٍ أخرى --}}
        <div class="cx-meta-item">
            <span class="cx-meta-icon"><i class="fa fa-hourglass-half"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">المدة الائتمانية</span>
                <span class="cx-meta-value">
                    {{ $client->credit_period !== null && $client->credit_period !== '' ? $client->credit_period . ' يوم' : 'غير محدد' }}
                </span>
            </span>
        </div>

        <div class="cx-meta-item">
            <span class="cx-meta-icon"><i class="fa fa-calendar-plus"></i></span>
            <span class="cx-meta-text">
                <span class="cx-meta-label">تاريخ الإنشاء</span>
                <span class="cx-meta-value">{{ $client->created_at ? $client->created_at->format('Y-m-d') : 'غير محدد' }}</span>
            </span>
        </div>
    </div>

    {{-- الموظفون المعينون --}}
    <div class="cx-assigned">
        <span class="cx-meta-label"><i class="fa fa-user-tie me-1"></i> الموظفون المعينون:</span>
        @if($assignedEmployees->count() > 0)
            <div style="display: contents;">
                @foreach($assignedEmployees as $employee)
                    <span class="cx-chip">
                        {{ $employee->full_name }}
                        <form action="{{ route('clients.remove-employee', $client->id) }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            <button type="submit" title="إزالة الموظف" onclick="return confirm('هل تريد إزالة هذا الموظف؟');">
                                <i class="fa fa-times-circle"></i>
                            </button>
                        </form>
                    </span>
                @endforeach
            </div>
        @else
            <span class="text-warning" style="font-size: .82rem;">لم يتم تعيين موظفين</span>
        @endif
    </div>
</div>
