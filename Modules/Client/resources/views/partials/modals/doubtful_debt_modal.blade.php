<!-- Modal الديون المشكوك فيها -->
<div class="modal fade" id="modal_doubtful_debt" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #ffc107; color: #333;">
                <h5 class="modal-title">
                    <i class="fa fa-exclamation-triangle me-2"></i>مديونية مشكوك فيها
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="doubtfulDebtForm" method="POST" action="{{ route('clients.add-doubtful-debt', $client->id) }}">
                @csrf
                <div class="modal-body">
                    <!-- معلومات توضيحية -->
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fa fa-info-circle me-2"></i>
                        <strong>شرح العملية:</strong> هذه العملية ستقوم بـ:
                        <ul class="mb-0 mt-2">
                            <li>تصنيف المديونية كمشكوك فيها</li>
                            <li>إنشاء مخصص للديون المشكوك فيها</li>
                            <li>تسجيل العملية في السجلات المحاسبية</li>
                            <li>يمكن تعديل هذا التصنيف لاحقًا</li>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>

                    <!-- معلومات العميل -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">بيانات العميل</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>الاسم:</strong> {{ $client->trade_name }}</p>
                                    <p class="mb-0"><strong>الكود:</strong> {{ $client->code }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-0">
                                        <strong>الرصيد الحالي:</strong>
                                        <span class="badge bg-warning">
                                            {{ number_format($due ?? 0, 2) }} {{ $account_setting->currency ?? 'SAR' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- حقل مبلغ المديونية المشكوك فيها -->
                    <div class="mb-3">
                        <label for="doubtful_amount" class="form-label">مبلغ المديونية المشكوك فيها <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="doubtful_amount" id="doubtful_amount"
                                   class="form-control form-control-sm"
                                   placeholder="أدخل مبلغ المديونية المشكوك فيها"
                                   max="{{ $due ?? 0 }}"
                                   required>
                            <span class="input-group-text">{{ $account_setting->currency ?? 'SAR' }}</span>
                        </div>
                        <small class="text-muted d-block mt-1">الحد الأقصى للرصيد الحالي: {{ number_format($due ?? 0, 2) }}</small>
                    </div>

                    <!-- حقل السبب -->
                    <div class="mb-3">
                        <label for="reason" class="form-label">سبب التصنيف كمشكوك فيه <span class="text-danger">*</span></label>
                        <select name="reason" id="reason" class="form-select form-select-sm" required>
                            <option value="">-- اختر السبب --</option>
                            <option value="تأخر طويل الأمد في السداد">تأخر طويل الأمد في السداد</option>
                            <option value="توقف العميل عن الدفع">توقف العميل عن الدفع</option>
                            <option value="مؤشرات على إعسار العميل">مؤشرات على إعسار العميل</option>
                            <option value="تقدم ضد العميل بدعوى">تقدم ضد العميل بدعوى</option>
                            <option value="صعوبة الحصول على المستندات">صعوبة الحصول على المستندات</option>
                            <option value="سبب آخر">سبب آخر</option>
                        </select>
                        <small class="text-muted d-block mt-1">اختر السبب الذي يبرر تصنيف هذه المديونية كمشكوك فيها</small>
                    </div>

                    <!-- حقل نسبة المخصص -->
                    <div class="mb-3">
                        <label for="provision_percentage" class="form-label">نسبة المخصص (%) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="provision_percentage" id="provision_percentage"
                                   class="form-control form-control-sm"
                                   placeholder="أدخل نسبة المخصص"
                                   value="50"
                                   min="0"
                                   max="100"
                                   required>
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted d-block mt-1">نسبة المخصص من مبلغ المديونية المشكوك فيها (افتراضي: 50%)</small>
                    </div>

                    <!-- عرض مبلغ المخصص المحسوب -->
                    <div class="mb-3">
                        <label class="form-label">مبلغ المخصص المحسوب</label>
                        <div class="input-group">
                            <input type="text" id="provision_amount_display" class="form-control form-control-sm"
                                   placeholder="سيتم حسابه تلقائيًا" disabled>
                            <span class="input-group-text">{{ $account_setting->currency ?? 'SAR' }}</span>
                        </div>
                        <small class="text-muted d-block mt-1">هذا المبلغ سيتم إنشاء مخصص له في الحسابات</small>
                    </div>

                    <!-- حقل الملاحظات -->
                    <div class="mb-3">
                        <label for="notes" class="form-label">ملاحظات إضافية</label>
                        <textarea name="notes" id="notes" class="form-control form-control-sm"
                                rows="4" placeholder="أضف أي ملاحظات تتعلق بهذه المديونية المشكوك فيها..."></textarea>
                        <small class="text-muted d-block mt-1">هذا الحقل اختياري</small>
                    </div>

                    <!-- تأكيد -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmDoubtfulDebt" required>
                        <label class="form-check-label" for="confirmDoubtfulDebt">
                            أتفهم أن هذه العملية ستصنف المديونية كمشكوك فيها وستؤثر على التقارير المحاسبية
                        </label>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i>إلغاء
                    </button>
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="fa fa-exclamation-triangle me-1"></i>تصنيف كمشكوك فيه
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script لحساب مبلغ المخصص -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const doubtfulAmountInput = document.getElementById('doubtful_amount');
    const provisionPercentageInput = document.getElementById('provision_percentage');
    const provisionAmountDisplay = document.getElementById('provision_amount_display');

    function calculateProvision() {
        const amount = parseFloat(doubtfulAmountInput.value) || 0;
        const percentage = parseFloat(provisionPercentageInput.value) || 0;
        const provisionAmount = (amount * percentage) / 100;

        provisionAmountDisplay.value = provisionAmount.toFixed(2);
    }

    if (doubtfulAmountInput) {
        doubtfulAmountInput.addEventListener('input', calculateProvision);
    }

    if (provisionPercentageInput) {
        provisionPercentageInput.addEventListener('input', calculateProvision);
    }

    // حساب عند فتح الـ Modal
    const modal = document.getElementById('modal_doubtful_debt');
    if (modal) {
        modal.addEventListener('show.bs.modal', function() {
            calculateProvision();
        });
    }
});
</script>
