{{--
    بطاقة بيانات المورد — المصدر الواحد لعرض المورد في كل شاشات مشتريات
    المتجر: نموذج الفاتورة (إنشاءً وتعديلاً)، صفحة الفاتورة، طباعتها، وصفحة
    المورد. الحقول نفسها بالترتيب نفسه في كل موضع:

        الاسم · رقم المورد · الرقم الضريبي · السجل التجاري · الهاتف/الجوال ·
        العنوان · الرصيد (ما له علينا — من حساب ذمّته في شجرة الحسابات)

    كانت كل شاشة تعرض المورد بطريقتها: واحدة بالاسم وحده، وأخرى تقرأ
    `full_address` من عمودٍ لا وجود له، وثالثة بلا رصيد.

    @param \App\Models\Supplier|null $supplier
    @param bool $live        true في النموذج: تتبدّل البطاقة مع اختيار المورد (#supplier_id)
    @param \Illuminate\Support\Collection|null $suppliers  مطلوب مع $live
    @param bool $print       true في المطبوعات: بلا روابط ولا أيقونات
    @param string|null $title
--}}
@php
    $live = $live ?? false;
    $print = $print ?? false;
    $cardTitle = $title ?? 'بيانات المورد';

    // أرصدة الذمم دفعةً واحدة — لا استعلام لكل مورد في قائمة النموذج
    $ledgerBalances = \App\Models\Account::ledger()
        ->whereIn('supplier_id', collect($suppliers ?? [])->pluck('id')->push(optional($supplier ?? null)->id)->filter()->unique())
        ->pluck('balance', 'supplier_id');

    $describeSupplier = function ($s) use ($ledgerBalances) {
        if (! $s) {
            return null;
        }

        $balance = (float) ($ledgerBalances[$s->id] ?? 0);
        $phones = array_values(array_filter([$s->mobile, $s->phone]));

        return [
            'id' => $s->id,
            'name' => $s->name,
            'number' => $s->number_suply ?: '—',
            'tax_number' => $s->tax_number ?: '—',
            'commercial_registration' => $s->commercial_registration ?: '—',
            'phone' => $phones ? implode(' / ', $phones) : '—',
            'address' => trim((string) $s->full_address) !== '' ? $s->full_address : '—',
            // حساب المورد التزام: رصيده الدائن «له علينا»، والسالب «لنا عنده»
            'balance' => \App\Support\CurrencySettings::format(abs($balance)),
            'balance_label' => $balance > 0.004 ? 'له علينا' : ($balance < -0.004 ? 'لنا عنده' : 'لا رصيد'),
            'balance_class' => $balance > 0.004 ? 'text-danger' : ($balance < -0.004 ? 'text-success' : 'text-muted'),
            'inactive' => $s->status !== null && (int) $s->status !== 1,
            'url' => \Illuminate\Support\Facades\Route::has('store_suppliers.show') && hasPermission('purchases.suppliers.view')
                ? route('store_suppliers.show', $s->id) : null,
        ];
    };

    $card = $describeSupplier($supplier ?? null);

    $fields = [
        'number' => ['رقم المورد', 'hash'],
        'tax_number' => ['الرقم الضريبي', 'percent'],
        'commercial_registration' => ['السجل التجاري', 'briefcase'],
        'phone' => ['الهاتف / الجوال', 'phone'],
        'address' => ['العنوان', 'map-pin'],
    ];
@endphp

@if ($print)
    {{-- المطبوعات: جدول بسيط يطبعه المتصفح وTCPDF على السواء --}}
    <table class="supplier-card-print" width="100%" cellpadding="3" style="border:1px solid #ddd; border-collapse:collapse; margin-bottom:10px; font-size:12px;">
        <tr>
            <td colspan="4" style="background:#f5f5f5; font-weight:bold; border-bottom:1px solid #ddd;">{{ $cardTitle }}</td>
        </tr>
        <tr>
            <td width="18%" style="color:#666;">الاسم</td>
            <td width="32%"><strong>{{ $card['name'] ?? 'غير محدد' }}</strong></td>
            <td width="18%" style="color:#666;">رقم المورد</td>
            <td width="32%">{{ $card['number'] ?? '—' }}</td>
        </tr>
        <tr>
            <td style="color:#666;">الرقم الضريبي</td>
            <td>{{ $card['tax_number'] ?? '—' }}</td>
            <td style="color:#666;">السجل التجاري</td>
            <td>{{ $card['commercial_registration'] ?? '—' }}</td>
        </tr>
        <tr>
            <td style="color:#666;">الهاتف / الجوال</td>
            <td>{{ $card['phone'] ?? '—' }}</td>
            <td style="color:#666;">العنوان</td>
            <td>{{ $card['address'] ?? '—' }}</td>
        </tr>
    </table>
@else
    <div class="card store-supplier-card mb-3" id="storeSupplierCard" @if ($live && ! $card) style="display:none" @endif>
        <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <h6 class="mb-0">
                    <i class="feather icon-truck"></i> {{ $cardTitle }}:
                    <strong data-supplier-field="name">{{ $card['name'] ?? '' }}</strong>
                    <span class="badge badge-light-danger ms-1" data-supplier-flag="inactive" @if (! ($card['inactive'] ?? false)) style="display:none" @endif>موقوف</span>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">الرصيد:</span>
                    <strong data-supplier-field="balance" class="{{ $card['balance_class'] ?? 'text-muted' }}">{{ $card['balance'] ?? '' }}</strong>
                    <span class="small text-muted" data-supplier-field="balance_label">{{ $card['balance_label'] ?? '' }}</span>
                    <a href="{{ $card['url'] ?? '#' }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"
                        data-supplier-link @if (! ($card['url'] ?? null)) style="display:none" @endif>
                        <i class="feather icon-external-link"></i> صفحة المورد
                    </a>
                </div>
            </div>
            <div class="row g-2">
                @foreach ($fields as $key => [$label, $icon])
                    <div class="{{ $key === 'address' ? 'col-lg-4 col-md-8' : 'col-lg-2 col-md-4' }} col-6">
                        <div class="small text-muted"><i class="feather icon-{{ $icon }}"></i> {{ $label }}</div>
                        <div data-supplier-field="{{ $key }}" @if (in_array($key, ['number', 'tax_number', 'commercial_registration', 'phone'])) dir="ltr" class="text-end" @endif>{{ $card[$key] ?? '—' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if ($live)
        @push('scripts')
            <script>
                // بطاقة المورد تتبع القائمة: البيانات محقونةٌ من الخادم بالحقول نفسها
                // التي تعرضها صفحة الفاتورة وطباعتها
                (function () {
                    var suppliers = @json(collect($suppliers ?? [])->map($describeSupplier)->filter()->keyBy('id'));
                    var card = document.getElementById('storeSupplierCard');
                    var select = document.getElementById('supplier_id');

                    if (!card || !select) { return; }

                    function render() {
                        var data = suppliers[select.value];

                        if (!data) { card.style.display = 'none'; return; }

                        card.style.display = '';
                        card.querySelectorAll('[data-supplier-field]').forEach(function (el) {
                            el.textContent = data[el.getAttribute('data-supplier-field')] || '—';
                        });

                        var balance = card.querySelector('[data-supplier-field="balance"]');
                        balance.className = data.balance_class;

                        card.querySelector('[data-supplier-flag="inactive"]').style.display = data.inactive ? '' : 'none';

                        var link = card.querySelector('[data-supplier-link]');
                        link.style.display = data.url ? '' : 'none';
                        if (data.url) { link.setAttribute('href', data.url); }
                    }

                    select.addEventListener('change', render);
                    if (window.jQuery) { window.jQuery(select).on('change select2:select select2:clear', render); }
                    render();
                })();
            </script>
        @endpush
    @endif
@endif
