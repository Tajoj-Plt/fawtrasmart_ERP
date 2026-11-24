<?php

namespace Modules\Client\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Reservations\Services\ServiceProductCatalog;

/**
 * ملفُّ حجز منتجٍ خدمي — المدّةُ والتحضيرُ والتنظيفُ والموظفون.
 *
 * **لا اسمَ ولا سعرَ ولا ضريبة هنا.** تلك من المنتج، فلا يُحفَظ لها ثانٍ
 * يفترق عن الفاتورة. والمنتجُ إلزاميّ، ومن نوع «خدمي»، وملفُّه واحد.
 */
class StoreReservationServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission(
            $this->route('service') ? 'reservation_services.edit' : 'reservation_services.create'
        );
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required', 'integer', 'exists:products,id',
                Rule::unique('reservation_services', 'product_id')
                    ->ignore($this->route('service'))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],

            'duration' => ['required', 'integer', 'min:1', 'max:1440'],
            'buffer_before' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'buffer_after' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'max_daily' => ['nullable', 'integer', 'min:1'],

            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'employees' => ['nullable', 'array'],
            'employees.*' => ['integer', 'exists:employees,id'],
        ];
    }

    /** المنتجُ خدميٌّ فعلاً — `exists` وحدها تقبل سلعةً مخزنية. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('product_id') || $validator->errors()->has('product_id')) {
                return;
            }

            try {
                ServiceProductCatalog::resolve($this->integer('product_id'));
            } catch (ReservationException $e) {
                $validator->errors()->add('product_id', $e->getMessage());
            }
        });
    }

    public function attributes(): array
    {
        return [
            'product_id' => __('reservations.columns.service'),
            'duration' => __('reservations.attributes.duration'),
            'buffer_before' => __('reservations.attributes.buffer_before'),
            'buffer_after' => __('reservations.attributes.buffer_after'),
            'max_daily' => __('reservations.attributes.max_daily'),
            'employees' => __('reservations.attributes.employees'),
        ];
    }
}
