<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            // إضافة للمخزن
            ['id' => 1, 'name' => 'اذن إضافة مخزني', 'category' => 'إضافة'],

            // صرف من المخزن
            ['id' => 2, 'name' => 'اذن صرف مخزني', 'category' => 'صرف'],

            // سحب من المخزن
            ['id' => 18, 'name' => 'اذن سحب مخزني', 'category' => 'سحب'],

            // أوامر التصنيع
            ['id' => 3, 'name' => 'أمر تصنيع المنتج الرئيسي', 'category' => 'تصنيع'],
            ['id' => 4, 'name' => 'أمر تصنيع المواد الهالكة', 'category' => 'تصنيع'],
            ['id' => 16, 'name' => 'أمر تصنيع البنود المرتجعة', 'category' => 'تصنيع'],
            ['id' => 17, 'name' => 'أمر تصنيع لصرف المواد', 'category' => 'تصنيع'],

            // الجرد
            ['id' => 5, 'name' => 'ورقة جرد منصرف', 'category' => 'جرد'],
            ['id' => 6, 'name' => 'ورقة جرد وارد', 'category' => 'جرد'],
            ['id' => 19, 'name' => 'ورقة جرد عام', 'category' => 'جرد'],

            // المبيعات
            ['id' => 7, 'name' => 'فاتورة مبيعات', 'category' => 'مبيعات'],
            ['id' => 9, 'name' => 'إشعار دائن مبيعات', 'category' => 'مبيعات'],

            // مرتجع المبيعات
            ['id' => 8, 'name' => 'مرتجع مبيعات', 'category' => 'مرتجع مبيعات'],

            // المشتريات
            ['id' => 10, 'name' => 'فاتورة مشتريات', 'category' => 'مشتريات'],
            ['id' => 12, 'name' => 'إشعار مدين مشتريات', 'category' => 'مشتريات'],

            // مرتجع المشتريات
            ['id' => 11, 'name' => 'مرتجع مشتريات', 'category' => 'مرتجع مشتريات'],

            // التحويلات
            ['id' => 13, 'name' => 'تحويل يدوي بين المخازن', 'category' => 'تحويل'],
            ['id' => 14, 'name' => 'تحويل صادر من نقطة البيع', 'category' => 'تحويل'],
            ['id' => 15, 'name' => 'تحويل وارد لنقطة البيع', 'category' => 'تحويل'],

            // إضافات مقترحة
            ['id' => 20, 'name' => 'طلب شراء', 'category' => 'مشتريات'],
            ['id' => 21, 'name' => 'عرض سعر للعميل', 'category' => 'مبيعات'],
            ['id' => 22, 'name' => 'أمر بيع', 'category' => 'مبيعات'],
            ['id' => 23, 'name' => 'أمر شراء', 'category' => 'مشتريات'],
            ['id' => 24, 'name' => 'إتلاف مخزون', 'category' => 'صرف'],
            ['id' => 25, 'name' => 'تسوية مخزنية', 'category' => 'جرد'],
            ['id' => 26, 'name' => 'رصيد افتتاحي للمخزون', 'category' => 'رصيد افتتاحي'],
            ['id' => 27, 'name' => 'تبديل تالف', 'category' => 'تبديل بضاعة'], // صادر
            ['id' => 28, 'name' => 'ارجاع تبديل تالف', 'category' => 'تبديل بضاعة'], // وارد
        ];

        // استخدام upsert لإضافة أو تحديث السجلات
        DB::table('permission_sources')->upsert($sources, ['id'], ['name', 'category']);
    }
}