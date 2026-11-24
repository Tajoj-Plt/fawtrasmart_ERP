@extends('sitting::master')

@section('title')
    {{ __('settings.doc_templates.create_title') }}
@endsection

@section('content')
<style>
    .create-card {
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
        border: none;
        background: #fff;
        padding: 30px;
    }
    .base-card-select {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        margin-bottom: 15px;
    }
    .base-card-select:hover {
        border-color: #7367F0;
        background: #f8fafc;
    }
    .base-card-select.selected {
        border-color: #7367F0;
        background: #f3f2fe;
        box-shadow: 0 0 0 3px rgba(115, 103, 240, 0.2);
    }
</style>

{{-- رسائلُ الحفظ وأخطاءُ التحقّق: كانت الشاشةُ لا تعرض شيئاً منها، فحفظٌ
     فاشلٌ يعيد المحرّرَ كما كان بلا كلمة --}}
@include('layouts.alerts.success')
@include('layouts.alerts.error')
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2>{{ __('settings.doc_templates.create_title') }}</h2>
            <p class="text-muted">{{ __('settings.doc_templates.create_subtitle') }}</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <div class="create-card">
                <form action="{{ route('document-templates.store') }}" method="POST" id="createTemplateForm">
                    @csrf

                    <div class="form-group mb-4">
                        <label class="form-label font-weight-bold">{{ __('settings.doc_templates.name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg" placeholder="{{ __('settings.doc_templates.name_placeholder') }}" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-4">
                            <label class="form-label font-weight-bold">{{ __('settings.doc_templates.document_type') }} <span class="text-danger">*</span></label>
                            <select name="document_type" id="document_type_select" class="form-control" required>
                                <option value="">{{ __('settings.doc_templates.choose_document') }}</option>
                                {{-- `?document_type=` يصل من شاشة تصاميم المبيعات: من ضغط
                                     «أنشئ قالباً لمرتجع مبيعات» يجد النوع مختاراً --}}
                                @foreach($documentTypes as $key => $name)
                                    <option value="{{ $key }}" @selected(old('document_type', request('document_type')) === $key)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-4">
                            <label class="form-label font-weight-bold">{{ __('settings.doc_templates.print_paper_size') }} <span class="text-danger">*</span></label>
                            <select name="paper_size" id="paper_size_select" class="form-control" required>
                                <option value="">{{ __('settings.doc_templates.choose_size') }}</option>
                                @foreach($paperSizes as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row" id="custom_size_fields" style="display: none;">
                        <div class="col-md-6 form-group mb-4">
                            <label class="form-label">{{ __('settings.doc_templates.width_mm') }}</label>
                            <input type="number" step="0.1" name="width" class="form-control" placeholder="{{ __('settings.doc_templates.width_placeholder') }}">
                        </div>
                        <div class="col-md-6 form-group mb-4">
                            <label class="form-label">{{ __('settings.doc_templates.height_mm') }}</label>
                            <input type="number" step="0.1" name="height" class="form-control" placeholder="{{ __('settings.doc_templates.height_placeholder') }}">
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label font-weight-bold">{{ __('settings.doc_templates.orientation') }}</label>
                        <select name="orientation" class="form-control">
                            <option value="portrait">{{ __('settings.doc_templates.portrait') }}</option>
                            <option value="landscape">{{ __('settings.doc_templates.landscape') }}</option>
                        </select>
                    </div>

                    <!-- Base Templates Chooser -->
                    <div class="form-group mb-5">
                        <label class="form-label font-weight-bold">{{ __('settings.doc_templates.base_template') }}</label>
                        <p class="text-muted small">{{ __('settings.doc_templates.base_template_hint') }}</p>
                        
                        <input type="hidden" name="base_template_id" id="base_template_id_val">
                        
                        <div class="row" id="ready_templates_container">
                            <div class="col-12 text-center text-muted py-3" id="select_prompt_msg">
                                <i class="fas fa-arrow-up fa-2x mb-2"></i>
                                <p>{{ __('settings.doc_templates.pick_first') }}</p>
                            </div>
                            
                            @foreach($readyTemplates as $base)
                                <div class="col-md-6 base-template-option-card" 
                                     data-doc-type="{{ $base->document_type }}" 
                                     data-size="{{ $base->paper_size }}" 
                                     data-id="{{ $base->id }}" 
                                     style="display: none;">
                                    <div class="base-card-select" onclick="selectBaseTemplate(this, {{ $base->id }})">
                                        <h5 class="font-weight-bold mb-1"><i class="fas fa-file-layout mr-2 text-primary"></i> {{ $base->name }}</h5>
                                        <p class="text-muted small mb-0">{{ __('settings.doc_templates.base_meta', ['size' => $base->paper_size, 'orientation' => $base->orientation]) }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('document-templates.index') }}" class="btn btn-outline-secondary btn-lg">{{ __('settings.common.cancel') }}</a>
                        <button type="submit" class="btn btn-primary btn-lg shadow-sm">{{ __('settings.doc_templates.save_and_edit') }} <i class="fas fa-arrow-left ml-2"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const docSelect = document.getElementById('document_type_select');
        const sizeSelect = document.getElementById('paper_size_select');
        const customFields = document.getElementById('custom_size_fields');

        // Show custom width/height fields if custom size is chosen
        sizeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customFields.style.display = 'flex';
            } else {
                customFields.style.display = 'none';
            }
            filterBaseTemplates();
        });

        docSelect.addEventListener('change', filterBaseTemplates);

        function filterBaseTemplates() {
            const docType = docSelect.value;
            const size = sizeSelect.value;
            const cards = document.querySelectorAll('.base-template-option-card');
            const prompt = document.getElementById('select_prompt_msg');
            
            // Reset selection
            document.getElementById('base_template_id_val').value = '';
            document.querySelectorAll('.base-card-select').forEach(el => el.classList.remove('selected'));
            
            let visibleCount = 0;

            if (docType && size) {
                prompt.style.display = 'none';
                cards.forEach(card => {
                    const cDoc = card.getAttribute('data-doc-type');
                    const cSize = card.getAttribute('data-size');
                    if (cDoc === docType && cSize === size) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                if (visibleCount === 0) {
                    prompt.style.display = 'block';
                    prompt.innerHTML = '<i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i><p>' + @json(__('settings.doc_templates.no_base')) + '</p>';
                }
            } else {
                prompt.style.display = 'block';
                prompt.innerHTML = '<i class="fas fa-arrow-up fa-2x mb-2"></i><p>{{ __('settings.doc_templates.pick_first') }}</p>';
                cards.forEach(card => card.style.display = 'none');
            }
        }
    });

    function selectBaseTemplate(element, id) {
        document.querySelectorAll('.base-card-select').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('base_template_id_val').value = id;
    }
</script>
@endsection
