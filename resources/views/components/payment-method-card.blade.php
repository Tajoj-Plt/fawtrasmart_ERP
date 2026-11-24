@props(['title', 'icon', 'index', 'checked' => false])

<div class="payment-method-item">
    <div class="payment-method-card">
        <div class="payment-method-header">
            <div class="payment-icon-wrapper">
                <i class="{{ $icon }}"></i>
            </div>
            <div class="payment-info">
                <h5 class="payment-title">{{ $title }}</h5>
                @if ($index !== null)
                    <small class="payment-index">الترتيب: {{ $index }}</small>
                @endif
            </div>
        </div>

        <div class="payment-method-footer">
            <div x-data="{ isChecked: {{ $checked ? 'true' : 'false' }} }" class="switch-container">
                <span class="switch-label" :class="{ 'active': isChecked }">
                    <span x-show="isChecked" class="status-text">مفعّل</span>
                    <span x-show="!isChecked" class="status-text">معطّل</span>
                </span>
                
                <label class="modern-switch">
                    <input type="checkbox" 
                        x-model="isChecked"
                        x-on:change="$refs.hiddenInput.value = isChecked ? 'active' : 'inactive'"
                    >
                    <span class="slider"></span>
                </label>

                <input type="hidden" x-ref="hiddenInput" name="payments[{{ $index }}][status]" 
                    :value="isChecked ? 'active' : 'inactive'">
            </div>
        </div>
    </div>
</div>



<style>
.payment-method-item {
    width: 100%;
    height: 100%;
}

.payment-method-card {
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    transition: all 0.3s ease;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    cursor: pointer;
}

.payment-method-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.12);
    border-color: #6366f1;
}

.payment-method-header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.payment-icon-wrapper {
    background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 100%);
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    flex-shrink: 0;
}

.payment-icon-wrapper i {
    font-size: 1.75rem;
}

.payment-info {
    flex: 1;
    text-align: right;
}

.payment-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.25rem 0;
}

.payment-index {
    color: #9ca3af;
    font-size: 0.8rem;
}

.payment-method-footer {
    background-color: #f8fafc;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.switch-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    justify-content: space-between;
}

.switch-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #9ca3af;
    transition: color 0.3s ease;
}

.switch-label.active {
    color: #10b981;
}

.status-text {
    display: inline-block;
    min-width: 50px;
}

/* Modern Toggle Switch */
.modern-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
    cursor: pointer;
}

.modern-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 28px;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15), 0 1px 2px rgba(0, 0, 0, 0.1);
}

.modern-switch input:checked + .slider {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}

.modern-switch input:checked + .slider:before {
    transform: translateX(24px);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3), 0 1px 3px rgba(0, 0, 0, 0.1);
}

.modern-switch:hover .slider {
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.modern-switch input:checked:hover + .slider {
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}


.custom-switch .custom-control-label::after {
    height: 18px !important;
    width: 18px !important;
    border-radius: 50% !important;
}
</style>
