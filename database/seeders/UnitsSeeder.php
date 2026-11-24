<?php

namespace Database\Seeders;

use App\Models\TemplateUnit;
use App\Models\SubUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * تشغيل: php artisan db:seed --class=UnitsSeeder
     */
    public function run(): void
    {
        // تعطيل التحقق من المفاتيح الخارجية مؤقتاً
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // حذف البيانات القديمة (اختياري - علق هذا السطر إذا لم ترد الحذف)
        // SubUnit::truncate();
        // TemplateUnit::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $units = [
            // ═══════════════════════════════════════════════════════════════
            // 📦 وحدات الحجم والسعة (السوائل)
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'لتر',
                'discrimination' => 'وحدات السوائل والحجم',
                'template' => 'volume',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'لتر', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'ملي لتر', 'conversion_factor' => 0.001, 'sub_discrimination' => '1 لتر = 1000 ملي'],
                    ['larger_unit_name' => 'سنتي لتر', 'conversion_factor' => 0.01, 'sub_discrimination' => '1 لتر = 100 سنتي'],
                    ['larger_unit_name' => 'ديسي لتر', 'conversion_factor' => 0.1, 'sub_discrimination' => '1 لتر = 10 ديسي'],
                    ['larger_unit_name' => 'جالون', 'conversion_factor' => 3.78541, 'sub_discrimination' => '1 جالون = 3.785 لتر'],
                    ['larger_unit_name' => 'برميل', 'conversion_factor' => 200, 'sub_discrimination' => '1 برميل = 200 لتر'],
                    ['larger_unit_name' => 'متر مكعب', 'conversion_factor' => 1000, 'sub_discrimination' => '1 م³ = 1000 لتر'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // ⚖️ وحدات الوزن والكتلة
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'كيلو جرام',
                'discrimination' => 'وحدات الوزن والكتلة',
                'template' => 'weight',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'كيلو جرام', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'جرام', 'conversion_factor' => 0.001, 'sub_discrimination' => '1 كجم = 1000 جرام'],
                    ['larger_unit_name' => 'ملي جرام', 'conversion_factor' => 0.000001, 'sub_discrimination' => '1 كجم = 1,000,000 ملي جرام'],
                    ['larger_unit_name' => 'طن', 'conversion_factor' => 1000, 'sub_discrimination' => '1 طن = 1000 كجم'],
                    ['larger_unit_name' => 'رطل (باوند)', 'conversion_factor' => 0.453592, 'sub_discrimination' => '1 رطل = 0.4536 كجم'],
                    ['larger_unit_name' => 'أونصة', 'conversion_factor' => 0.0283495, 'sub_discrimination' => '1 أونصة = 28.35 جرام'],
                    ['larger_unit_name' => 'قنطار', 'conversion_factor' => 100, 'sub_discrimination' => '1 قنطار = 100 كجم'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // 📏 وحدات الطول
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'متر',
                'discrimination' => 'وحدات الطول',
                'template' => 'length',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'متر', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'سنتيمتر', 'conversion_factor' => 0.01, 'sub_discrimination' => '1 متر = 100 سم'],
                    ['larger_unit_name' => 'ملليمتر', 'conversion_factor' => 0.001, 'sub_discrimination' => '1 متر = 1000 مم'],
                    ['larger_unit_name' => 'كيلومتر', 'conversion_factor' => 1000, 'sub_discrimination' => '1 كم = 1000 متر'],
                    ['larger_unit_name' => 'إنش (بوصة)', 'conversion_factor' => 0.0254, 'sub_discrimination' => '1 إنش = 2.54 سم'],
                    ['larger_unit_name' => 'قدم', 'conversion_factor' => 0.3048, 'sub_discrimination' => '1 قدم = 30.48 سم'],
                    ['larger_unit_name' => 'ياردة', 'conversion_factor' => 0.9144, 'sub_discrimination' => '1 ياردة = 91.44 سم'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // 📐 وحدات المساحة
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'متر مربع',
                'discrimination' => 'وحدات المساحة',
                'template' => 'area',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'متر مربع', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'سنتيمتر مربع', 'conversion_factor' => 0.0001, 'sub_discrimination' => '1 م² = 10,000 سم²'],
                    ['larger_unit_name' => 'كيلومتر مربع', 'conversion_factor' => 1000000, 'sub_discrimination' => '1 كم² = 1,000,000 م²'],
                    ['larger_unit_name' => 'هكتار', 'conversion_factor' => 10000, 'sub_discrimination' => '1 هكتار = 10,000 م²'],
                    ['larger_unit_name' => 'فدان', 'conversion_factor' => 4200, 'sub_discrimination' => '1 فدان ≈ 4200 م²'],
                    ['larger_unit_name' => 'قدم مربع', 'conversion_factor' => 0.092903, 'sub_discrimination' => '1 قدم² = 0.093 م²'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // 🔢 وحدات العد (القطع)
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'حبة',
                'discrimination' => 'وحدات العد والقطع',
                'template' => 'piece',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'حبة', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'قطعة', 'conversion_factor' => 1, 'sub_discrimination' => 'قطعة واحدة'],
                    ['larger_unit_name' => 'زوج', 'conversion_factor' => 2, 'sub_discrimination' => '1 زوج = 2 حبة'],
                    ['larger_unit_name' => 'درزن', 'conversion_factor' => 12, 'sub_discrimination' => '1 درزن = 12 حبة'],
                    ['larger_unit_name' => 'نصف درزن', 'conversion_factor' => 6, 'sub_discrimination' => '1 نصف درزن = 6 حبة'],
                    ['larger_unit_name' => 'ربع درزن', 'conversion_factor' => 3, 'sub_discrimination' => '1 ربع درزن = 3 حبة'],
                    ['larger_unit_name' => 'جروس', 'conversion_factor' => 144, 'sub_discrimination' => '1 جروس = 144 حبة (12 درزن)'],
                    ['larger_unit_name' => 'مئة', 'conversion_factor' => 100, 'sub_discrimination' => '100 حبة'],
                    ['larger_unit_name' => 'ألف', 'conversion_factor' => 1000, 'sub_discrimination' => '1000 حبة'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // 📦 وحدات التعبئة والتغليف
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'علبة',
                'discrimination' => 'وحدات التعبئة والتغليف',
                'template' => 'package',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'علبة', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'كرتون', 'conversion_factor' => 12, 'sub_discrimination' => '1 كرتون = 12 علبة (تعديل حسب الحاجة)'],
                    ['larger_unit_name' => 'كرتون كبير', 'conversion_factor' => 24, 'sub_discrimination' => '1 كرتون كبير = 24 علبة'],
                    ['larger_unit_name' => 'صندوق', 'conversion_factor' => 6, 'sub_discrimination' => '1 صندوق = 6 علب'],
                    ['larger_unit_name' => 'باكت', 'conversion_factor' => 1, 'sub_discrimination' => 'باكت واحد'],
                    ['larger_unit_name' => 'شرينك', 'conversion_factor' => 6, 'sub_discrimination' => '1 شرينك = 6 علب'],
                    ['larger_unit_name' => 'بالتة', 'conversion_factor' => 100, 'sub_discrimination' => '1 بالتة = 100 علبة (تعديل حسب الحاجة)'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // 🧴 وحدات العطور والزيوت
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'ملي',
                'discrimination' => 'وحدات العطور والزيوت',
                'template' => 'perfume',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'ملي', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية (ملي لتر)'],
                    ['larger_unit_name' => 'لتر', 'conversion_factor' => 1000, 'sub_discrimination' => '1 لتر = 1000 ملي'],
                    ['larger_unit_name' => 'تولة', 'conversion_factor' => 12, 'sub_discrimination' => '1 تولة ≈ 12 ملي'],
                    ['larger_unit_name' => 'نصف تولة', 'conversion_factor' => 6, 'sub_discrimination' => '1 نصف تولة ≈ 6 ملي'],
                    ['larger_unit_name' => 'ربع تولة', 'conversion_factor' => 3, 'sub_discrimination' => '1 ربع تولة ≈ 3 ملي'],
                    ['larger_unit_name' => 'أونصة سائلة', 'conversion_factor' => 29.5735, 'sub_discrimination' => '1 أونصة ≈ 29.57 ملي'],
                    ['larger_unit_name' => 'كوب', 'conversion_factor' => 250, 'sub_discrimination' => '1 كوب ≈ 250 ملي'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // ⏱️ وحدات الوقت
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'ساعة',
                'discrimination' => 'وحدات الوقت',
                'template' => 'time',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'ساعة', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'دقيقة', 'conversion_factor' => 0.0166667, 'sub_discrimination' => '60 دقيقة = 1 ساعة'],
                    ['larger_unit_name' => 'يوم', 'conversion_factor' => 24, 'sub_discrimination' => '1 يوم = 24 ساعة'],
                    ['larger_unit_name' => 'أسبوع', 'conversion_factor' => 168, 'sub_discrimination' => '1 أسبوع = 168 ساعة'],
                    ['larger_unit_name' => 'شهر', 'conversion_factor' => 720, 'sub_discrimination' => '1 شهر ≈ 720 ساعة (30 يوم)'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // 🔋 وحدات الطاقة والكهرباء
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'كيلو واط',
                'discrimination' => 'وحدات الطاقة',
                'template' => 'energy',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'كيلو واط', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'واط', 'conversion_factor' => 0.001, 'sub_discrimination' => '1 كيلو واط = 1000 واط'],
                    ['larger_unit_name' => 'ميجا واط', 'conversion_factor' => 1000, 'sub_discrimination' => '1 ميجا واط = 1000 كيلو واط'],
                    ['larger_unit_name' => 'حصان', 'conversion_factor' => 0.7457, 'sub_discrimination' => '1 حصان ≈ 0.746 كيلو واط'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // 📊 وحدات البيانات الرقمية
            // ═══════════════════════════════════════════════════════════════
            [
                'base_unit_name' => 'جيجابايت',
                'discrimination' => 'وحدات البيانات الرقمية',
                'template' => 'data',
                'status' => 1,
                'sub_units' => [
                    ['larger_unit_name' => 'جيجابايت', 'conversion_factor' => 1, 'sub_discrimination' => 'الوحدة الأساسية'],
                    ['larger_unit_name' => 'ميجابايت', 'conversion_factor' => 0.000976563, 'sub_discrimination' => '1 جيجا = 1024 ميجا'],
                    ['larger_unit_name' => 'كيلوبايت', 'conversion_factor' => 0.00000095367, 'sub_discrimination' => '1 جيجا = 1,048,576 كيلو'],
                    ['larger_unit_name' => 'تيرابايت', 'conversion_factor' => 1024, 'sub_discrimination' => '1 تيرا = 1024 جيجا'],
                ],
            ],
        ];

        foreach ($units as $unitData) {
            // إنشاء القالب الرئيسي
            $templateUnit = TemplateUnit::updateOrCreate(
                ['base_unit_name' => $unitData['base_unit_name']],
                [
                    'discrimination' => $unitData['discrimination'],
                    'template' => $unitData['template'],
                    'status' => $unitData['status'],
                ]
            );

            // إنشاء الوحدات الفرعية
            foreach ($unitData['sub_units'] as $subUnit) {
                SubUnit::updateOrCreate(
                    [
                        'template_unit_id' => $templateUnit->id,
                        'larger_unit_name' => $subUnit['larger_unit_name'],
                    ],
                    [
                        'conversion_factor' => $subUnit['conversion_factor'],
                        'sub_discrimination' => $subUnit['sub_discrimination'],
                    ]
                );
            }

            $this->command->info("✅ تم إنشاء قالب: {$unitData['base_unit_name']} مع " . count($unitData['sub_units']) . " وحدة فرعية");
        }

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('✅ تم إنشاء جميع الوحدات بنجاح!');
        $this->command->info('📊 إجمالي القوالب: ' . count($units));
        $this->command->info('═══════════════════════════════════════════════════════════════');
    }
}
