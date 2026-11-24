<!-- Modal تسوية الديون المعدومة -->
<div class="modal fade" id="modal_write_off_debt" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #dc3545; color: white;">
                <h5 class="modal-title">
                    <i class="fa fa-minus-circle me-2"></i>تسوية الديون المعدومة
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="writeOffForm" method="POST" action="{{ route('clients.write-off-debt', $client->id) }}">
                @csrf
                <div class="modal-body">
                    <!-- تحذير -->
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <strong>تحذير مهم:</strong> هذه العملية ستقوم بـ:
                        <ul class="mb-0 mt-2">
                            <li>تصفير رصيد حساب العميل بالكامل</li>
                            <li>إنشاء قيد يومية تلقائي</li>
                            <li>تسجيل العملية في السجلات</li>
                            <li>هذه العملية لا يمكن التراجع عنها</li>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>

                    <!-- معلومات العميل -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">بيانات العميل</h6>
                            <p class="mb-1"><strong>الاسم:</strong> {{ $client->trade_name }}</p>
                            <p class="mb-1"><strong>الكود:</strong> {{ $client->code }}</p>
                            <p class="mb-0">
                                <strong>الرصيد الحالي:</strong>
                                <span class="badge bg-danger">
                                    {{ number_format($due ?? 0, 2) }} {{ $account_setting->currency ?? 'SAR' }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- خيار نوع التسوية -->
                    <div class="mb-3">
                        <label for="write_off_type" class="form-label">نوع التسوية <span class="text-danger">*</span></label>
                        <select name="write_off_type" id="write_off_type" class="form-select form-select-sm" required>
                            <option value="">-- اختر نوع التسوية --</option>
                            <option value="full">تصفير المديونية كاملة</option>
                            <option value="partial">تصفير المديونية بمبلغ محدد</option>
                            <option value="negative_to_zero">تحويل الرصيد السالب إلى صفر</option>
                        </select>
                        <small class="text-muted d-block mt-1">اختر طريقة التسوية المطلوبة</small>
                    </div>

                    <!-- حقل المبلغ (يظهر فقط عند اختيار "مبلغ محدد") -->
                    <div class="mb-3" id="amountField" style="display: none;">
                        <label for="amount" class="form-label">المبلغ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="amount" id="amount"
                                   class="form-control form-control-sm"
                                   placeholder="أدخل المبلغ المراد تسويته"
                                   min="0">
                            <span class="input-group-text">{{ $account_setting->currency ?? 'SAR' }}</span>
                        </div>
                        <small class="text-muted d-block mt-1">يجب ألا يزيد المبلغ عن الرصيد الحالي</small>
                    </div>

                    <!-- حقل السبب -->
                    <div class="mb-3">
                        <label for="reason" class="form-label">السبب <span class="text-danger">*</span></label>
                        <select name="reason" id="reason" class="form-select form-select-sm" required>
                            <option value="">-- اختر السبب --</option>
                            <option value="عميل معسر - غير قادر على السداد">عميل معسر - غير قادر على السداد</option>
                            <option value="شطب ديون قديمة لم تُسدد">شطب ديون قديمة لم تُسدد</option>
                            <option value="اتفاق تنازل عن الديون">اتفاق تنازل عن الديون</option>
                            <option value="إعادة هيكلة حساب العميل">إعادة هيكلة حساب العميل</option>
                            <option value="سبب آخر">سبب آخر</option>
                        </select>
                        <small class="text-muted d-block mt-1">اختر السبب الرئيسي لتسوية الديون</small>
                    </div>

                    <!-- حقل الملاحظات -->
                    <div class="mb-3">
                        <label for="notes" class="form-label">ملاحظات إضافية</label>
                        <textarea name="notes" id="notes" class="form-control form-control-sm"
                                rows="4" placeholder="أضف أي ملاحظات تتعلق بتسوية هذه الديون..."></textarea>
                        <small class="text-muted d-block mt-1">هذا الحقل اختياري</small>
                    </div>

                    <!-- تأكيد -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmWriteOff" required>
                        <label class="form-check-label" for="confirmWriteOff">
                            أتفهم أن هذه العملية ستقوم بتصفير حساب العميل ولا يمكن التراجع عنها
                        </label>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i>إلغاء
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-minus-circle me-1"></i>تسوية الديون
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// التحكم في ظهور حقل المبلغ
document.getElementById('write_off_type').addEventListener('change', function() {
    const amountField = document.getElementById('amountField');
    const amountInput = document.getElementById('amount');

    if (this.value === 'partial') {
        amountField.style.display = 'block';
        amountInput.required = true;
    } else {
        amountField.style.display = 'none';
        amountInput.required = false;
        amountInput.value = '';
    }
});

// التحقق من صحة المبلغ عند الإرسال
document.getElementById('writeOffForm').addEventListener('submit', function(e) {
    const writeOffType = document.getElementById('write_off_type').value;
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const currentBalance = {{ $due ?? 0 }};

    if (writeOffType === 'partial') {
        if (!amount || amount <= 0) {
            e.preventDefault();
            alert('يجب إدخال مبلغ موجب');
            return false;
        }
        if (amount > currentBalance) {
            e.preventDefault();
            alert('المبلغ المدخل أكبر من الرصيد الحالي');
            return false;
        }
    }
});
</script>
