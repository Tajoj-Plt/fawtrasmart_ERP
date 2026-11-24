{{-- تبويب «التبديلات» — بطاقاتٌ بتصميم «ملاحظات الزيارات» (visit_note_cards.css،
     ثلاثٌ في الصف). التفاصيل في مودال `#exchangeDetailsModal` من
     `/sales/product-exchanges/{id}/details`. تسعُ بطاقاتٍ ثم «عرض الكل» (`data-vn-more`). --}}
@php
    $exRows = $productExchanges ?? collect();
    $exCurrency = ($account_setting ?? null)?->currency;
    $exSymbol = empty($exCurrency) || $exCurrency === 'SAR'
        ? '<img src="' . asset('assets/images/Saudi_Riyal.svg') . '" alt="ريال سعودي" width="14">'
        : e($exCurrency);
    $exStatuses = [
        0 => ['label' => 'معلق', 'tone' => 'live', 'icon' => 'fa-clock'],
        1 => ['label' => 'مكتمل', 'tone' => 'done', 'icon' => 'fa-check-circle'],
        2 => ['label' => 'ملغي', 'tone' => 'cancel', 'icon' => 'fa-times-circle'],
    ];
    $exTotalQuantity = $exRows->sum(fn ($exchange) => $exchange->items->sum('quantity'));
@endphp
<div class="tab-pane" id="product-exchanges" role="tabpanel">
    <div class="vn-wrap">
        <div class="vn-section-head">
            <div class="vn-section-title">
                <span class="vn-section-icon"><i class="fas fa-sync-alt"></i></span>
                <div>
                    التبديلات
                    <span class="vn-section-count">{{ $exRows->count() }} تبديل · مجموع الكميات {{ $exTotalQuantity }}</span>
                </div>
            </div>
        </div>

        @if ($exRows->isNotEmpty())
            <div class="vn-grid" id="vn-exchanges-grid">
                @foreach ($exRows as $exchange)
                    @php
                        $exStatus = $exStatuses[(int) $exchange->status] ?? ['label' => 'غير معروف', 'tone' => 'muted', 'icon' => 'fa-question-circle'];
                        $exQuantity = $exchange->items->sum('quantity');
                    @endphp
                    <article class="vn-card" @if ($loop->iteration > 9) hidden data-vn-extra @endif>
                        <header class="vn-head">
                            <span class="vn-num vn-num-{{ ['success', 'primary', 'warning'][$loop->index % 3] }}">{{ $loop->iteration }}</span>
                            <div class="vn-who">
                                <div class="vn-title">
                                    <span class="vn-title-icon"><i class="fas fa-user"></i></span>
                                    <span title="{{ $exchange->employee->name ?? 'غير محدد' }}">{{ $exchange->employee->name ?? 'غير محدد' }}</span>
                                </div>
                                <div class="vn-sub">
                                    <span><i class="fas fa-hashtag"></i><bdi>{{ $exchange->code }}</bdi></span>
                                    <span><i class="fas fa-warehouse"></i>{{ $exchange->storehouse->name ?? 'غير محدد' }}</span>
                                </div>
                            </div>
                            <div class="vn-when">
                                @if ($exchange->exchange_date)
                                    <span><i class="fas fa-calendar-alt"></i>{{ $exchange->exchange_date->format('Y-m-d') }}</span>
                                @endif
                                <span class="vn-status vn-status-{{ $exStatus['tone'] }}">
                                    <i class="fas {{ $exStatus['icon'] }}"></i>{{ $exStatus['label'] }}
                                </span>
                            </div>
                        </header>

                        <div class="vn-note" title="{{ $exchange->notes }}">
                            <p><span class="vn-note-label">ملاحظة:</span> {{ $exchange->notes ?: 'لا توجد ملاحظات' }}</p>
                            <span class="vn-note-icon"><i class="fas fa-file-alt"></i></span>
                        </div>

                        <div class="vn-facts">
                            <div class="vn-fact">
                                <div class="vn-fact-label"><i class="fas fa-cubes vn-ic-doc"></i> الكمية</div>
                                <div class="vn-count"><b>{{ $exQuantity }}</b> {{ \App\Services\Clients\VisitNoteCards::pieces((int) $exQuantity) }}</div>
                                <div class="vn-empty">{{ $exchange->items->count() }} صنف</div>
                            </div>
                            <div class="vn-fact">
                                <div class="vn-fact-label"><i class="fas fa-money-bill vn-ic-comp"></i> القيمة</div>
                                <div class="vn-amount"><bdi dir="ltr">{{ number_format($exchange->total_value, 2) }}</bdi> {!! $exSymbol !!}</div>
                            </div>
                            <div class="vn-fact">
                                <div class="vn-fact-label"><i class="fas fa-image vn-ic-custody"></i> الصورة</div>
                                @if ($exchange->image)
                                    <a href="{{ asset($exchange->image) }}" data-fancybox="exchange-images">
                                        <img class="vn-photo" src="{{ asset($exchange->image) }}" alt="صورة التبديل" loading="lazy">
                                    </a>
                                @else
                                    <span class="vn-photo-empty"><i class="fas fa-image"></i></span>
                                @endif
                            </div>
                        </div>

                        <footer class="vn-foot">
                            <span class="vn-foot-meta">
                                @if ($exchange->created_at)
                                    <i class="fas fa-history"></i> {{ $exchange->created_at->diffForHumans() }}
                                @endif
                            </span>
                            <div class="vn-actions">
                                <button type="button" class="vn-act view-exchange-details"
                                    data-exchange-id="{{ $exchange->id }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#exchangeDetailsModal"
                                    title="عرض التفاصيل"><i class="fas fa-eye"></i></button>
                            </div>
                        </footer>
                    </article>
                @endforeach
            </div>

            @if ($exRows->count() > 9)
                <div class="vn-more-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-vn-more="vn-exchanges-grid">
                        <i class="fa fa-chevron-down me-1"></i>عرض كل التبديلات ({{ $exRows->count() }})
                    </button>
                </div>
            @endif

            <!-- Modal لعرض تفاصيل التبديل -->
            <div class="modal fade" id="exchangeDetailsModal" tabindex="-1" aria-labelledby="exchangeDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="exchangeDetailsModalLabel">
                                <i class="fa fa-exchange-alt me-2"></i>
                                تفاصيل التبديل
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="exchangeDetailsContent">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">جاري التحميل...</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="empty-state">
                <i class="fa fa-exchange-alt"></i>
                <h5>لا توجد تبديلات</h5>
                <p>لم يتم تسجيل أي تبديلات منتجات لهذا العميل حتى الآن.</p>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // تحميل تفاصيل التبديل عند النقر على زر العرض
    document.querySelectorAll('.view-exchange-details').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const exchangeId = this.dataset.exchangeId;
            const contentDiv = document.getElementById('exchangeDetailsContent');

            contentDiv.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">جاري التحميل...</span>
                    </div>
                </div>
            `;

            fetch(`/sales/product-exchanges/${exchangeId}/details`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        contentDiv.innerHTML = data.html;
                    } else {
                        contentDiv.innerHTML = `
                            <div class="alert alert-danger text-center">
                                <i class="fa fa-exclamation-circle me-2"></i>
                                حدث خطأ أثناء جلب البيانات
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger text-center">
                            <i class="fa fa-exclamation-circle me-2"></i>
                            حدث خطأ في الاتصال بالسيرفر
                        </div>
                    `;
                });
        });
    });
});
</script>
