{{-- Modal تحديث حد كمية البنود لعميل واحد --}}
<div class="modal fade" id="quantityLimitModal" tabindex="-1" aria-labelledby="quantityLimitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #28a745; color: white;">
                <h5 class="modal-title" id="quantityLimitModalLabel">
                    <i class="fa fa-cubes me-2"></i>تحديث حد الكمية (لكل بند)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quantityLimitForm" action="{{ route('clients.update_item_quantity_limit') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <!-- خيار التحديث لعميل واحد أو للكل -->
                    <div class="d-flex gap-4 mb-4 pb-3 border-bottom">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="limitType" id="singleQuantityLimit" value="single" checked>
                            <label class="form-check-label fw-bold" for="singleQuantityLimit">
                                لهذا العميل فقط
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="limitType" id="allQuantityLimit" value="all">
                            <label class="form-check-label fw-bold" for="allQuantityLimit">
                                لجميع العملاء
                            </label>
                        </div>
                    </div>

                    <div id="singleClientQuantityDiv">
                        <div class="alert alert-info mb-3">
                            <i class="fa fa-info-circle me-2"></i>
                            <strong>العميل:</strong> {{ isset($client) ? $client->trade_name : 'غير محدد' }}
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fa fa-history me-2"></i>الحد الحالي (لكل بند):
                            </label>
                            <div class="alert alert-warning mb-3">
                                <strong id="current_quantity_limit_display">
                                    @if (isset($client) && $client->item_quantity_limit > 0)
                                        {{ number_format($client->item_quantity_limit, 2) }}
                                    @else
                                        لا يوجد حد (0)
                                    @endif
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div id="allClientsQuantityDiv" style="display: none;">
                        <div class="alert alert-danger mb-3">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>تنبيه هام:</strong> سيتم تطبيق هذا الحد على <strong>جميع العملاء الحاليين</strong>، كما سيتم اعتماده كـ <strong>حد افتراضي عام</strong> لأي عميل جديد يتم إضافته مستقبلاً.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="item_quantity_limit_input" class="form-label fw-bold">الحد الجديد (لكل بند):</label>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               id="item_quantity_limit_input" 
                               name="value" 
                               step="0.01" 
                               placeholder="أدخل الكمية القصوى"
                               required>
                        <small class="text-muted d-block mt-2">
                            <i class="fa fa-lightbulb text-warning me-1"></i>
                            القيمة 0 تعني إلغاء الحد (كمية مفتوحة).
                        </small>
                    </div>

                    <input type="hidden" name="client_id" id="quantity_limit_client_id" value="{{ isset($client) ? $client->id : '' }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i>إغلاق
                    </button>
                    <button type="submit" class="btn btn-success" id="quantityLimitSubmitBtn">
                        <i class="fa fa-save me-1"></i>حفظ التحديث
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const limitTypeRadios = document.querySelectorAll('input[name="limitType"]');
    const singleDiv = document.getElementById('singleClientQuantityDiv');
    const allDiv = document.getElementById('allClientsQuantityDiv');
    const submitBtn = document.getElementById('quantityLimitSubmitBtn');
    const form = document.getElementById('quantityLimitForm');

    // تبديل العرض بناءً على الخيار المختار
    limitTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'single') {
                singleDiv.style.display = 'block';
                allDiv.style.display = 'none';
                submitBtn.className = 'btn btn-success';
                submitBtn.innerHTML = '<i class="fa fa-save me-1"></i>حفظ التحديث';
            } else {
                singleDiv.style.display = 'none';
                allDiv.style.display = 'block';
                submitBtn.className = 'btn btn-danger';
                submitBtn.innerHTML = '<i class="fa fa-users me-1"></i>حفظ للجميع';
            }
        });
    });

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const limitType = document.querySelector('input[name="limitType"]:checked').value;
            const url = limitType === 'single'
                ? "{{ route('clients.update_item_quantity_limit') }}"
                : "{{ route('clients.update_all_item_quantity_limits') }}";

            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            
            if (limitType === 'single') {
                formData.append('client_id', document.getElementById('quantity_limit_client_id').value);
            }
            formData.append('value', document.getElementById('item_quantity_limit_input').value);

            /* نفسُ معالجة «الحد الائتماني» المجاور: رسائل SweetAlert لا
               `alert()` — الصندوق الأسود الذي يكتب فوقه اسمَ النطاق — والردُّ
               يُقرأ نصّاً قبل تحليله كي لا تُخفي `response.json()` سببَ الخطأ
               الحقيقيّ حين يعود HTML (جلسةٌ منتهية، أو خطأ ٥٠٠). */
            const notify = (icon, title, text) => {
                if (typeof Swal === 'undefined') { alert(text || title); return Promise.resolve(); }
                return icon === 'success'
                    ? Swal.fire({ icon: 'success', title: title, toast: true, position: 'top-start',
                                  showConfirmButton: false, timer: 1800, timerProgressBar: true })
                    : Swal.fire({ icon: icon, title: title, text: text, confirmButtonText: 'حسناً' });
            };

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(async response => {
                const body = await response.text();
                let data = null;
                try { data = JSON.parse(body); } catch (e) { /* ليس JSON */ }

                if (!data) {
                    throw new Error(response.status === 419
                        ? 'انتهت صلاحية الجلسة. حدِّث الصفحة وأعد المحاولة.'
                        : 'تعذّر قراءة رد الخادم (رمز ' + response.status + ').');
                }

                return data;
            })
            .then(data => {
                if (data.success) {
                    closeBsModal('quantityLimitModal');

                    notify('success', data.message || 'تم الحفظ بنجاح').then(() => location.reload());
                } else {
                    notify('error', 'تعذّر الحفظ', data.message || 'حدث خطأ غير متوقّع');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                notify('error', 'تعذّر الحفظ', error.message || 'حدث خطأ في الإرسال');
            });
        });
    }
});
</script>
