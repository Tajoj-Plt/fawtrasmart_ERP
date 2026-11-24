<?php

namespace Modules\RentalManagement\Models;

use Illuminate\Database\Eloquent\Model;

class SavedReport extends Model
{
    protected $table = 're_saved_reports';

    protected $guarded = ['id'];

    protected $casts = ['filters' => 'array'];
}
