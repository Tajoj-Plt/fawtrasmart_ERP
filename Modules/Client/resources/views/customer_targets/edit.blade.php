{{--
    تعديل هدف عميل — أُعيد بناؤها بنفس نظام تصميم الوحدة الفعلي
    (`.cl-scope` + `partials/settings-theme` + `components/page-head`/`guide`)
    بدل CSS مكتوبةٍ يدوياً داخل `@section('css')` — قسمٌ `client::master` لا
    يعرضه أصلاً (`@stack('styles')` لا `@yield('css')`)، فلم يكن يصل الصفحة.
--}}
@extends('client::master')

@section('title')
    تعديل هدف عميل
@stop

@section('content')
    @include('client::partials.settings-theme')
    @include('components.hint-tips')

    <div class="cl-scope">
        @section('page-actions')
            <a href="{{ route('client.customer_targets.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                <span>رجوع</span>
            </a>
        @endsection

        @include('client::components.page-head', [
            'title' => 'تعديل هدف: '.$target->name,
            'icon' => 'target',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'أهداف العملاء' => route('client.customer_targets.index'),
                'تعديل' => null,
            ],
        ])

        @include('client::components.guide', [
            'key' => 'clients.customer_targets.edit',
            'open' => false,
            'title' => 'آلية التقييم والأولوية',
            'blocks' => [
                'معدّل التحصيل الشهري' => [
                    'يُحسَب بقسمة إجمالي ما سدّده العميل على عدد أشهر العلاقة معه (من تاريخ أوّل فاتورة). عميلٌ سدّد 2000 ريال خلال 7 أشهر معدّله 285 ريال/شهر (ذهبي مثلاً)، وآخر سدّد 192 ريال فقط منذ 7 أشهر ولم يسدّد بعدها معدّله 27 ريال/شهر فقط (خامل).',
                ],
                'الأولوية' => [
                    'يُفحص العميل من الهدف الأعلى أولويةً (الرقم الأكبر) إلى الأدنى، وأوّل هدفٍ يستوفي شروطه يُمنَح له فوراً.',
                ],
            ],
        ])

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('client.customer_targets.update', $target->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="cl-req">اسم الهدف</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $target->name) }}" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label>اللون للتمييز</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="{{ old('color', $target->color) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>الأولوية</label>
                            <input type="number" name="priority" class="form-control" value="{{ old('priority', $target->priority) }}">
                            <small class="cl-help">يُفحص العميل بالأهداف ذات الأولوية الأعلى أولاً.</small>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-center">
                            <label class="cl-switch">
                                <input type="checkbox" name="is_active" {{ old('is_active', $target->is_active) ? 'checked' : '' }}>
                                <span class="cl-switch__track"><span class="cl-switch__thumb"></span></span>
                                <span>نشط وفعال</span>
                            </label>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label>الوصف المفسّر للهدف</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $target->description) }}</textarea>
                        </div>
                    </div>

                    <div class="cl-section">
                        <div class="cl-section__head">
                            <i class="fas fa-filter"></i>
                            <h6 class="cl-section__title">شروط التقييم واحتساب الهدف</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="cl-req">الحد الأدنى لمعدل السداد/التحصيل الشهري (ريال/شهر)</label>
                                <input type="number" step="0.01" name="min_sales" class="form-control" value="{{ old('min_sales', $target->min_sales) }}">
                                <small class="cl-help">إجمالي التحصيلات مقسومة على أشهر العلاقة. (مثال للذهبي: 150)</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الحد الأدنى لإجمالي التحصيلات (اختياري)</label>
                                <input type="number" step="0.01" name="min_collections" class="form-control" value="{{ old('min_collections', $target->min_collections) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الحد الأقصى لأيام التحصيل (اختياري)</label>
                                <input type="number" name="max_avg_collection_days" class="form-control" value="{{ old('max_avg_collection_days', ($target->max_avg_collection_days == 999 || $target->max_avg_collection_days == 0) ? '' : $target->max_avg_collection_days) }}" placeholder="اتركه فارغاً أو 0 لإلغاء الشرط">
                                <small class="cl-help">اتركه فارغاً أو 0 إذا كنت تريد التقييم بناءً على قوة السداد الشهري فقط.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cl-form-foot">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التعديلات</button>
                    <a href="{{ route('client.customer_targets.index') }}" class="btn btn-outline-secondary"><i class="fas fa-times"></i> إلغاء</a>
                </div>
            </div>
        </form>
    </div>
@endsection
