@extends('hr::master')

@section('title', 'إدارة بطاقات الحضور')

@push('styles')
<style>
    .cm-tiles { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px,1fr)); gap:10px; margin-bottom:16px; }
    .cm-tile { background: var(--app-surface,#fff); border:1px solid var(--app-border,#e5e7eb);
        border-top:3px solid var(--app-muted,#9ca3af); border-radius:10px; padding:12px 15px; }
    .cm-tile .v { font-size:1.6rem; font-weight:800; line-height:1; font-variant-numeric: tabular-nums; }
    .cm-tile .l { font-size:.72rem; color: var(--app-muted,#6b7280); margin-top:4px; }
    .cm-tile--issued    { border-top-color: var(--app-success,#16a34a); }
    .cm-tile--suspended { border-top-color: var(--app-danger,#dc2626); }
    .cm-tile--none      { border-top-color: var(--app-warning,#d97706); }

    .cm-filters { background: var(--app-surface,#fff); border:1px solid var(--app-border,#e5e7eb);
        border-radius:10px; padding:12px 14px; margin-bottom:16px; }
    .cm-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px,1fr)); gap:10px; align-items:end; }
    .cm-filters .form-control { height:36px; font-size:.84rem; }

    .cm-table { font-size:.84rem; }
    .cm-table td { vertical-align:middle; }
    .cm-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.8rem;
        background: var(--app-body-bg,#f3f4f6); padding:2px 8px; border-radius:6px; letter-spacing:.5px; }
    .cm-pill { font-size:.68rem; padding:2px 9px; border-radius:999px; }
    .cm-pill--active    { background: color-mix(in srgb, var(--app-success,#16a34a) 15%, transparent); color: var(--app-success,#15803d); }
    .cm-pill--suspended { background: color-mix(in srgb, var(--app-danger,#dc2626) 15%, transparent); color: var(--app-danger,#b91c1c); }
    .cm-pill--none      { background: var(--app-body-bg,#f3f4f6); color: var(--app-muted,#6b7280); }
    .cm-help { font-size:.78rem; color: var(--app-muted,#6b7280); }
</style>
@endpush

@section('content')
@include('stock::components.page-head', [
    'title' => 'إدارة بطاقات الحضور',
    'crumbs' => ['الحضور' => null, 'بطاقات الحضور' => null],
])

@include('hr::attendance._partials.section-tabs', ['group' => 'devices'])

<div class="content-body">

    <div class="alert alert-secondary cm-help">
        البطاقةُ هي هويّةُ الموظف أمام جهاز الفرع. و<strong>سحبُها فوريّ</strong>:
        إيقافُها هنا يجعل حارسَ التسجيل يردّ أيَّ مسحةٍ بها من اللحظة التالية —
        وهو ما يلزم عند الاستقالة أو الإيقاف، لأن البطاقة تبقى في جيب صاحبها.
        والسحبُ لا يمحو شيئاً من سجل الحضور السابق.
    </div>

    <div class="cm-tiles">
        <div class="cm-tile"><div class="v">{{ $stats['total'] }}</div><div class="l">إجمالي الموظفين</div></div>
        <div class="cm-tile cm-tile--issued"><div class="v">{{ $stats['issued'] }}</div><div class="l">بطاقات مُصدَرة</div></div>
        <div class="cm-tile cm-tile--suspended"><div class="v">{{ $stats['suspended'] }}</div><div class="l">بطاقات مسحوبة</div></div>
        <div class="cm-tile cm-tile--none"><div class="v">{{ $stats['none'] }}</div><div class="l">بلا بطاقة</div></div>
    </div>

    <form method="GET" class="cm-filters">
        <div class="cm-grid">
            <div>
                <label class="cm-help">القسم</label>
                <select name="department_id" class="form-control">
                    <option value="">الكل</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') == $department->id)>
                            {{ $department->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="cm-help">حالة البطاقة</label>
                <select name="card_state" class="form-control">
                    <option value="">الكل</option>
                    <option value="active" @selected(($filters['card_state'] ?? '') === 'active')>فعّالة</option>
                    <option value="suspended" @selected(($filters['card_state'] ?? '') === 'suspended')>مسحوبة</option>
                    <option value="none" @selected(($filters['card_state'] ?? '') === 'none')>بلا بطاقة</option>
                </select>
            </div>
            <div>
                <label class="cm-help">بحث (اسم أو رقم بطاقة)</label>
                <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div>
                <button class="btn btn-primary btn-sm">تصفية</button>
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm">مسح</a>
            </div>
        </div>
    </form>

    {{-- كلُّ زرٍّ بصلاحيته (`attendance_cards.*`): العرضُ وحده لا يُصدر ولا يحذف ولا يطبع --}}
    @php
        $canManageCards = hasPermission('attendance_cards.manage');
        $canPrintCards = hasPermission('attendance_cards.print');
    @endphp
    <div class="d-flex justify-content-end mb-2" style="gap:6px">
        {{-- تطبع ما تراه: الرابطُ يحمل التصفيةَ الحالية معه --}}
        @if ($canPrintCards)
            <a class="btn btn-outline-secondary btn-sm"
               href="{{ route('barcode.cards.print', request()->only(['department_id', 'card_state', 'q'])) }}"
               target="_blank">طباعة البطاقات</a>
        @endif
        @if ($canManageCards)
            <button class="btn btn-success btn-sm" id="generate-all">
                <i class="feather icon-credit-card"></i> إصدار بطاقات لمن لا بطاقة له
            </button>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-hover cm-table">
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>القسم</th>
                    <th style="width:210px;">طريقة التسجيل</th>
                    <th>رقم البطاقة</th>
                    <th>الحالة</th>
                    <th style="width:230px;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    @php
                        $state = ! $employee->barcode ? 'none' : ($employee->barcode_enabled === false ? 'suspended' : 'active');
                        $labels = ['none' => 'بلا بطاقة', 'suspended' => 'مسحوبة', 'active' => 'فعّالة'];
                    @endphp
                    <tr data-employee="{{ $employee->id }}">
                        <td>{{ $employee->full_name }}</td>
                        <td>{{ $employee->department->name ?? '—' }}</td>
                        <td>
                            @php $empChannel = $employee->attendance_channel ?: 'branch'; @endphp
                            <select class="form-control form-control-sm js-channel" data-id="{{ $employee->id }}" @disabled(! $canManageCards)>
                                <option value="branch" @selected($empChannel === 'branch')>بالبطاقة — جهاز الفرع</option>
                                <option value="field" @selected($empChannel === 'field')>من الجوال — ميداني</option>
                                <option value="both" @selected($empChannel === 'both')>الاثنين</option>
                            </select>
                            {{-- مكانُ التسجيل من الجوال: يظهر للميداني فقط --}}
                            <select class="form-control form-control-sm mt-25 js-place" data-id="{{ $employee->id }}"
                                    title="من أين يُقبل تسجيله من الجوال" @if($empChannel === 'branch') hidden @endif @disabled(! $canManageCards)>
                                <option value="">من أي مكان — يُحفظ موقعه</option>
                                @foreach($geofences as $gf)
                                    <option value="{{ $gf->id }}" @selected((int) $employee->attendance_geofence_id === $gf->id)>داخل: {{ $gf->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            @if($employee->barcode)
                                <span class="cm-code">{{ $employee->barcode }}</span>
                            @else
                                <span class="cm-help">—</span>
                            @endif
                        </td>
                        <td><span class="cm-pill cm-pill--{{ $state }}">{{ $labels[$state] }}</span></td>
                        <td class="text-nowrap">
                            @if($employee->barcode)
                                @if ($canPrintCards)
                                    <a href="{{ route('barcode.print', $employee->id) }}" target="_blank"
                                       class="btn btn-outline-secondary btn-sm">طباعة</a>
                                @endif
                                @if ($canManageCards)
                                    <button class="btn btn-outline-{{ $employee->barcode_enabled === false ? 'success' : 'warning' }} btn-sm js-toggle"
                                            data-id="{{ $employee->id }}"
                                            data-enable="{{ $employee->barcode_enabled === false ? 1 : 0 }}">
                                        {{ $employee->barcode_enabled === false ? 'إعادة تفعيل' : 'سحب البطاقة' }}
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm js-delete" data-id="{{ $employee->id }}">حذف</button>
                                @endif
                            @elseif ($canManageCards)
                                <button class="btn btn-primary btn-sm js-issue" data-id="{{ $employee->id }}">إصدار بطاقة</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">لا يوجد موظفون مطابقون</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $employees->links() }}
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';

    async function call(url, method, body) {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        return response.json();
    }

    function done(data) {
        if (data.success) {
            Swal.fire({ icon: 'success', title: data.message, timer: 1400, showConfirmButton: false })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'تعذّر التنفيذ', text: data.message || '' });
        }
    }

    // طريقةُ التسجيل تُحفظ فور اختيارها — بلا إعادة تحميل
    const channelUrl = id => '{{ route("barcode.card.channel", ":id") }}'.replace(':id', id);
    const saveChannel = async (row) => {
        const channel = row.querySelector('.js-channel');
        const place = row.querySelector('.js-place');
        place.hidden = channel.value === 'branch';
        const data = await call(channelUrl(channel.dataset.id), 'POST', { channel: channel.value, geofence_id: place.value || null });
        Swal.fire({ icon: data.success ? 'success' : 'error', title: data.message || 'تعذّر الحفظ', timer: 2200, showConfirmButton: false });
    };
    document.querySelectorAll('.js-channel, .js-place').forEach(sel => sel.addEventListener('change', () => saveChannel(sel.closest('tr'))));

    document.querySelectorAll('.js-issue').forEach(btn => btn.addEventListener('click', async () => {
        {{-- `route()` لا عنوانٌ مكتوبٌ بيده: كان `…/hr/presence/barcode/…`
             وبادئةُ `presence` لا وجودَ لها في الوحدة (المسارات تحت `hr/`)،
             ولا بادئةَ لغةٍ فيه — فزرُّ «إصدار بطاقة» لموظفٍ واحد يردّ ٤٠٤
             وحدَه، بينما «إصدار للكل» و«السحب» و«الحذف» في الملفّ نفسِه
             تستعمل `route()` وتعمل. --}}
        done(await call('{{ route("barcode.generate", ":id") }}'.replace(':id', btn.dataset.id), 'POST'));
    }));

    document.querySelectorAll('.js-toggle').forEach(btn => btn.addEventListener('click', async () => {
        const enable = btn.dataset.enable === '1';

        // السحبُ يُوقف تسجيلَ صاحبها فوراً — يُستأذن فيه
        if (! enable) {
            const ok = await Swal.fire({
                icon: 'warning', title: 'سحب البطاقة؟',
                text: 'لن يستطيع الموظف التسجيل بها من اللحظة التالية. سجلُّ حضوره السابق لا يتأثّر.',
                showCancelButton: true, confirmButtonText: 'اسحبها', cancelButtonText: 'إلغاء',
            });
            if (! ok.isConfirmed) return;
        }

        done(await call('{{ route("barcode.card.toggle", ":id") }}'.replace(':id', btn.dataset.id),
                        'POST', { enabled: enable }));
    }));

    document.querySelectorAll('.js-delete').forEach(btn => btn.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'warning', title: 'حذف رقم البطاقة؟',
            text: 'يُمحى الرقم نهائياً ويلزم إصدار بطاقة جديدة. السحبُ وحده يكفي للإيقاف المؤقّت.',
            showCancelButton: true, confirmButtonText: 'احذف', cancelButtonText: 'إلغاء',
        });
        if (! ok.isConfirmed) return;

        done(await call('{{ route("barcode.card.delete", ":id") }}'.replace(':id', btn.dataset.id), 'DELETE'));
    }));

    document.getElementById('generate-all')?.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'question', title: 'إصدار بطاقات؟',
            text: 'تُصدَر بطاقة لكل موظف لا بطاقة له. لا تتأثّر البطاقات القائمة.',
            showCancelButton: true, confirmButtonText: 'أصدِر', cancelButtonText: 'إلغاء',
        });
        if (! ok.isConfirmed) return;

        done(await call('{{ route("barcode.generate.all") }}', 'POST'));
    });
});
</script>
@endsection
