<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitCompetitorObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        // الرصد يُسجَّل داخل الملاحظة لا في مودالٍ منفصل — هذا رابطه بها
        'client_relation_id',
        'client_id',
        'employee_id',
        'competitor_id',
        // يبقى مكتوباً مع كل رصد (لقطةُ الاسم لحظتَه) حتى لا تتغيّر
        // التقارير التاريخية بإعادة تسمية المنافس في الكتالوج
        'competitor_name',
        'asset_count',
        'notes',
        'price',
        'photo_path',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'asset_count' => 'integer',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function competitor()
    {
        return $this->belongsTo(Competitor::class);
    }

    public function note()
    {
        return $this->belongsTo(ClientRelation::class, 'client_relation_id');
    }

    /** اسم الكتالوج إن بقي، وإلّا لقطةُ الاسم المحفوظة مع الرصد. */
    public function getDisplayNameAttribute(): string
    {
        return $this->competitor->name ?? ($this->competitor_name ?: 'منافس');
    }
}
