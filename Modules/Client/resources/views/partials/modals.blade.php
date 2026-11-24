<!-- Modals -->

<!-- Modal ملاحظة الزيارة السريعة (عدد العهدة + صورة من الكاميرا + GPS) -->
{{-- بتصميم نافذة «ملاحظة الزيارة» في مساحة عمل الزيارة (`visit_workspace.blade.php`
     ← `#noteModal`): رأسٌ بسيط، تنبيه الموقع أعلى النافذة، تلميحٌ تحت كل حقل،
     ومساحةُ التقاطٍ من الكاميرا تقبل أكثر من صورة.

     الحقول هي ما يقبله `ClientNoteController::quickVisitNote()` وحده — العهدة
     والصور والموقع. المسؤول ودوامه والملاحظة ورصد المنافسين مربوطةٌ بزيارةٍ
     بدأت (`VisitWorkspaceController::storeNote()`)، ولا زيارة في هذه الشاشة. --}}
@php
    $lastDepositCount = \App\Models\ClientRelation::where('client_id', $client->id)
        ->whereNotNull('deposit_count')
        ->orderBy('created_at', 'desc')
        ->value('deposit_count') ?? 0;
    $vnUploadQuality = \App\Helpers\ClientSettingsHelper::uploadImageQuality();
    $vnUploadMaxPx = \App\Helpers\ClientSettingsHelper::uploadImageMaxDimension();
@endphp
<style>
    /* `cl-help` معرَّفةٌ في settings-theme التي لا تُحمَّل في شاشة العميل */
    #visitNoteModal .cl-help { display: block; font-size: .75rem; color: #6e6b7b; margin-top: .3rem; line-height: 1.6; }
    #visitNoteModal .vn-upload-area { cursor: pointer; transition: background-color .2s ease, border-color .2s ease; }
    #visitNoteModal .vn-upload-area:hover { background-color: #e9ecef !important; border-color: #0d6efd !important; }
</style>
<div class="modal fade" id="visitNoteModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('clients.quick_visit_note') }}" method="POST" enctype="multipart/form-data" id="visitNoteForm">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="current_latitude"  id="visitNoteLat">
                <input type="hidden" name="current_longitude" id="visitNoteLng">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-sticky-note me-1"></i> ملاحظة الزيارة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="visitNoteGpsAlert" class="alert alert-warning py-2 mb-3 small">
                        <i class="fa fa-spinner fa-spin me-1"></i> جاري تحديد موقعك...
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label">عدد العهدة الموجودة <span class="text-danger">*</span></label>
                            <input type="number" name="deposit_count" id="visitNoteDepositCount"
                                class="form-control" min="0" step="1" value="{{ $lastDepositCount }}" required>
                            <small class="cl-help">كم قطعة من عهد شركتنا رأيتَها بعينك الآن في الموقع (ثلاجات، رفوف، ستاندات). الرقم المعبّأ من آخر ملاحظة — عدّل عليه بما رأيتَه اليوم، واكتب 0 إن لم توجد عهدة.</small>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">المرفقات (التقاط من الكاميرا) <span class="text-danger">*</span></label>
                        <small class="cl-help mb-2">
                            صورة أو أكثر تُثبت وجودك في الموقع (الواجهة، الرفّ، العهدة). تُلتقط من الكاميرا مباشرة، ولا تُقبل صورةٌ قديمة من المعرض.
                        </small>
                        {{-- بلا `required` على الحقل المخفي: المتصفّح لا يستطيع التركيز عليه
                             فيرفض الإرسال بصمت. زرّ الحفظ يبقى معطَّلاً حتى تُلتقط صورة. --}}
                        <input type="file" name="attachments[]" multiple id="visitNoteFiles" class="form-control d-none"
                            accept="image/*" capture="environment">

                        <div class="vn-upload-area border rounded p-3 text-center bg-light" onclick="document.getElementById('visitNoteFiles').click()">
                            <i class="fas fa-camera fa-2x text-primary mb-1"></i>
                            <p class="mb-0 text-primary fw-bold small">اضغط هنا لفتح الكاميرا</p>
                            <small class="text-muted d-block">يمكن التقاط أكثر من صورة — تُضغط تلقائياً</small>
                        </div>

                        <div class="row mt-2 g-2">
                            <div class="col-md-6">
                                <label class="form-label small">جودة ضغط الصور</label>
                                <select id="visitNoteImageQuality" class="form-select form-select-sm">
                                    <option value="0.8" @selected(round($vnUploadQuality, 2) == 0.8)>عالية جداً (80%)</option>
                                    <option value="0.6" @selected(round($vnUploadQuality, 2) == 0.6)>عالية (60%)</option>
                                    <option value="0.4" @selected(round($vnUploadQuality, 2) == 0.4)>متوسطة (40%)</option>
                                    <option value="0.2" @selected(round($vnUploadQuality, 2) == 0.2)>منخفضة (20%)</option>
                                </select>
                                <small class="cl-help">تصغير الصورة قبل رفعها فقط — لا يُحفظ كإعداد ولا يؤثّر على صورٍ سابقة. اخفضها إن كانت الشبكة ضعيفة.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">الحد الأقصى لحجم الصورة</label>
                                <select id="visitNoteMaxImageSize" class="form-select form-select-sm">
                                    <option value="1920" @selected($vnUploadMaxPx == 1920)>1920px (عالية الدقة)</option>
                                    <option value="1280" @selected($vnUploadMaxPx == 1280)>1280px (دقة متوسطة)</option>
                                    <option value="800" @selected($vnUploadMaxPx == 800)>800px (دقة منخفضة)</option>
                                </select>
                                <small class="cl-help">أطول ضلعٍ للصورة بعد التصغير. 1280px تكفي لقراءة لافتةٍ أو رفّ؛ ارفعها فقط إن أردت قراءة كتابةٍ دقيقة.</small>
                            </div>
                        </div>

                        <div id="visitNoteProgress" class="mt-2 d-none">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">يتم ضغط الصور...</small>
                        </div>

                        <div id="visitNotePreview" class="mt-2"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="visitNoteSubmit" disabled>حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var modalEl   = document.getElementById('visitNoteModal');
        var form      = document.getElementById('visitNoteForm');
        var fileInput = document.getElementById('visitNoteFiles');
        var preview   = document.getElementById('visitNotePreview');
        var progress  = document.getElementById('visitNoteProgress');
        var submitBtn = document.getElementById('visitNoteSubmit');
        var latInput  = document.getElementById('visitNoteLat');
        var lngInput  = document.getElementById('visitNoteLng');
        var gpsAlert  = document.getElementById('visitNoteGpsAlert');
        var qualityEl = document.getElementById('visitNoteImageQuality');
        var maxSizeEl = document.getElementById('visitNoteMaxImageSize');
        if (!modalEl || !form) return;

        // صلاحية العمل من أي موقع يعفيها الخادم من الموقع (`quickVisitNote()`)،
        // فلا تُحبس الواجهة على GPS لم يُطلب منها
        var ANY_LOCATION = @json(hasPermission(['client_follow_up_add_notes_from_any_location', 'clients_visit_any_location']));

        var gpsOk = false;
        var rawFiles = [];    // الأصل — يُعاد ضغطه متى تغيّرت الجودة أو الحجم
        var processed = [];   // [{file, originalSize, compressedSize, preview}]
        var busy = false;

        function setGps(cls, icon, text) {
            gpsAlert.className = 'alert ' + cls + ' py-2 mb-3 small';
            gpsAlert.innerHTML = '<i class="fa ' + icon + ' me-1"></i> ' + text;
        }

        function updateSubmitState() {
            submitBtn.disabled = busy || !(gpsOk && processed.length > 0);
        }

        function fetchGps() {
            gpsOk = false;
            updateSubmitState();
            if (!('geolocation' in navigator)) {
                setGps('alert-danger', 'fa-exclamation-triangle', 'المتصفح لا يدعم تحديد الموقع.');
                if (ANY_LOCATION) { gpsOk = true; updateSubmitState(); }
                return;
            }
            setGps('alert-warning', 'fa-spinner fa-spin', 'جاري تحديد موقعك...');
            navigator.geolocation.getCurrentPosition(function (pos) {
                latInput.value = pos.coords.latitude;
                lngInput.value = pos.coords.longitude;
                gpsOk = true;
                setGps('alert-success', 'fa-check-circle', 'تم تحديد موقعك. تأكد أنك ضمن نطاق موقع العميل.');
                updateSubmitState();
            }, function () {
                if (ANY_LOCATION) {
                    setGps('alert-info', 'fa-info-circle', 'تعذّر تحديد موقعك، ولديك صلاحية العمل من أي موقع — يمكنك الحفظ.');
                    gpsOk = true;
                } else {
                    setGps('alert-danger', 'fa-exclamation-triangle', 'تعذّر تحديد موقعك — فعّل GPS وأذن الوصول ثم أعد فتح النافذة.');
                }
                updateSubmitState();
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        }

        function formatSize(bytes) {
            if (!bytes) return '0 Bytes';
            var k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function compress(file) {
            return new Promise(function (resolve) {
                var img = new Image();
                img.onload = function () {
                    var quality = parseFloat(qualityEl.value);
                    var maxSize = parseInt(maxSizeEl.value, 10);
                    var width = img.width, height = img.height;
                    var ratio = Math.min(maxSize / width, maxSize / height);
                    if (ratio < 1) { width *= ratio; height *= ratio; }
                    var canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    canvas.toBlob(function (blob) {
                        // فشل الضغط لا يُسقط الصورة: تُرفع الأصلية
                        if (!blob) { resolve({ file: file, originalSize: file.size, compressedSize: file.size, preview: URL.createObjectURL(file) }); return; }
                        var name = (file.name || 'camera_photo').replace(/\.[^.]+$/, '') + '.jpg';
                        resolve({
                            file: new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }),
                            originalSize: file.size,
                            compressedSize: blob.size,
                            preview: URL.createObjectURL(blob),
                        });
                    }, 'image/jpeg', quality);
                };
                img.onerror = function () { resolve(null); };
                img.src = URL.createObjectURL(file);
            });
        }

        function syncFileInput() {
            var dt = new DataTransfer();
            processed.forEach(function (item) { dt.items.add(item.file); });
            fileInput.files = dt.files;
        }

        function renderPreview() {
            preview.innerHTML = '';
            processed.forEach(function (item, index) {
                var savings = item.originalSize > 0 ? ((item.originalSize - item.compressedSize) / item.originalSize * 100).toFixed(1) : 0;
                var row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 border rounded p-2 mb-1 bg-light';
                row.innerHTML = '<img src="' + item.preview + '" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">'
                    + '<div class="flex-grow-1 small">'
                    + '<span class="badge bg-primary">' + formatSize(item.compressedSize) + '</span> '
                    + (savings > 0 ? '<span class="badge bg-success">-' + savings + '%</span>' : '')
                    + '</div>'
                    + '<button type="button" class="btn btn-sm btn-outline-danger" data-index="' + index + '"><i class="fas fa-times"></i></button>';
                preview.appendChild(row);
            });
            syncFileInput();
        }

        async function processFiles(files) {
            busy = true;
            updateSubmitState();
            progress.classList.remove('d-none');
            var bar = progress.querySelector('.progress-bar');
            var result = [];
            for (var i = 0; i < files.length; i++) {
                bar.style.width = ((i / files.length) * 100) + '%';
                var item = await compress(files[i]);
                if (item) result.push(item);
            }
            bar.style.width = '100%';
            progress.classList.add('d-none');
            busy = false;
            return result;
        }

        fileInput.addEventListener('change', async function () {
            // التقاطٌ جديد يُضاف إلى ما سبقه — الكاميرا تُعيد صورةً واحدة في كل مرّة
            var files = Array.from(fileInput.files || []).filter(function (f) {
                if (f.type.startsWith('image/')) return true;
                alert('يُسمح فقط بالصور من الكاميرا');
                return false;
            });
            if (files.length === 0) { syncFileInput(); return; }
            rawFiles = rawFiles.concat(files);
            processed = processed.concat(await processFiles(files));
            renderPreview();
            updateSubmitState();
        });

        preview.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-index]');
            if (!btn) return;
            var index = parseInt(btn.dataset.index, 10);
            processed.splice(index, 1);
            rawFiles.splice(index, 1);
            renderPreview();
            updateSubmitState();
        });

        async function recompress() {
            if (rawFiles.length === 0) return;
            processed = await processFiles(rawFiles);
            renderPreview();
            updateSubmitState();
        }
        qualityEl.addEventListener('change', recompress);
        maxSizeEl.addEventListener('change', recompress);

        modalEl.addEventListener('shown.bs.modal', fetchGps);
        modalEl.addEventListener('hidden.bs.modal', function () {
            form.reset();
            rawFiles = [];
            processed = [];
            preview.innerHTML = '';
            syncFileInput();
            latInput.value = '';
            lngInput.value = '';
            gpsOk = false;
            updateSubmitState();
        });

        form.addEventListener('submit', function (e) {
            if (!gpsOk || (!ANY_LOCATION && (!latInput.value || !lngInput.value))) {
                e.preventDefault();
                setGps('alert-danger', 'fa-exclamation-triangle', 'يجب تحديد موقعك أولاً قبل الحفظ.');
                return;
            }
            if (processed.length === 0) {
                e.preventDefault();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> جاري الحفظ...';
        });
    })();
</script>

<!-- Modal الرصيد الافتتاحي -->
<div class="modal fade" id="modal_opening_balance" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--success-color); color: white;">
                <h5 class="modal-title">إضافة رصيد افتتاحي</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="openingBalanceForm" action="{{ route('clients.updateOpeningBalance', $client->id) }}"
                method="POST">
                @csrf
                <div class="modal-body">
                    {{-- لا اختيار اتجاه هنا بطلب المستخدم: رصيد العميل الافتتاحي
                         دَينٌ عليه لنا دائماً (مدين) — كحال المورد المقابل تماماً،
                         حيث رصيده الافتتاحي مبلغٌ لنا عليه دائماً (دائن). --}}
                    <div class="mb-3">
                        <label class="form-label">المبلغ ({!! $currencySymbol ?? 'SAR' !!})</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="opening_balance"
                            placeholder="أدخل الرصيد الافتتاحي" required>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        يُسجَّل المبلغ ديناً على العميل في حساب ذممه، ويُكتب له قيدٌ افتتاحي.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>حفظ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal حذف العميل -->
<div class="modal fade" id="modal_DELETE1" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--danger-color); color: white;">
                <h5 class="modal-title">حذف العميل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من حذف هذا العميل؟</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>تحذير:</strong> سيؤدي حذف العميل إلى حذف جميع البيانات المرتبطة به
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <form action="{{ route('clients.destroy', $client->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">حذف نهائي</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal تعيين الموظفين -->
<div class="modal fade" id="assignEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--primary-color); color: white;">
                <h5 class="modal-title">تعيين موظفين للعميل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('clients.assign-employees', $client->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اختر الموظفين</label>
                        <select name="employee_id[]" multiple class="form-control select2">
                            @if (isset($employees))
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                        @if ($client->employees && $client->employees->contains('id', $employee->id)) selected @endif>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i>تعيين
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal تسوية الديون المعدومة -->
@include('client::partials.modals.write_off_debt_modal')
<!-- Modal الحد الائتماني -->
@include('client::partials.credit_limit_modal_single_client')
{{-- حُذف `credit_limit_modal`: نافذةٌ ثانيةٌ للحدّ الائتماني كانت تُضمَّن
     هنا بنفس المعرّفات بالضبط (`#creditLimitModal`, `#creditLimitForm`,
     `#client_id`, `#credit_limit`) — فيصير في الصفحة معرّفان مكرّران لكلٍّ.

     وسكربتاهما ينادي كلٌّ منهما `getElementById('creditLimitForm')` فيصيب
     **النموذج الأوّل نفسه**، فيُعلَّق على نموذجٍ واحدٍ معالجا إرسال: كلاهما
     يُرسل الطلب، وأوّلُ ردٍّ يصل ينادي `location.reload()` فيقطع طلبَ
     الآخر في منتصفه، فيقع في `catch` ويصرخ «حدث خطأ في الإرسال» — بينما
     الحفظُ قد تمّ فعلاً. وهو الخطأ الذي كان يظهر للمستخدم بعد كل حفظ. --}}
@include('client::partials.quantity_limit_modal')

{{-- «إضافة موظف العميل» — النافذة نفسها في مساحة عمل الزيارة
     (`visit_workspace.blade.php` ← `#wsContactForm`)، غيّرهما معاً: الاسم والجوال
     والدوام والوظيفة و«رئيسي» وصورةٌ اختيارية. لا ملاحظة ولا ربط فاتورة — الحقلان
     `nullable` في `ClientNoteController::storeEmployeeNote()`.
     يُرسلها `public/js/client/employee-notes.js` (`#employeeNoteForm`)، وهو من
     يعرض معاينة `#noteImageInput` ويمسحها بـ`#removeImageBtn`. --}}
<div class="modal fade" id="addEmployeeNoteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content cl-emp-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-1"></i> إضافة موظف العميل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="employeeNoteForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-sm-7">
                            <label class="form-label">اسم الموظف <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="employee_name"
                                placeholder="موظف المحل لا موظف النظام" maxlength="255" required>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label">الجوال <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="employee_phone"
                                placeholder="05xxxxxxxx" pattern="[0-9+\-\s()]*" maxlength="20" required>
                        </div>

                        {{-- دوام الموظف — الجزئية المشتركة نفسها التي تستعملها مساحة العمل --}}
                        <div class="col-12">
                            @include('client::partials.shift-select')
                        </div>

                        <div class="col-sm-7">
                            <label class="form-label">الوظيفة</label>
                            {{-- لا `select2`: show_client.js يمسح `.select2` ويجبر مودالاً آخر أباً لها --}}
                            <select class="form-control" name="employee_position">
                                <option value="موظف">موظف</option>
                                <option value="مشرف">مشرف</option>
                                <option value="كاشير">كاشير</option>
                                <option value="موظف مبيعات">موظف مبيعات</option>
                                <option value="مدير فرع">مدير فرع</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        <div class="col-sm-5 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_primary" id="isPrimaryCheck" value="1">
                                <label class="form-check-label" for="isPrimaryCheck">موظف رئيسي</label>
                            </div>
                        </div>

                        {{-- صورة الموظف — اختيارية، تُضغط في المتصفّح قبل الإرسال --}}
                        <div class="col-12">
                            <label class="form-label">صورة الموظف <small class="text-muted">(اختيارية)</small></label>
                            <input type="file" class="d-none" name="image" accept="image/*" capture="environment" id="noteImageInput">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('noteImageInput').click()">
                                    <i class="fas fa-camera me-1"></i> إرفاق صورة
                                </button>
                                <span id="imagePreview" style="display: none;">
                                    <img id="previewImg" src="" alt="" style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px; vertical-align: middle;">
                                    <button type="button" class="btn btn-sm btn-light text-danger" id="removeImageBtn" title="إزالة الصورة"><i class="fa fa-times"></i></button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save me-1"></i>حفظ الموظف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .cl-emp-modal { border-radius: 14px; overflow: hidden; }
    .cl-emp-modal .modal-header { padding: .6rem 1rem; }
    .cl-emp-modal .modal-title { font-size: .98rem; font-weight: 800; }
    .cl-emp-modal .modal-body { padding: .8rem 1rem; }
    .cl-emp-modal .form-label { font-size: .82rem; margin-bottom: .2rem; }
    /* شرح قائمة الدوام الطويل — النافذة المختصرة لا تحتاجه */
    .cl-emp-modal [data-shift-select] ~ small.text-muted { display: none !important; }
</style>

<script>
    // صورة موظف العميل: تُضغط (الإعدادات ← حدود المرفقات) ثم تحلّ محلّ الملف الأصلي.
    // المعاينة والمسح في employee-notes.js؛ هنا الضغط وحده.
    (function () {
        const input = document.getElementById('noteImageInput');
        if (!input) return;
        const QUALITY = {{ \App\Helpers\ClientSettingsHelper::uploadImageQuality() }};
        const MAX_PX = {{ \App\Helpers\ClientSettingsHelper::uploadImageMaxDimension() }};

        function compress(file) {
            return new Promise(function (resolve) {
                const img = new Image();
                img.onload = function () {
                    let width = img.width, height = img.height;
                    const ratio = Math.min(MAX_PX / width, MAX_PX / height);
                    if (ratio < 1) { width *= ratio; height *= ratio; }
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    canvas.toBlob(function (blob) {
                        resolve(blob ? new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }) : file);
                    }, 'image/jpeg', QUALITY);
                };
                img.onerror = function () { resolve(file); };
                img.src = URL.createObjectURL(file);
            });
        }

        input.addEventListener('change', async function () {
            const file = input.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const compressed = await compress(file);
            if (compressed !== file) {
                const dt = new DataTransfer();
                dt.items.add(compressed);
                input.files = dt.files;
            }
            document.getElementById('previewImg').src = URL.createObjectURL(compressed);
            document.getElementById('imagePreview').style.display = 'inline-block';
        });

        document.getElementById('addEmployeeNoteModal')?.addEventListener('hidden.bs.modal', function () {
            input.value = '';
            document.getElementById('previewImg').src = '';
            document.getElementById('imagePreview').style.display = 'none';
        });
    })();
</script>
