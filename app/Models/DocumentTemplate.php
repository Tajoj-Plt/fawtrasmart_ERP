<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class DocumentTemplate extends Model
{
    protected $table = 'document_templates';

    protected $fillable = [
        'name',
        'document_type',
        'template_type',
        'paper_size',
        'width',
        'height',
        'orientation',
        'content',
        'styles',
        'settings',
        'is_default',
        'is_active',
        'user_id',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'width' => 'double',
        'height' => 'double',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->user_id = $model->user_id ?? Auth::id();
                $model->branch_id = $model->branch_id ?? Auth::user()->branch_id;
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        // Ensure default uniqueness per branch/user and document_type + paper_size
        static::saved(function ($model) {
            if ($model->is_default) {
                self::where('id', '!=', $model->id)
                    ->where('document_type', $model->document_type)
                    ->where('paper_size', $model->paper_size)
                    ->where('branch_id', $model->branch_id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
