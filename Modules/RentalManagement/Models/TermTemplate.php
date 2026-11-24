<?php

namespace Modules\RentalManagement\Models;

/** قالب شرطٍ يُنسخ إلى بنود العقد عند إنشائه. */
class TermTemplate extends RealEstateModel
{
    protected $table = 're_term_templates';

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];
}
