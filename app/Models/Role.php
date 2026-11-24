<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * جدول `roles`.
 *
 * الأعمدة كما هي في القاعدة: `id`, `name`, `guard_name`,
 * `unified_sidebar`, `mobile_only`, `created_at`, `updated_at`.
 */
class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'guard_name',
        'unified_sidebar',
        'mobile_only',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'unified_sidebar' => 'boolean',
        'mobile_only' => 'boolean',
    ];

    /** المستخدمون الذين هذا دورهم الأساسي — عبر `users.role_id`. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /** صلاحيات الدور — عبر `role_has_permissions`. */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
