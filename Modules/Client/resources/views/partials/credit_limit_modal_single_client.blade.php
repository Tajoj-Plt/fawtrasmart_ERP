{{-- Modal تحديث الحد الائتماني لعميل واحد (صفحة العميل) --}}
{{-- الظهور بصلاحية مساره وحدها — كان مشروطاً أيضاً بتفعيل «الحد الائتماني» في الحقول
     الإضافية، فإطفاؤه يُبقي الزرّ بلا نافذة. أُزيل ذلك الحقل (2026-09-15). --}}
@if (hasPermission('clients_manage_credit_limit'))
<div class="modal fade" id="creditLimitModal" tabindex="-1" aria-labelledby="creditLimitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #007bff; color: white;">
                <h5 class="modal-title" id="creditLimitModalLabel">
                    <i class="fa fa-dollar-sign me-2"></i>تحديث الحد الائتماني
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="creditLimitForm" action="{{ route('clients.update_credit_limit') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="fa fa-info-circle me-2"></i>
                        <strong>العميل:</strong> {{ isset($client) ? $client->trade_name : 'غير محدد' }}
                    </div>

                    <div class="mb-3">
                        <label for="current_limit_display" class="form-label">
                            <i class="fa fa-history me-2"></i>الحد الائتماني الحالي:
                        </label>
                        <div class="alert alert-warning mb-3">
                            <strong id="current_limit_display">
                                @if (isset($creditLimit) && $creditLimit)
                                    {{ number_format($creditLimit->value, 2) }}
                                @else
                                    غير محدد
                                @endif
                            </strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="credit_limit" class="form-label">الحد الائتماني الجديد:</label>
                        <input type="number"
                               class="form-control form-control-lg"
                               id="credit_limit"
                               name="value"
                               step="0.01"
                               value="{{ isset($creditLimit) && $creditLimit ? $creditLimit->value : '' }}"
                               placeholder="أدخل القيمة الجديدة"
                               required>
                        <small class="text-muted d-block mt-2">
                            <i class="fa fa-lightbulb text-warning me-1"></i>
                            يجب أن تكون القيمة موجبة
                        </small>
                    </div>

                    {{-- المدّة الائتمانية مع الحدّ في نافذةٍ واحدة.

                         كانت تُضبَط في شاشة إضافة العميل وتعديله وحدهما، بينما
                         الحدُّ له زرٌّ في شاشة العرض — فمن أراد تغيير المدّة
                         وحدها اضطُرّ لفتح نموذج التعديل كلّه. وهما رقمان
                         لسياسةٍ واحدة: كم يُدان العميل، وإلى متى.

                         يظهر الحقل تبعاً لتفعيل «المدة الائتمانية» في الحقول
                         الإضافية — نفس شرط ظهوره في نموذج الإضافة. --}}
                    @php
                        $creditDurationEnabled = collect($GeneralClientSettings ?? [])
                            ->contains(fn ($s) => ($s->key ?? null) === 'credit_duration' && ($s->is_active ?? false));
                    @endphp
                    @if ($creditDurationEnabled)
                        <div class="mb-3">
                            <label for="credit_period_input" class="form-label">المدة الائتمانية (بالأيام):</label>
                            <input type="number"
                                   class="form-control"
                                   id="credit_period_input"
                                   name="credit_period"
                                   min="0"
                                   step="1"
                                   value="{{ isset($client) ? $client->credit_period : '' }}"
                                   placeholder="عدد الأيام">
                            <small class="text-muted d-block mt-2">
                                <i class="fa fa-clock text-info me-1"></i>
                                المهلة الممنوحة للعميل قبل استحقاق السداد. اتركه فارغاً لبقائه بلا مدّة.
                            </small>
                        </div>
                    @endif

                    <input type="hidden" name="client_id" id="client_id" value="{{ isset($client) ? $client->id : '' }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i>إغلاق
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save me-1"></i>حفظ التحديث
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('creditLimitForm');

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            /* كل حقلٍ يُقرأ من داخل هذا النموذج (`form.querySelector`) لا من
               الصفحة كلّها: شاشةُ العميل تحمل نماذجَ كثيرة، و`getElementById`
               تصيب أوّلَ ما تجده أينما كان — وهو ما جعل النافذتين المكرّرتين
               تتنازعان نفس الحقول قبل حذف الثانية. */
            const url = "{{ route('clients.update_credit_limit') }}";
            const formData = new FormData();
            formData.append('_token', form.querySelector('input[name="_token"]').value);
            formData.append('client_id', form.querySelector('#client_id').value);
            formData.append('value', form.querySelector('#credit_limit').value);

            // الحقل غائبٌ حين تكون «المدة الائتمانية» مطفأةً في الحقول
            // الإضافية — لا يُرسَل أصلاً فلا تُمسّ المدّة المحفوظة
            const creditPeriodInput = form.querySelector('#credit_period_input');
            if (creditPeriodInput) {
                formData.append('credit_period', creditPeriodInput.value);
            }

            /* رسائل النظام لا نوافذ المتصفّح.

               كانت الثلاثةُ `alert()` — الصندوق الأسود الذي يكتب فوقه اسمَ
               النطاق. صارت SweetAlert المستعملة في بقيّة الشاشة، والنجاحُ
               شريطٌ صغير يختفي وحده ثمّ تُحدَّث الصفحة. */
            const notify = (icon, title, text) => {
                if (typeof Swal === 'undefined') {
                    alert(text || title);
                    return Promise.resolve();
                }

                return icon === 'success'
                    ? Swal.fire({
                        icon: 'success',
                        title: title,
                        toast: true,
                        position: 'top-start',
                        showConfirmButton: false,
                        timer: 1800,
                        timerProgressBar: true,
                    })
                    : Swal.fire({ icon: icon, title: title, text: text, confirmButtonText: 'حسناً' });
            };

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>جارٍ الحفظ…';
            }

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(async response => {
                /* الردُّ يُقرأ نصّاً أوّلاً ثمّ يُحلَّل.

                   `response.json()` وحدها ترمي «Unexpected token <» حين يعود
                   HTML — صفحةُ دخولٍ بعد انتهاء الجلسة، أو صفحةُ خطأ ٥٠٠ —
                   فيقع في `catch` ويقول «حدث خطأ في الإرسال» بلا سبب مفهوم. */
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
                    closeBsModal('creditLimitModal');

                    notify('success', data.message || 'تم الحفظ بنجاح').then(() => location.reload());
                } else {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalBtnHtml; }
                    notify('error', 'تعذّر الحفظ', data.message || 'حدث خطأ غير متوقّع');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalBtnHtml; }
                notify('error', 'تعذّر الحفظ', error.message || 'حدث خطأ في الإرسال');
            });
        });
    }
});
</script>

@endif
