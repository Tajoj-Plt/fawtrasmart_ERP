{{--
    اسم وحدة قياس بند المواد الخام.

    الترتيب: وحدة البند في أمر التصنيع، ثم الوحدة الافتراضية للمنتج، ثم
    الوحدة الأساسية لصنفه. كان هذا التسلسل مكتوباً بيده في شاشتي الاستلام
    والإرجاع بصيغتين مختلفتين قليلاً.
--}}
@php
    $unitName = 'وحدة';
    $product = $item->rawProduct ?? null;

    if ($item->rawUnit && $item->rawUnit->categoryUnit && $item->rawUnit->categoryUnit->unit) {
        $unitName = $item->rawUnit->categoryUnit->unit->name;
    } elseif ($product) {
        $productUnits = $product->productUnits ?? collect();

        if ($productUnits->count()) {
            $default = $productUnits->firstWhere('is_default', true) ?? $productUnits->first();
            $unitName = optional(optional($default->categoryUnit)->unit)->name ?? $unitName;
        } elseif ($product->category && ($product->category->categoryUnits ?? collect())->count()) {
            $units = $product->category->categoryUnits;
            $base = $units->firstWhere('is_base_unit', true) ?? $units->first();
            $unitName = optional($base->unit)->name ?? $unitName;
        }
    }
@endphp
{{ $unitName }}
