{{--
    جدول عروض الأسعار — يُصيَّر مع الصفحة أوّل مرّة، ثم يُستبدل وحده عند
    البحث والتنقّل (`loadData`).

    قالبٌ واحد للحالتين عمداً: المتحكّم يعيده نصّاً في نداء Ajax ويُضمِّنه
    الفهرسُ في النداء العادي، فلا تتباعد نسختان من الجدول.

    ── ما كان ──

      • `bg-{{ $class }}` و`fas fa-*` — أصناف Bootstrap 5 وأيقونات
        FontAwesome في قالبٍ على Bootstrap 4 وFeather.
      • الحالة شرطٌ ثنائي: `status == 1 ? 'مفتوح' : 'مغلق'` — فالعرض
        المُحوَّل إلى فاتورة (٤) والمرفوض (٣) يظهران «مغلق».
      • `number_format($total, 2)` ثم «ر.س» نصّاً — لا رمز العملة ولا
        كسورها من «العملات».
      • `$quote->items->count()` داخل الحلقة: استعلامٌ لكل صفّ.
--}}

@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    use App\Models\Quote;

    /* الحالة: [اللون، الأيقونة] لكل قيمة تعداد — خريطةٌ واحدة بدل سلسلة
       شروط تعيد بناء الشارة عند كل حالة.

       المفاتيح نصوص لا أرقام: العمود `enum` والقيمة المخزَّنة نصّ. وكان
       الشرط السابق `$quote->status == 1` يقارن `'Draft'` بـ`1` — وهي في
       PHP 8 مقارنةٌ خاطئة دائماً، فيظهر كل عرضٍ «مغلق». */
    $states = [
        Quote::STATUS_DRAFT => ['secondary', 'edit-3'],
        Quote::STATUS_PENDING => ['warning', 'clock'],
        Quote::STATUS_APPROVED => ['success', 'check-circle'],
        Quote::STATUS_CONVERTED => ['primary', 'file-text'],
        Quote::STATUS_CANCELLED => ['danger', 'x-circle'],
        // المنتهي رماديٌّ بساعةٍ لا أحمر: لم يُرفَض، مضى وقتُه
        Quote::STATUS_EXPIRED => ['secondary', 'clock'],
    ];
@endphp

@if ($quotes->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="quotesTable">
            <thead>
                <tr>
                    <th style="width:20%">{{ $doc['t']('columns.number') }}</th>
                    <th style="width:16%">{{ $doc['t']('columns.date') }}</th>
                    <th>{{ $doc['t']('columns.client') }}</th>
                    <th>{{ __('branches.node_filter.column') }}</th>
                    <th class="text-center" style="width:9%">{{ $doc['t']('columns.items_count') }}</th>
                    <th class="num" style="width:15%">{{ $doc['t']('columns.total') }}</th>
                    <th class="text-center" style="width:14%">{{ $doc['t']('columns.status') }}</th>
                    <th class="text-center" style="width:8%">{{ $doc['t']('columns.actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($quotes as $quote)
                    @php
                        $statusKey = Quote::normalizeStatus($quote->status);
                        [$tone, $icon] = $states[$statusKey] ?? ['secondary', 'help-circle'];

                        $number = $quote->quote_number ?: $quote->id;

                        /* `items_count` من `withCount()` في المتحكّم:
                           `$quote->items->count()` هنا استعلامٌ لكل صفّ */
                        $itemsCount = $quote->items_count ?? 0;

                        $isConverted = $statusKey === Quote::STATUS_CONVERTED;

                        $clientName = $quote->client
                            ? ($quote->client->trade_name
                                ?: trim(($quote->client->first_name ?? '').' '.($quote->client->last_name ?? '')))
                            : $doc['t']('ui.unknown');
                    @endphp

                    <tr>
                        <td>
                            <div class="sl-media">
                                <span class="sl-media__thumb text-{{ $tone }}">
                                    <i class="feather icon-{{ $icon }}"></i>
                                </span>

                                <div class="sl-media__body">
                                    <a href="{{ route($doc['routes']['show'], $quote->id) }}" class="sl-media__title">
                                        {{ $number }}
                                    </a>
                                    <span class="sl-media__sub sl-num">#{{ $quote->id }}</span>
                                </div>
                            </div>
                        </td>

                        <td>
                            {{-- صيغة التاريخ من إعدادات اللغة النشطة --}}
                            <span class="sl-num d-block">
                                {{ LanguageSettings::formatDate($quote->quote_date) }}
                            </span>

                            <span class="sl-media__sub">
                                <i class="feather icon-user"></i>
                                {{ $quote->creator->name ?? $doc['t']('ui.dash') }}
                            </span>

                            {{-- مهلةُ السداد المتّفق عليها — تُعرَض حين تُحدَّد
                                 وحدَها، فالمستندُ النقديُّ لا سطرَ زائدَ له. --}}
                            @php $rowTerms = \App\Support\PaymentTerms::of($quote); @endphp

                            @if ($rowTerms)
                                <span class="sl-media__sub d-block">
                                    <i class="feather icon-clock"></i> {{ $rowTerms->summary() }}
                                </span>
                            @endif
                        </td>

                        <td><span class="sl-media__sub">{{ $clientName }}</span></td>

                        {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                        <td>@include('partials.branch-node-path', ['branchId' => $quote->branch_id])</td>

                        <td class="text-center">
                            <span class="badge badge-light-secondary">{{ $itemsCount }}</span>
                        </td>

                        <td class="num">
                            {{-- العملة المحفوظة مع العرض لا العملة الافتراضية اليوم --}}
                            <span class="sl-num">
                                {{ CurrencySettings::format($quote->grand_total ?? 0) }}
                            </span>
                        </td>

                        <td class="text-center">
                            <span class="badge badge-light-{{ $tone }}">
                                {{ $doc['t']('status.'.$statusKey) }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        {{-- `data-bs-toggle` وحده: الصفحة تحمّل بوتستراب ٥
                                             (سطر ٦١ في `master`) وبوتستراب ٤ داخل
                                             `vendors.min.js` (سطر ٤٠١)، والوسمان معاً
                                             يجعلان الاثنين يعالجان النقرة نفسها. --}}
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="{{ $doc['t']('ui.options') }} {{ $number }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route($doc['routes']['show'], $quote->id) }}">
                                        <i class="feather icon-eye"></i> {{ $doc['t']('ui.view') }}
                                    </a>

                                    {{-- المطبوعات لا مكان لها هنا: القوالب العشرة
                                         تُطيل قائمة الصفّ حتى تتجاوز الجدول،
                                         وهي في شاشة العرض حيث يُنتقى القالب
                                         ويُعايَن قبل الطباعة. --}}

                                    {{-- العرض المُحوَّل إلى فاتورة لا يُعدَّل:
                                         تعديله بعد التحويل يُخالف بينه وبين
                                         الفاتورة التي تشير إليه --}}
                                    @if (! $isConverted)
                                        @if ($doc['can']('edit'))
                                            <a class="dropdown-item" href="{{ route($doc['routes']['edit'], $quote->id) }}">
                                                <i class="feather icon-edit-2"></i> {{ $doc['t']('ui.edit') }}
                                            </a>
                                        @endif

                                        @if ($doc['can']('convert'))
                                            <div class="dropdown-divider"></div>

                                            <a class="dropdown-item text-success quote-convert" href="#"
                                               data-id="{{ $quote->id }}" data-number="{{ $number }}">
                                                <i class="feather icon-repeat"></i> {{ $doc['t']('doc.convert') }}
                                            </a>
                                        @endif
                                    @endif

                                    @if ($doc['can']('send_link'))
                                        <a class="dropdown-item" href="{{ route($doc['routes']['email'], $quote->id) }}">
                                            <i class="feather icon-mail"></i> {{ $doc['t']('ui.send_email') }}
                                        </a>
                                    @endif

                                    @if ($doc['can']('delete'))
                                        <div class="dropdown-divider"></div>

                                        <a class="dropdown-item text-danger quote-delete" href="#"
                                           data-id="{{ $quote->id }}" data-number="{{ $number }}">
                                            <i class="feather icon-trash-2"></i> {{ $doc['t']('ui.delete') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('sales::qoution.partials.pagination', ['paginator' => $quotes])
@else
    <div class="sl-empty">
        <div class="sl-empty__icon" aria-hidden="true"><i class="feather icon-file-text"></i></div>
        <p class="sl-empty__title">{{ $doc['t']('empty.title') }}</p>
        <p class="sl-empty__hint">
            {{ request()->hasAny(['client_id', 'id', 'status', 'total_from', 'total_to', 'from_date_1', 'to_date_1', 'item_search', 'created_by', 'currency'])
                ? $doc['t']('empty.hint')
                : $doc['t']('empty.hint_none') }}
        </p>
    </div>
@endif
