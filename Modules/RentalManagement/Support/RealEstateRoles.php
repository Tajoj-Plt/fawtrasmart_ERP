<?php

namespace Modules\RentalManagement\Support;

use App\Constants\permissions\RealEstatePermissions;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * الأدوار الستة النموذجية من وثيقة التصميم، مبنيّةً من أسماء الكتالوج.
 *
 * التثبيت **إضافةٌ فقط**: ينشئ الدور إن غاب ويضيف ما نقصه من صلاحيات
 * العقارات، ولا ينزع صلاحيةً ولا يلمس دوراً آخر — فمن عدّل دوره بيده لا
 * يخسر تعديله.
 */
class RealEstateRoles
{
    /** @return array<string, array{label:string, description:string, permissions:array<int,string>}> */
    public static function definitions(): array
    {
        // `all()` تُرجع الأسماء قائمةً مسطّحة، لا `اسم => تسمية`
        $all = RealEstatePermissions::all();
        $of = fn (string ...$groups) => array_values(array_filter($all, fn ($name) => in_array(explode('.', $name)[1] ?? '', $groups, true)));
        $views = array_values(array_filter($all, fn ($n) => preg_match('/\.(view|show|schedules)$/', $n) && ! str_contains($n, 'settings') && ! str_contains($n, 'logs')));

        return [
            'مدير إدارة العقارات' => [
                'description' => 'كامل الموديول، اعتماد العقود، التقارير، تعديل السياسات.',
                'permissions' => $all,
            ],
            'مسؤول التأجير' => [
                'description' => 'الوحدات، العروض، العملاء العقاريون، المعاينات، مسودات العقود.',
                'permissions' => array_merge($of('dashboard', 'units', 'listings', 'relationships', 'tenants', 'tasks'), [
                    'realestate.buildings.view', 'realestate.buildings.show', 'realestate.owners.view',
                    'realestate.contracts.view', 'realestate.contracts.show', 'realestate.contracts.create', 'realestate.contracts.edit',
                    'realestate.reports.view',
                ]),
            ],
            'مسؤول العقود' => [
                'description' => 'إنشاء/تعديل العقود والتجديدات والمستندات.',
                'permissions' => array_merge($of('dashboard', 'tasks'), [
                    'realestate.buildings.view', 'realestate.buildings.show', 'realestate.units.view', 'realestate.units.show',
                    'realestate.relationships.view', 'realestate.tenants.view', 'realestate.tenants.show', 'realestate.tenants.create', 'realestate.tenants.edit',
                    'realestate.contracts.view', 'realestate.contracts.show', 'realestate.contracts.create',
                    'realestate.contracts.edit', 'realestate.contracts.delete', 'realestate.contracts.renew', 'realestate.contracts.print',
                    'realestate.payments.schedules', 'realestate.reports.view',
                ]),
            ],
            'محصّل / محاسب العقارات' => [
                'description' => 'الاستحقاقات، تسجيل الدفعات، التسويات والتقارير المالية.',
                'permissions' => array_merge($of('dashboard', 'payments'), [
                    'realestate.owners.view', 'realestate.owners.show', 'realestate.owners.transfer', 'realestate.owners.cancel_transfer',
                    'realestate.contracts.view', 'realestate.contracts.show', 'realestate.relationships.view',
                    'realestate.tenants.view', 'realestate.tenants.show',
                    'realestate.tasks.view', 'realestate.tasks.create', 'realestate.tasks.edit',
                    'realestate.reports.view', 'realestate.reports.financial', 'realestate.reports.export',
                ]),
            ],
            'مسؤول الصيانة العقارية' => [
                'description' => 'الطلبات، أوامر العمل، المقاولون، التكاليف.',
                'permissions' => array_merge($of('dashboard', 'maintenance'), [
                    'realestate.buildings.view', 'realestate.buildings.show', 'realestate.units.view', 'realestate.units.show',
                    'realestate.tasks.view', 'realestate.tasks.create', 'realestate.tasks.edit', 'realestate.reports.view',
                ]),
            ],
            'مستخدم قراءة العقارات' => [
                'description' => 'عرض فقط — ويُضيَّق بنطاق البيانات (الفرع/الفريق) من صلاحيات data_scope.',
                'permissions' => array_merge($views, ['realestate.dashboard.view', 'realestate.reports.view']),
            ],
        ];
    }

    /** @return array<int, array{name:string, description:string, exists:bool, count:int, total:int}> */
    public static function status(): array
    {
        $out = [];

        foreach (self::definitions() as $name => $definition) {
            $role = Role::where('name', $name)->first();
            $wanted = array_unique($definition['permissions']);

            $out[] = [
                'name' => $name,
                'description' => $definition['description'],
                'exists' => (bool) $role,
                'count' => $role ? $role->permissions()->whereIn('name', $wanted)->count() : 0,
                'total' => count($wanted),
            ];
        }

        return $out;
    }

    /** @return array<int, string> أسماء الأدوار التي أُنشئت الآن */
    public static function install(): array
    {
        $created = [];

        DB::transaction(function () use (&$created) {
            foreach (self::definitions() as $name => $definition) {
                $role = Role::where('name', $name)->where('guard_name', 'web')->first();

                if (! $role) {
                    $role = Role::create(['name' => $name, 'guard_name' => 'web']);
                    $created[] = $name;
                }

                $ids = Permission::whereIn('name', array_unique($definition['permissions']))->where('guard_name', 'web')->pluck('id');
                $role->permissions()->syncWithoutDetaching($ids);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }
}
