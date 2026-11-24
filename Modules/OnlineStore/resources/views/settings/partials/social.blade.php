{{-- إعدادات التواصل الاجتماعي --}}
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="feather icon-info"></i>
            أدخل روابط حسابات التواصل الاجتماعي الخاصة بمتجرك. سيتم عرضها في تذييل الموقع وصفحة التواصل.
        </div>
    </div>
</div>

<div class="row">
    {{-- فيسبوك --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_facebook">
                <i class="fa fa-facebook text-primary"></i> فيسبوك
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-link"></i></span>
                </div>
                <input type="url" 
                       name="social_facebook" 
                       id="social_facebook"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_facebook')->first()->value ?? '' }}"
                       placeholder="https://facebook.com/yourpage">
            </div>
            <small class="text-muted">رابط صفحة فيسبوك الخاصة بالمتجر</small>
        </div>
    </div>

    {{-- تويتر (X) --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_twitter">
                <i class="fa fa-twitter text-info"></i> تويتر (X)
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-link"></i></span>
                </div>
                <input type="url" 
                       name="social_twitter" 
                       id="social_twitter"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_twitter')->first()->value ?? '' }}"
                       placeholder="https://twitter.com/yourhandle">
            </div>
            <small class="text-muted">رابط حساب تويتر (X)</small>
        </div>
    </div>
</div>

<div class="row">
    {{-- انستقرام --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_instagram">
                <i class="fa fa-instagram text-danger"></i> انستقرام
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-link"></i></span>
                </div>
                <input type="url" 
                       name="social_instagram" 
                       id="social_instagram"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_instagram')->first()->value ?? '' }}"
                       placeholder="https://instagram.com/yourhandle">
            </div>
            <small class="text-muted">رابط حساب انستقرام</small>
        </div>
    </div>

    {{-- واتساب --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_whatsapp">
                <i class="fa fa-whatsapp text-success"></i> واتساب
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-phone"></i></span>
                </div>
                <input type="text" 
                       name="social_whatsapp" 
                       id="social_whatsapp"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_whatsapp')->first()->value ?? '' }}"
                       placeholder="+966501234567"
                       dir="ltr">
            </div>
            <small class="text-muted">رقم واتساب مع رمز الدولة (مثال: +966501234567)</small>
        </div>
    </div>
</div>

<div class="row">
    {{-- يوتيوب --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_youtube">
                <i class="fa fa-youtube text-danger"></i> يوتيوب
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-link"></i></span>
                </div>
                <input type="url" 
                       name="social_youtube" 
                       id="social_youtube"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_youtube')->first()->value ?? '' }}"
                       placeholder="https://youtube.com/@yourchannel">
            </div>
            <small class="text-muted">رابط قناة يوتيوب</small>
        </div>
    </div>

    {{-- تيك توك --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_tiktok">
                <i class="fa fa-music"></i> تيك توك
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-link"></i></span>
                </div>
                <input type="url" 
                       name="social_tiktok" 
                       id="social_tiktok"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_tiktok')->first()->value ?? '' }}"
                       placeholder="https://tiktok.com/@yourhandle">
            </div>
            <small class="text-muted">رابط حساب تيك توك</small>
        </div>
    </div>
</div>

<div class="row">
    {{-- سناب شات --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_snapchat">
                <i class="fa fa-snapchat text-warning"></i> سناب شات
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-link"></i></span>
                </div>
                <input type="url" 
                       name="social_snapchat" 
                       id="social_snapchat"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_snapchat')->first()->value ?? '' }}"
                       placeholder="https://snapchat.com/add/yourhandle">
            </div>
            <small class="text-muted">رابط حساب سناب شات</small>
        </div>
    </div>

    {{-- لينكد إن --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_linkedin">
                <i class="fa fa-linkedin text-primary"></i> لينكد إن
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-link"></i></span>
                </div>
                <input type="url" 
                       name="social_linkedin" 
                       id="social_linkedin"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_linkedin')->first()->value ?? '' }}"
                       placeholder="https://linkedin.com/company/yourcompany">
            </div>
            <small class="text-muted">رابط صفحة لينكد إن</small>
        </div>
    </div>
</div>

<div class="row">
    {{-- تيليجرام --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="social_telegram">
                <i class="fa fa-telegram text-info"></i> تيليجرام
            </label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-link"></i></span>
                </div>
                <input type="url" 
                       name="social_telegram" 
                       id="social_telegram"
                       class="form-control"
                       value="{{ $settings->where('key', 'social_telegram')->first()->value ?? '' }}"
                       placeholder="https://t.me/yourchannel">
            </div>
            <small class="text-muted">رابط قناة أو مجموعة تيليجرام</small>
        </div>
    </div>
</div>

<hr>

{{-- معاينة الأيقونات --}}
<div class="row">
    <div class="col-12">
        <h6><i class="feather icon-eye"></i> معاينة أيقونات التواصل</h6>
        <div class="social-preview d-flex flex-wrap gap-2 mt-2">
            <a href="#" class="btn btn-outline-primary btn-sm social-preview-btn" data-field="social_facebook" title="فيسبوك">
                <i class="fa fa-facebook"></i>
            </a>
            <a href="#" class="btn btn-outline-info btn-sm social-preview-btn" data-field="social_twitter" title="تويتر">
                <i class="fa fa-twitter"></i>
            </a>
            <a href="#" class="btn btn-outline-danger btn-sm social-preview-btn" data-field="social_instagram" title="انستقرام">
                <i class="fa fa-instagram"></i>
            </a>
            <a href="#" class="btn btn-outline-success btn-sm social-preview-btn" data-field="social_whatsapp" title="واتساب">
                <i class="fa fa-whatsapp"></i>
            </a>
            <a href="#" class="btn btn-outline-danger btn-sm social-preview-btn" data-field="social_youtube" title="يوتيوب">
                <i class="fa fa-youtube"></i>
            </a>
            <a href="#" class="btn btn-outline-dark btn-sm social-preview-btn" data-field="social_tiktok" title="تيك توك">
                <i class="fa fa-music"></i>
            </a>
            <a href="#" class="btn btn-outline-warning btn-sm social-preview-btn" data-field="social_snapchat" title="سناب شات">
                <i class="fa fa-snapchat"></i>
            </a>
            <a href="#" class="btn btn-outline-primary btn-sm social-preview-btn" data-field="social_linkedin" title="لينكد إن">
                <i class="fa fa-linkedin"></i>
            </a>
            <a href="#" class="btn btn-outline-info btn-sm social-preview-btn" data-field="social_telegram" title="تيليجرام">
                <i class="fa fa-telegram"></i>
            </a>
        </div>
        <small class="text-muted d-block mt-2">
            <i class="feather icon-info"></i> الأيقونات المعبأة ستظهر ملونة، والفارغة ستكون باهتة
        </small>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // تحديث معاينة الأيقونات
    function updateSocialPreview() {
        document.querySelectorAll('.social-preview-btn').forEach(function(btn) {
            var field = btn.dataset.field;
            var input = document.getElementById(field);
            if (input && input.value.trim() !== '') {
                btn.classList.remove('btn-outline-secondary');
                btn.style.opacity = '1';
            } else {
                btn.style.opacity = '0.4';
            }
        });
    }

    // تحديث عند تغيير القيم
    document.querySelectorAll('input[name^="social_"]').forEach(function(input) {
        input.addEventListener('input', updateSocialPreview);
    });

    // تحديث أولي
    updateSocialPreview();
});
</script>
