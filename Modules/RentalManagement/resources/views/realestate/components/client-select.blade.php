{{--
    اختيار عميلٍ من الملفّ المركزي — لا إنشاء عميلٍ هنا.

    الحقل يبحث بـAjax (`clientSearch`) داخل نطاق بيانات المستخدم، فلا تُضمَّن
    آلاف العملاء `<option>` في كلّ صفحة. لا يُطبع إلّا العميل المختار.

    الوسائط: $name، $selected، $required، $placeholder، $id، و$tenantsOnly (bool)
    لقصر البحث على المستأجرين، و$searchRoute لاسم مسار البحث.
    الوسائط القديمة ($clients، $preferred، $preferredLabel، $othersLabel) مقبولةٌ
    ومتجاهَلة — يُقرأ منها اسم المختار إن وُجد.
--}}
@php
    use App\Constants\planFeatures\RealEstatePlanFeatures as ClientSelectPlan;
    use App\Services\Fawtra\SubscriptionGate as ClientSelectGate;

    $fieldId = $id ?? $name;
    $current = (string) old($name, $selected ?? '');

    $currentClient = null;

    if ($current !== '') {
        $currentClient = (isset($clients) && $clients instanceof \Illuminate\Support\Collection ? $clients->firstWhere('id', (int) $current) : null)
            ?? \App\Models\Client::query()->select('id', 'trade_name', 'first_name', 'last_name', 'code')->find($current);
    }

    // مسار البحث مكرَّرٌ تحت أكثر من قسم (كلٌّ بميزة باقته) — يُختار المتاح منها
    $searchRoute ??= collect([
        'realestate.tenants.clients' => ClientSelectPlan::LISTINGS,
        'realestate.tasks.clients' => ClientSelectPlan::TASKS,
    ])->filter(fn ($plan, $route) => Route::has($route) && ClientSelectGate::permits($plan))->keys()->first() ?? 'realestate.tenants.clients';
@endphp
<select name="{{ $name }}" id="{{ $fieldId }}" class="form-control re-client-search"
        data-url="{{ Route::has($searchRoute) ? route($searchRoute) : '' }}"
        data-placeholder="{{ $placeholder ?? 'ابحث باسم العميل أو رمزه أو جواله…' }}"
        @if (! empty($tenantsOnly)) data-tenants="1" @endif
        @if (! empty($required)) data-required="1" @endif>
    <option value="">{{ $placeholder ?? 'اختر عميلاً…' }}</option>
    @if ($currentClient)
        <option value="{{ $currentClient->id }}" selected>{{ $currentClient->display_name }}@if ($currentClient->code) — {{ $currentClient->code }}@endif</option>
    @endif
</select>
@permission('clients_add_client')
    @if (empty($filter) && Route::has('clients.create'))
        <small class="text-muted"><a href="{{ route('clients.create') }}" target="_blank" rel="noopener">عميل جديد؟ أضِفه في ملفّ العملاء</a> ثم ابحث عنه هنا.</small>
    @endif
@endpermission

@once
    @push('scripts')
        <script>
            (function ($) {
                window.reClientSelect = function (root) {
                    if (!$.fn.select2) return;

                    $(root || document).find('select.re-client-search').each(function () {
                        const $select = $(this);
                        if ($select.data('select2') || !$select.data('url')) return;

                        const $modal = $select.closest('.modal');

                        $select.select2({
                            dir: 'rtl', width: '100%', allowClear: true, minimumInputLength: 0,
                            placeholder: $select.data('placeholder') || 'اختر عميلاً…',
                            dropdownParent: $modal.length ? $modal : $(document.body),
                            language: {
                                searching: () => 'جارٍ البحث…', noResults: () => 'لا نتائج',
                                loadingMore: () => 'تحميل المزيد…', errorLoading: () => 'تعذّر تحميل النتائج',
                            },
                            ajax: {
                                url: $select.data('url'), dataType: 'json', delay: 250, cache: true,
                                data: params => ({ q: params.term || '', page: params.page || 1, tenants: $select.data('tenants') ? 1 : 0 }),
                                processResults: data => ({ results: data.results || [], pagination: data.pagination || { more: false } }),
                            },
                        });
                    });
                };

                // يضع عميلاً بعينه في الحقل (تعديل صفٍّ في نافذة) دون جلب القائمة
                window.reClientSelectSet = function (select, id, text) {
                    const $select = $(select);
                    $select.find('option').filter((i, o) => o.value !== '').remove();
                    if (id) $select.append(new Option(text || ('#' + id), id, true, true));
                    $select.val(id || '').trigger('change');
                };

                $(function () { window.reClientSelect(document); });
            })(jQuery);
        </script>
    @endpush
@endonce
