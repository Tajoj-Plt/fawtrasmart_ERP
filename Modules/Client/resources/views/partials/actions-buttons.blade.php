@php
    // قيود الأزرار:
    //   $blockAll          = كل الأزرار مجمّدة (عدا "إضافة ملاحظة" و "اضافة موضف العميل") لحد ما يضيف ملاحظة الزيارة أو الرئيسية
    //   $blockExchange     = "تبديل البضاعة" مجمّد ما لم يكن: يوم خط السير + سوّى فاتورة/سند/إشعار دائن اليوم
    $hasVisitNote        = $hasVisitNote        ?? true;
    $hasMainNote         = $hasMainNote         ?? true;
    $isItineraryDay      = $isItineraryDay      ?? true;
    $hasTodayTransaction = $hasTodayTransaction ?? true;
    $canExchange         = $canExchange         ?? true;

    $blockAll      = !$hasVisitNote && !$hasMainNote;
    $blockExchange = $blockAll || !$canExchange;

    $lockMsgAll      = 'أضف ملاحظة الزيارة أولاً لتفعيل هذا الإجراء';
    $lockMsgExchange = !$isItineraryDay
        ? 'تبديل البضاعة متاح فقط في يوم خط سير الموظف لهذا العميل'
        : (!$hasTodayTransaction
            ? 'لتفعيل تبديل البضاعة: يجب إنشاء فاتورة أو سند أو إشعار دائن اليوم'
            : 'أضف ملاحظة الزيارة أولاً');

    $lockedAllAttrs = $blockAll
        ? 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . $lockMsgAll . '" style="pointer-events:auto;cursor:not-allowed;opacity:.55;" onclick="event.preventDefault();event.stopPropagation();return false;"'
        : '';
    $lockedExchangeAttrs = $blockExchange
        ? 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . $lockMsgExchange . '" style="pointer-events:auto;cursor:not-allowed;opacity:.55;" onclick="event.preventDefault();event.stopPropagation();return false;"'
        : '';

    // كلُّ زرٍّ يحكمه فحصُ صلاحيةِ مساره وحده — مفاتيح «أزرار شاشة عرض العميل»
    // في إعدادات وحدات العملاء أُزيلت (2026-09-15): الإخفاء بها كان للشركة كلّها

    $moduleSettings = \App\Models\ClientModuleSetting::current();
    $isHidden = \App\Models\HiddenClient::where('user_id', auth()->id())
        ->where('client_id', $client->id)
        ->where('expires_at', '>', now())
        ->exists();

    /* «معاينة كعميل» — الزرّ يُعرض لحاملِ الصلاحية دائماً، ويُعطَّل بسببٍ
       مكتوب حين يتعذّر: بلا حساب دخولٍ للعميل، أو البوابة مقفلة، أو لم
       يُختَر «دور العميل» بعد. زرٌّ يختفي بلا سبب يجعل المستخدم يظنّ
       الميزة غير موجودة؛ وزرٌّ معطَّلٌ بسببٍ يقوده إلى ما ينقصه. */
    $canPreviewPortal = hasPermission('clients_portal_preview');
    $portalPreviewBlock = null;

    if ($canPreviewPortal) {
        if (! \App\Models\User::where('client_id', $client->id)->exists()) {
            $portalPreviewBlock = 'لا يوجد حساب دخول مرتبط بهذا العميل — أنشئ له مستخدماً أوّلاً';
        } elseif (! \App\Support\ClientPortalPermissions::portalEnabled()) {
            $portalPreviewBlock = 'بوابة العملاء مقفلة من «صلاحيات بوابة العميل»';
        } elseif (\App\Helpers\ClientSettingsHelper::clientPortalRoleId() === null) {
            $portalPreviewBlock = 'اختر «دور العميل» من «صلاحيات بوابة العميل» أوّلاً';
        }
    }
@endphp

{{-- زر ملاحظة الزيارة: يظهر بارزاً لأنه يفتح جميع الإجراءات الأخرى --}}
@if ($blockAll)
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#visitNoteModal"
        title="ملاحظة سريعة (صورة + عدد عهدة + GPS) — تفتح كل الإجراءات">
        <i class="fa fa-camera"></i>
        <span>ملاحظة الزيارة</span>
    </button>
@endif

{{-- الزر الأساسي: تعديل البيانات --}}
@if (hasPermission('clients_edit_data'))
    <a href="{{ $blockAll ? '#' : route('clients.edit', $client->id) }}" class="btn btn-primary" {!! $lockedAllAttrs !!}>
        <i class="fa fa-edit"></i>
        <span>تعديل البيانات</span>
    </a>
@endif

{{-- قائمة: إجراء جديد --}}
<div class="btn-group">
    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa fa-plus"></i>
        <span>إجراء جديد</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        {{-- إنشاء فاتورة — إلزاميّ الظهور --}}
        <li>
            <a class="dropdown-item" href="{{ $blockAll ? '#' : route('invoices.create', ['client_id' => $client->id]) }}" {!! $lockedAllAttrs !!}>
                <i class="fa fa-file-invoice me-2 text-primary"></i>إنشاء فاتورة
            </a>
        </li>
        {{-- سند قبض — إلزاميّ الظهور --}}
        <li>
            <a class="dropdown-item" href="{{ $blockAll ? '#' : route('incomes.create', ['client_id' => $client->id]) }}" {!! $lockedAllAttrs !!}>
                <i class="fa fa-money-bill-wave me-2 text-success"></i>سند قبض
            </a>
        </li>
        {{-- إضافة ملاحظة (متاحة دائماً) --}}
        @if (canAny(['client_follow_up_add_notes_attachments_appointments_all', 'client_follow_up_add_notes_attachments_appointments_assigned', 'client_follow_up_add_notes_attachments_appointments_own']))
            <li>
                <a class="dropdown-item" href="{{ route('appointment.notes.create', $client->id) }}">
                    <i class="fa fa-sticky-note me-2 text-info"></i>إضافة ملاحظة
                </a>
            </li>
        @endif
        {{-- إضافة موظف العميل (متاحة دائماً) --}}
        @if (canAny(['client_follow_up_add_notes_attachments_appointments_all', 'client_follow_up_add_notes_attachments_appointments_assigned', 'client_follow_up_add_notes_attachments_appointments_own', 'clients_add_employee_note']))
            <li>
                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#addEmployeeNoteModal">
                    <i class="fa fa-user-plus me-2 text-info"></i>إضافة موظف العميل
                </button>
            </li>
        @endif
        {{-- ترتيب موعد --}}
        @if (canAny(['client_follow_up_add_notes_attachments_appointments_all', 'client_follow_up_add_notes_attachments_appointments_assigned', 'client_follow_up_add_notes_attachments_appointments_own']))
            <li>
                <a class="dropdown-item" href="{{ $blockAll ? '#' : route('appointments.create', ['client_id' => $client->id]) }}" {!! $lockedAllAttrs !!}>
                    <i class="fa fa-calendar-plus me-2 text-primary"></i>ترتيب موعد
                </a>
            </li>
        @endif
        {{-- تبديل البضاعة (قيد خاص) --}}
        @if (hasPermission('sales.exchanges.create'))
            <li>
                <a class="dropdown-item" href="{{ $blockExchange ? '#' : route('product-exchanges.create', ['client_id' => $client->id]) }}" {!! $lockedExchangeAttrs !!}>
                    <i class="fa fa-exchange-alt me-2 text-warning"></i>تبديل البضاعة
                    @if ($blockExchange && !$blockAll)
                        <small class="d-block text-muted" style="font-size:.68rem;">
                            @if (!$isItineraryDay) متاح في يوم خط السير فقط
                            @elseif (!$hasTodayTransaction) يحتاج فاتورة أو إشعار دائن اليوم
                            @endif
                        </small>
                    @endif
                </a>
            </li>
        @endif
        {{-- إشعار دائن — إلزاميّ الظهور --}}
        @if (hasPermission(['sales.credit_notes.create', 'sales.credit_notes.create', 'clients_create_credit_notice']))
            <li>
                <a class="dropdown-item" href="{{ $blockAll ? '#' : route('CreditNotes.create', ['client_id' => $client->id]) }}" {!! $lockedAllAttrs !!}>
                    <i class="fa fa-file-invoice-dollar me-2 text-danger"></i>إشعار دائن
                </a>
            </li>
        @endif
        {{-- رصيد افتتاحي --}}
        @if (hasPermission('clients_manage_opening_balance'))
            <li>
                <button type="button" class="dropdown-item" @if (!$blockAll) data-bs-toggle="modal" data-bs-target="#modal_opening_balance" @endif {!! $lockedAllAttrs !!}>
                    <i class="fa fa-plus-circle me-2 text-primary"></i>رصيد افتتاحي
                </button>
            </li>
        @endif
    </ul>
</div>

{{-- قائمة: المزيد --}}
<div class="btn-group">
    <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa fa-ellipsis-h"></i>
        <span>المزيد</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        {{-- كشف حساب — إلزاميّ الظهور --}}
        @if (hasPermission('clients_view_statement'))
            <li>
                <a class="dropdown-item" href="{{ $blockAll ? '#' : route('clients.statement', $client->id) }}" {!! $lockedAllAttrs !!}>
                    <i class="fa fa-file-text me-2"></i>كشف حساب
                </a>
            </li>
        @endif
        {{-- الحد الائتماني --}}
        @if (hasPermission('clients_manage_credit_limit'))
            <li>
                <button type="button" class="dropdown-item" @if (!$blockAll) data-bs-toggle="modal" data-bs-target="#creditLimitModal" @endif {!! $lockedAllAttrs !!}>
                    <i class="fa fa-dollar-sign me-2"></i>الحد الائتماني
                </button>
            </li>
        @endif
        {{-- حد الكمية --}}
        @if (hasPermission('clients_manage_quantity_limit'))
            <li>
                <button type="button" class="dropdown-item" @if (!$blockAll) data-bs-toggle="modal" data-bs-target="#quantityLimitModal" @endif {!! $lockedAllAttrs !!}>
                    <i class="fa fa-cubes me-2"></i>حد الكمية
                </button>
            </li>
        @endif
        {{-- الفواتير المستحقة --}}
        @if (hasPermission('sales.payments.bulk'))
            <li>
                <a class="dropdown-item" href="{{ $blockAll ? '#' : route('paymentsClient.bulk-payment', ['client_id' => $client->id]) }}" {!! $lockedAllAttrs !!}>
                    <i class="fa fa-file-invoice-dollar me-2 text-success"></i>الفواتير المستحقة
                </a>
            </li>
        @endif
        {{-- تعيين موظفين --}}
        @if (hasPermission('clients_assign_employees'))
            <li>
                <button type="button" class="dropdown-item" @if (!$blockAll) data-bs-toggle="modal" data-bs-target="#assignEmployeeModal" @endif {!! $lockedAllAttrs !!}>
                    <i class="fa fa-user-plus me-2"></i>تعيين موظفين
                </button>
            </li>
        @endif

        {{-- إدارة الديون --}}
        @if (hasPermission('clients_manage_debts'))
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">إدارة الديون</h6></li>
            <li>
                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modal_write_off_debt" title="تسوية وحذف مديونية العميل (شطب)">
                    <i class="fa fa-minus-circle me-2 text-danger"></i>تسوية المديونية
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="{{ route('clients.add-doubtful-debt-quick', $client->id) }}"
                    title="تصنيف كامل المديونية كديون مشكوك فيها"
                    onclick="return confirm('هل تريد تصنيف كامل المديونية كمشكوك فيها؟')">
                    <i class="fa fa-exclamation-triangle me-2 text-warning"></i>مديونية مشكوك فيها
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="{{ route('doubtful-debts.index', ['client_id' => $client->id]) }}" title="قائمة بكل الديون المشكوك فيها لهذا العميل">
                    <i class="fa fa-list me-2"></i>عرض الديون المشكوك فيها
                </a>
            </li>
        @endif

        {{-- استبعاد الموقع --}}
        @if ($moduleSettings->exempt_location_enabled && hasPermission('clients_exempt_visit_location'))
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('clients.toggleExemptLocation', $client->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="dropdown-item">
                        <i class="fa {{ $client->exempt_visit_location ? 'fa-map-marker-alt' : 'fa-map-marker-slash' }} me-2 {{ $client->exempt_visit_location ? 'text-danger' : '' }}"></i>
                        {{ $client->exempt_visit_location ? 'إلغاء استبعاد الموقع' : 'استبعاد الموقع' }}
                    </button>
                </form>
            </li>
        @endif

        {{-- إخفاء / إظهار العميل --}}
        @if (hasPermission('clients_hide_from_map'))
            <li>
                @if ($isHidden)
                    <button type="button" class="dropdown-item show-client-btn" data-client-id="{{ $client->id }}" data-client-name="{{ $client->trade_name }}">
                        <i class="fa fa-eye me-2"></i>إظهار العميل
                    </button>
                @else
                    <button type="button" class="dropdown-item hide-from-map-btn" data-client-id="{{ $client->id }}" data-client-name="{{ $client->trade_name }}">
                        <i class="fa fa-eye-slash me-2"></i>إخفاء العميل
                    </button>
                @endif
            </li>
        @endif

        {{-- معاينة البوابة بحساب العميل --}}
        @if ($canPreviewPortal)
            <li><hr class="dropdown-divider"></li>
            <li>
                @if ($portalPreviewBlock)
                    <span class="dropdown-item text-muted" style="cursor:not-allowed;opacity:.65;"
                        data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $portalPreviewBlock }}">
                        <i class="fa fa-eye me-2"></i>معاينة كعميل
                    </span>
                @else
                    {{-- POST لا رابط: تغييرُ صاحب الجلسة فعلٌ لا قراءة، فلا يقع
                         بزيارة رابطٍ من التاريخ أو بجلبٍ مسبقٍ من المتصفّح --}}
                    <form action="{{ route('clients.portal.start', $client->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item"
                            onclick="return confirm('ستدخل البوابة بحساب هذا العميل لترى ما يراه. يمكنك العودة لحسابك من الشريط أعلى الصفحة. متابعة؟')">
                            <i class="fa fa-eye me-2 text-info"></i>معاينة كعميل
                        </button>
                    </form>
                @endif
            </li>
        @endif

        {{-- حذف العميل --}}
        @if (hasPermission('clients_delete'))
            <li><hr class="dropdown-divider"></li>
            <li>
                <button type="button" class="dropdown-item text-danger" @if (!$blockAll) data-bs-toggle="modal" data-bs-target="#modal_DELETE1" @endif {!! $lockedAllAttrs !!}>
                    <i class="fa fa-trash me-2"></i>حذف العميل
                </button>
            </li>
        @endif

        {{-- زر «استبعاد الفواتير» أُزيل عمداً: كان يُبيح تجاوز شرط
             «فاتورة/سند/إشعار دائن اليوم» قبل تبديل البضاعة — إعفاءٌ من
             ضابطٍ رقابي طُلب إلغاؤه كلياً، لا زرّاً يُخفى فقط. راجع
             ClientController (canExchange/hasTodayTransaction) حيث أُزيل
             نفس الاستثناء من الحساب. --}}
    </ul>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // تفعيل tooltips Bootstrap على الأزرار المجمّدة
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('.cx-header-actions [data-bs-toggle="tooltip"]').forEach(function (el) {
                if (!bootstrap.Tooltip.getInstance(el)) {
                    new bootstrap.Tooltip(el, { trigger: 'hover focus' });
                }
            });
        }
    });
</script>
