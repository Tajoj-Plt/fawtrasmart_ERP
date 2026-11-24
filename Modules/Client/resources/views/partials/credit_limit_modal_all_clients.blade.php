{{-- Modal تحديث الحد الائتماني لجميع العملاء (قائمة العملاء) --}}
<div class="modal fade" id="creditLimitModal" tabindex="-1" aria-labelledby="creditLimitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="creditLimitModalLabel">تحديث الحد الائتماني لجميع العملاء</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="creditLimitForm" action="{{ route('clients.update_all_credit_limits') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="fa fa-info-circle me-2"></i>
                        <strong>ملاحظة:</strong> سيتم تطبيق هذا الحد الائتماني على جميع العملاء التي لديك صلاحية الوصول إليهم
                    </div>

                    <div class="mb-3">
                        <label for="credit_limit_all" class="form-label">الحد الائتماني الموحد:</label>
                        <input type="number" class="form-control" id="credit_limit_all" name="value" step="0.01" placeholder="أدخل الحد الائتماني" required>
                        <small class="text-muted d-block mt-2">
                            <i class="fa fa-warning text-warning me-1"></i>
                            هذا سيحدث جميع العملاء دفعة واحدة
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="exclude_clients_select" class="form-label">
                            <i class="fa fa-filter me-2"></i>
                            <strong>استثناء العملاء التالية:</strong>
                        </label>

                        <select class="form-select select2-multiple" id="exclude_clients_select" name="exclude_clients[]" multiple="multiple" style="width: 100%;">
                            <option value="">جاري التحميل...</option>
                        </select>

                        <small class="d-block text-muted mt-2">
                            <i class="fa fa-info-circle me-1"></i>
                            اختر العملاء الذين لا تريد تحديث الحد الائتماني لهم
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="submit" class="btn btn-primary">تحديث لجميع العملاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let select2Initialized = false;

    // تهيئة Select2 مع Ajax
    function initializeSelect2() {
        if (select2Initialized) return;

        const $selectElement = $('#exclude_clients_select');

        if ($selectElement.length && !$selectElement.hasClass('select2-hidden-accessible')) {
            $selectElement.select2({
                placeholder: 'ابحث عن العملاء للاستثناء...',
                allowClear: true,
                width: '100%',
                dir: 'rtl',
                dropdownParent: $('#creditLimitModal'),
                language: {
                    noResults: function () {
                        return 'لم يتم العثور على عملاء';
                    },
                    searching: function () {
                        return 'جاري البحث...';
                    },
                    loadingMore: function() {
                        return 'جاري تحميل المزيد...';
                    }
                },
                ajax: {
                    url: '{{ route("clients.get_for_select") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            search: params.term,
                            page: params.page || 1
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;

                        return {
                            results: data.data.map(function(client) {
                                return {
                                    id: client.id,
                                    text: client.trade_name + ' (' + client.code + ')'
                                };
                            }),
                            pagination: {
                                more: (params.page * 30) < data.total
                            }
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            select2Initialized = true;
            console.log('✅ Select2 تم تهيئته بنجاح');
        }
    }

    // عند فتح الـ Modal
    const creditLimitModal = document.getElementById('creditLimitModal');
    if (creditLimitModal) {
        creditLimitModal.addEventListener('show.bs.modal', function() {
            setTimeout(function() {
                initializeSelect2();
            }, 150);
        });

        // إعادة تعيين عند إغلاق الـ Modal
        creditLimitModal.addEventListener('hidden.bs.modal', function() {
            const $selectElement = $('#exclude_clients_select');
            if ($selectElement.hasClass('select2-hidden-accessible')) {
                $selectElement.select2('destroy');
                select2Initialized = false;
            }
        });
    }

    // معالجة الإرسال
    const form = document.getElementById('creditLimitForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const url = "{{ route('clients.update_all_credit_limits') }}";
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('value', document.getElementById('credit_limit_all').value);

            const selectedClients = $('#exclude_clients_select').val() || [];
            selectedClients.forEach(clientId => {
                formData.append('exclude_clients[]', clientId);
            });

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'نجح!',
                        html: data.message,
                        confirmButtonText: 'موافق'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        html: data.message,
                        confirmButtonText: 'موافق'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ!',
                    text: 'حدث خطأ في الإرسال',
                    confirmButtonText: 'موافق'
                });
            });
        });
    }
});
</script>