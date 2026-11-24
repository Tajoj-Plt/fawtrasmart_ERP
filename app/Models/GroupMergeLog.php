<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMergeLog extends Model
{
    protected $table = 'group_merge_logs';
    protected $fillable = [
        'source_group_id',
        'target_group_id',
        'source_group_name',
        'target_group_name',
        'moved_count',
        'clients_backup',
        'is_reversed',
        'reversed_at'
    ];

    protected $casts = [
        'clients_backup' => 'json',
        'reversed_at' => 'datetime',
    ];

    public function sourceGroup()
    {
        return $this->belongsTo(Region_groub::class, 'source_group_id');
    }

    public function targetGroup()
    {
        return $this->belongsTo(Region_groub::class, 'target_group_id');
    }
}
