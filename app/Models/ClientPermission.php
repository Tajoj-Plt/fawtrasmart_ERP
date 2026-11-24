<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * مفتاحٌ من مفاتيح بوّابة العميل — تعريفُه في الكود
 * (`\App\Support\ClientPortalPermissions`) وحالتُه هنا.
 */
class ClientPermission extends Model
{
    protected $table = 'client_permissions';

    /**
     * `key` و`name` قابلان للتعبئة من أجل الزرع: `db:seed` يرفع الحراسة
     * عن النماذج فيمرّان، لكنّ `DatabaseInstaller` يستدعي البذور مباشرةً
     * بلا رفعٍ — فكان الصفّ يُنشأ بمفتاحٍ فارغ.
     */
    protected $fillable = ['key', 'name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
