<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يُكمل ما ينقص من جداول الحضور وأعمدتها في اختبارات sqlite.
 *
 * هجراتُ المشروع تحوي صيغاً خاصّةً بـMySQL (`ALTER … MODIFY … ENUM`،
 * و`SchemaGuard`) لا يفهمها sqlite الذي يفرضه `phpunit.xml`، فكلُّ اختبارِ
 * حضورٍ يبني جداولَه بيده. وكان لكلٍّ منها نسختُه — فإضافةُ عمودٍ واحدٍ
 * إلى مسار التسجيل تعني تعديلَ ثلاثة ملفاتِ اختبارٍ يدوياً، ونسيانَ
 * الرابع.
 *
 * ما هنا **إكمالٌ لا إنشاء**: كلُّ شيءٍ محروسٌ بـ`hasTable`/`hasColumn`،
 * فما بناه الاختبارُ بنفسه يبقى كما بناه.
 */
trait BuildsAttendanceStampSchema
{
    protected function createAttendanceStampSchema(): void
    {
        $this->createBaseTables();
        $this->completeEmployeesTable();
        $this->completeAttendanceDaysTable();
        $this->createStampTables();
        $this->createCascadeTables();
    }

    /**
     * طلبٌ مقترن بجهاز فرع — شاشةُ المسح ترفض بلا اقتران.
     */
    protected function makePairedKioskRequest(string $uri, string $method = 'POST', array $data = []): \Illuminate\Http\Request
    {
        $kiosk = \App\Models\AttendanceKiosk::query()->first();

        if (! $kiosk) {
            $kiosk = \App\Models\AttendanceKiosk::create([
                'name' => 'جهاز اختبار',
                'is_active' => true,
            ]);
        }

        $request = \Illuminate\Http\Request::create($uri, $method, $data);
        $session = app('session.store');
        // اقترانُ الشاشة: معرّفُ الجهاز + مفتاحُ الشاشة الذي تحمل بصمتَه
        $token = 'test-device-token';
        $kiosk->forceFill(['device_token_hash' => hash('sha256', $token)])->save();
        $session->put('attendance_kiosk', $kiosk->id.'|'.$token);
        $request->setLaravelSession($session);

        return $request;
    }

    /**
     * يُحاكي عمودَ `DATE` في MySQL.
     *
     * صبُّ `date` في إلوكوِنت يكتب بصيغة قواعد الاتصال — وهي في sqlite
     * `Y-m-d H:i:s`. وعمودُ `DATE` في MySQL يُطبّع القيمة إلى `Y-m-d`، فلا
     * يقع الفرق هناك. لكنّ `AttendanceProcessorService::processEmployeeDay`
     * يطابق `attendance_date` **حرفياً** في `firstOrCreate`، فعلى sqlite
     * لا يجد الصفَّ الذي كتبه إلوكوِنت ويحسب اليومَ غياباً — فلا تُحتسب
     * دقائقُ إضافيٍّ ولا انصرافٍ مبكر.
     *
     * تُستدعى بعد كتابة صفوف اليوم بالموديل وقبل معالجتها. أثرٌ بيئيٌّ
     * بحت: لا يُحاكي عيباً في الإنتاج، بل يُزيل عيباً في المحاكاة.
     */
    protected function normalizeAttendanceDates(): void
    {
        if (! Schema::hasTable('attendance_days')) {
            return;
        }

        foreach (DB::table('attendance_days')->get(['id', 'attendance_date']) as $row) {
            DB::table('attendance_days')
                ->where('id', $row->id)
                ->update(['attendance_date' => substr((string) $row->attendance_date, 0, 10)]);
        }
    }

    /** الجداولُ الأساسية — لمن لم يبنِها الاختبارُ بنفسه. */
    private function createBaseTables(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('first_name')->nullable();
                $t->string('middle_name')->nullable();
                $t->string('nickname')->nullable();
                $t->string('email')->nullable();
                $t->tinyInteger('status')->default(1);
                $t->date('hire_date')->nullable();
                $t->date('termination_date')->nullable();
                $t->date('last_working_day')->nullable();
                $t->unsignedBigInteger('shift_id')->nullable();
                $t->string('employee_photo')->nullable();
                $t->softDeletes();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_days')) {
            Schema::create('attendance_days', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('employee_id');
                $t->date('attendance_date');
                $t->string('status')->default('present');
                $t->time('start_shift')->nullable();
                $t->time('end_shift')->nullable();
                $t->time('check_in_time')->nullable();
                $t->time('check_out_time')->nullable();
                $t->time('login_time')->nullable();
                $t->time('logout_time')->nullable();
                $t->unsignedTinyInteger('absence_type')->nullable();
                $t->integer('absence_balance')->nullable();
                $t->string('check_in_method')->default('manual');
                $t->string('check_out_method')->default('manual');
                $t->boolean('scanned_via_barcode')->default(false);
                $t->string('ip_address', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_settings')) {
            Schema::create('attendance_settings', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->integer('start_month')->nullable();
                $t->integer('start_day')->nullable();
                $t->integer('late_grace_minutes')->default(15);
                $t->integer('absent_after_minutes')->default(120);
                $t->boolean('auto_deduction_enabled')->default(false);
                $t->decimal('late_deduction_amount', 10, 2)->default(0);
                $t->decimal('absence_deduction_amount', 10, 2)->default(0);
                $t->string('deduction_calculation_type')->default('fixed');
                $t->decimal('work_hours_per_day', 5, 2)->default(8);
                $t->string('checkout_method')->default('hours');
                $t->boolean('photos_required')->default(false);
                $t->integer('photos_count_checkin')->default(0);
                $t->integer('photos_count_checkout')->default(0);
                $t->boolean('location_check_enabled')->default(false);
                $t->boolean('ip_check_enabled')->default(false);
                $t->integer('early_leave_threshold_minutes')->default(0);
                $t->integer('checkout_window_minutes')->nullable();
                $t->time('default_shift_start')->nullable();
                $t->text('default_weekly_off_days')->nullable();
                $t->boolean('allow_second_shift')->default(false);
                $t->boolean('allow_backdated_requests')->default(false);
                $t->boolean('direct_manager_approval')->default(false);
                $t->boolean('department_manager_approval')->default(false);
                $t->boolean('employees_approval')->default(false);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('employee_attendance_configs')) {
            Schema::create('employee_attendance_configs', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('employee_id');
                $t->integer('late_grace_minutes')->nullable();
                $t->integer('absent_after_minutes')->nullable();
                $t->time('expected_check_in')->nullable();
                $t->time('expected_check_out')->nullable();
                $t->text('weekly_off_days')->nullable();
                $t->string('checkout_method')->nullable();
                $t->boolean('uses_route_tracking')->nullable();
                $t->boolean('photos_required')->nullable();
                $t->decimal('work_hours_per_day', 5, 2)->nullable();
                $t->integer('checkout_window_minutes')->nullable();
                $t->boolean('auto_deduction_enabled')->nullable();
                $t->decimal('late_deduction_amount', 10, 2)->nullable();
                $t->decimal('absence_deduction_amount', 10, 2)->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('employee_attendance_configs')) {
            if (! Schema::hasColumn('employee_attendance_configs', 'auto_deduction_enabled')) {
                Schema::table('employee_attendance_configs', function (Blueprint $t) {
                    $t->boolean('auto_deduction_enabled')->nullable();
                });
            }
            if (! Schema::hasColumn('employee_attendance_configs', 'late_deduction_amount')) {
                Schema::table('employee_attendance_configs', function (Blueprint $t) {
                    $t->decimal('late_deduction_amount', 10, 2)->nullable();
                });
            }
            if (! Schema::hasColumn('employee_attendance_configs', 'absence_deduction_amount')) {
                Schema::table('employee_attendance_configs', function (Blueprint $t) {
                    $t->decimal('absence_deduction_amount', 10, 2)->nullable();
                });
            }
        }

        if (Schema::hasTable('attendance_settings')) {
            Schema::table('attendance_settings', function (Blueprint $t) {
                foreach ([
                    'allow_second_shift', 'allow_backdated_requests',
                    'direct_manager_approval', 'department_manager_approval', 'employees_approval',
                ] as $column) {
                    if (! Schema::hasColumn('attendance_settings', $column)) {
                        $t->boolean($column)->default(false);
                    }
                }
            });
        }

        if (Schema::hasTable('holidays') && ! Schema::hasColumn('holidays', 'holiday_list_id')) {
            Schema::table('holidays', function (Blueprint $t) {
                $t->unsignedBigInteger('holiday_list_id')->nullable();
            });
        }

        if (! Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('shift_days')) {
            Schema::create('shift_days', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('shift_id');
                $t->string('day')->nullable();
                $t->boolean('working_day')->default(true);
                $t->time('start_time')->nullable();
                $t->time('end_time')->nullable();
                $t->time('login_start_time')->nullable();
                $t->time('login_end_time')->nullable();
                $t->time('logout_start_time')->nullable();
                $t->time('logout_end_time')->nullable();
                $t->integer('grace_period')->nullable();
                $t->integer('delay_calculation')->nullable();
                $t->timestamps();
            });
        }
    }

    private function completeEmployeesTable(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $t) {
            if (! Schema::hasColumn('employees', 'barcode')) {
                $t->string('barcode', 64)->nullable();
            }
            if (! Schema::hasColumn('employees', 'barcode_enabled')) {
                $t->boolean('barcode_enabled')->default(true);
            }
            if (! Schema::hasColumn('employees', 'attendance_channel')) {
                $t->string('attendance_channel', 16)->default('branch');
            }
            if (! Schema::hasColumn('employees', 'branch_id')) {
                $t->unsignedBigInteger('branch_id')->nullable();
            }
            if (! Schema::hasColumn('employees', 'department_id')) {
                $t->unsignedBigInteger('department_id')->nullable();
            }
            // الهيكلُ التنظيمي: منه تُشتقّ لوحةُ متابعة الفريق
            if (! Schema::hasColumn('employees', 'direct_manager_id')) {
                $t->unsignedBigInteger('direct_manager_id')->nullable();
            }
            if (! Schema::hasColumn('employees', 'job_title_id')) {
                $t->unsignedBigInteger('job_title_id')->nullable();
            }
        });
    }

    private function completeAttendanceDaysTable(): void
    {
        if (! Schema::hasTable('attendance_days')) {
            return;
        }

        Schema::table('attendance_days', function (Blueprint $t) {
            $integers = [
                'late_minutes', 'early_leave_minutes', 'overtime_minutes',
                'check_in_distance_meters', 'check_out_distance_meters',
            ];

            foreach ($integers as $column) {
                if (! Schema::hasColumn('attendance_days', $column)) {
                    $t->integer($column)->nullable()->default(0);
                }
            }

            foreach (['expected_check_in', 'expected_check_out'] as $column) {
                if (! Schema::hasColumn('attendance_days', $column)) {
                    $t->time($column)->nullable();
                }
            }

            foreach (['is_holiday', 'is_weekly_off', 'is_on_leave', 'auto_generated', 'check_out_capped', 'manual_override'] as $column) {
                if (! Schema::hasColumn('attendance_days', $column)) {
                    $t->boolean($column)->default(false);
                }
            }

            $nullableIds = [
                'deduction_id', 'leave_request_id', 'attendance_sheets_id',
                'check_in_kiosk_id', 'check_out_kiosk_id',
                'check_in_geofence_id', 'check_out_geofence_id',
                'check_in_punch_id', 'check_out_punch_id',
            ];

            foreach ($nullableIds as $column) {
                if (! Schema::hasColumn('attendance_days', $column)) {
                    $t->unsignedBigInteger($column)->nullable();
                }
            }

            foreach (['check_in_channel', 'check_out_channel'] as $column) {
                if (! Schema::hasColumn('attendance_days', $column)) {
                    $t->string($column, 16)->nullable();
                }
            }

            foreach ([
                'checkin_latitude', 'checkin_longitude',
                'checkout_latitude', 'checkout_longitude',
            ] as $column) {
                if (! Schema::hasColumn('attendance_days', $column)) {
                    $t->decimal($column, 10, 7)->nullable();
                }
            }

            if (! Schema::hasColumn('attendance_days', 'processed_at')) {
                $t->timestamp('processed_at')->nullable();
            }
            if (! Schema::hasColumn('attendance_days', 'notes')) {
                $t->text('notes')->nullable();
            }
        });
    }

    private function createStampTables(): void
    {
        if (! Schema::hasTable('attendance_punches')) {
            Schema::create('attendance_punches', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('employee_id')->nullable();
                $t->unsignedBigInteger('attendance_day_id')->nullable();
                $t->string('punch_type', 8);
                $t->dateTime('punched_at');
                $t->time('applied_time')->nullable();
                $t->string('adjustment_code', 32)->nullable();
                $t->string('entry_note', 255)->nullable();
                $t->date('attendance_date');
                $t->string('channel', 16);
                $t->string('method', 16);
                $t->unsignedBigInteger('kiosk_id')->nullable();
                $t->unsignedBigInteger('geofence_id')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->integer('accuracy_meters')->nullable();
                $t->integer('distance_meters')->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->string('wifi_ssid', 64)->nullable();
                $t->text('user_agent')->nullable();
                $t->string('raw_barcode', 64)->nullable();
                $t->time('expected_check_in')->nullable();
                $t->time('expected_check_out')->nullable();
                $t->integer('late_minutes')->default(0);
                $t->integer('early_leave_minutes')->default(0);
                $t->string('result', 16);
                $t->string('stage', 32)->nullable();
                $t->string('rejection_code', 64)->nullable();
                $t->string('rejection_reason', 255)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_geofences')) {
            Schema::create('attendance_geofences', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name');
                $t->unsignedBigInteger('branch_id')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->integer('radius_meters')->default(100);
                $t->integer('max_accuracy_meters')->default(100);
                $t->text('allowed_ip_ranges')->nullable();
                $t->text('allowed_wifi_ssids')->nullable();
                $t->boolean('require_geo')->default(true);
                $t->boolean('require_network')->default(false);
                $t->boolean('is_active')->default(true);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_kiosks')) {
            Schema::create('attendance_kiosks', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name');
                $t->unsignedBigInteger('branch_id')->nullable();
                $t->unsignedBigInteger('geofence_id')->nullable();
                $t->string('pairing_code', 16)->unique();
                $t->boolean('is_active')->default(true);
                $t->timestamp('last_seen_at')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }
    }

    /** الجداولُ التي تستعلم عنها حلقاتُ التسلسل ولو لم يُعنَ بها الاختبار. */
    private function createCascadeTables(): void
    {
        if (! Schema::hasTable('leave_permissions')) {
            Schema::create('leave_permissions', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('employee_id');
                $t->date('start_date')->nullable();
                $t->date('end_date')->nullable();
                $t->unsignedTinyInteger('type')->nullable();
                $t->unsignedTinyInteger('leave_type')->nullable();
                $t->date('submission_date')->nullable();
                $t->text('notes')->nullable();
                $t->text('attachments')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('holiday_list_id')->nullable();
                $t->string('named')->nullable();
                $t->date('holiday_date')->nullable();
                $t->string('recurrence_type')->default('once');
                $t->unsignedTinyInteger('recurrence_day_of_week')->nullable();
                $t->unsignedTinyInteger('recurrence_day_of_month')->nullable();
                $t->unsignedTinyInteger('recurrence_month')->nullable();
                $t->boolean('is_active')->default(true);
                $t->boolean('is_paid')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('holiday_lists')) {
            Schema::create('holiday_lists', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('holy_day_list_customizes')) {
            Schema::create('holy_day_list_customizes', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedTinyInteger('use_rules')->default(2);
                $t->unsignedBigInteger('holiday_list_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable();
                $t->unsignedBigInteger('department_id')->nullable();
                $t->unsignedBigInteger('job_title_id')->nullable();
                $t->unsignedBigInteger('attendance_determinant_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('holy_day_list_customize_employees')) {
            Schema::create('holy_day_list_customize_employees', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('holyday_customizes_id');
                $t->unsignedBigInteger('employee_id');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_rules')) {
            Schema::create('attendance_rules', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name')->nullable();
                $t->string('color')->nullable();
                $t->string('status')->default('active');
                $t->unsignedBigInteger('shift_id')->nullable();
                $t->text('description')->nullable();
                $t->text('formula')->nullable();
                $t->text('condition')->nullable();
                $t->timestamps();
            });
        }

        /*
            الوردياتُ المخصَّصة: `resolveShiftId()` يسأل عنها قبل الوردِيّة
            الدائمة في كلِّ قياسِ يوم.
        */
        if (! Schema::hasTable('custom_shifts')) {
            Schema::create('custom_shifts', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name')->nullable();
                $t->date('from_date')->nullable();
                $t->date('to_date')->nullable();
                $t->unsignedBigInteger('shift_id')->nullable();
                $t->tinyInteger('use_rules')->default(1);
                $t->boolean('is_main')->default(0);
                $t->unsignedBigInteger('branch_id')->nullable();
                $t->unsignedBigInteger('department_id')->nullable();
                $t->unsignedBigInteger('job_title_id')->nullable();
                $t->unsignedBigInteger('shift_rule_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('custom_shifts_employees')) {
            Schema::create('custom_shifts_employees', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('custom_shifts_id');
                $t->unsignedBigInteger('employee_id');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('employee_id');
                $t->date('start_date')->nullable();
                $t->date('end_date')->nullable();
                $t->string('status')->default('pending');
                $t->timestamps();
            });
        }

        // محدِّداتُ الحضور القديمة: `GeofenceGuard` يستشيرها كطبقةٍ ثانية،
        // فيقرأ إسنادَها من `holy_day_list_customizes` في كل ختم.
        if (! Schema::hasTable('attendance_determinants')) {
            Schema::create('attendance_determinants', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name')->nullable();
                $t->tinyInteger('status')->default(0);
                $t->boolean('enable_ip_verification')->default(false);
                $t->tinyInteger('ip_investigation')->default(2);
                $t->text('allowed_ips')->nullable();
                $t->boolean('enable_location_verification')->default(false);
                $t->tinyInteger('location_investigation')->default(2);
                $t->decimal('latitude', 10, 8)->nullable();
                $t->decimal('longitude', 11, 8)->nullable();
                $t->integer('radius')->nullable();
                $t->tinyInteger('radius_type')->default(1);
                $t->boolean('capture_employee_image')->default(false);
                $t->tinyInteger('image_investigation')->default(2);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('holy_day_list_customizes')) {
            Schema::create('holy_day_list_customizes', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('attendance_determinant_id')->nullable();
                $t->tinyInteger('use_rules')->default(1);
                $t->unsignedBigInteger('branch_id')->nullable();
                $t->unsignedBigInteger('department_id')->nullable();
                $t->unsignedBigInteger('job_title_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('holy_day_list_customize_employees')) {
            Schema::create('holy_day_list_customize_employees', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('holy_day_list_customize_id');
                $t->unsignedBigInteger('employee_id');
            });
        }

        // مسارُ الرواتب: الخصميةُ التي يُولّدها الحضور، والقسيمةُ التي
        // يُقرأ منها الأجرُ الأساسي عند احتساب خصمية اليوم.
        if (! Schema::hasTable('deductions')) {
            Schema::create('deductions', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('employee_id');
                $t->string('deduction_type')->nullable();
                $t->decimal('amount', 15, 2)->default(0);
                $t->decimal('settled_amount', 15, 2)->default(0);
                $t->string('status')->default('pending');
                $t->date('date')->nullable();
                $t->string('reason')->nullable();
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('created_by_user')->nullable();
                $t->unsignedBigInteger('approved_by_user')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->timestamp('settled_at')->nullable();
                $t->string('code')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('deduction_histories')) {
            Schema::create('deduction_histories', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('deduction_id');
                $t->string('action')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->decimal('amount', 15, 2)->default(0);
                $t->string('description')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // الاعتمادُ المسبق للعمل الإضافي
        if (! Schema::hasTable('overtime_requests')) {
            Schema::create('overtime_requests', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('employee_id');
                $t->date('overtime_date');
                $t->integer('requested_minutes')->default(0);
                $t->integer('approved_minutes')->nullable();
                $t->string('status')->default('pending');
                $t->text('reason')->nullable();
                $t->unsignedBigInteger('requested_by')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->string('rejection_reason')->nullable();
                $t->text('admin_notes')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('overtime_rules')) {
            Schema::create('overtime_rules', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('code')->nullable();
                $t->string('name')->nullable();
                $t->string('applies_on')->nullable();
                $t->decimal('multiplier', 5, 2)->default(1.5);
                $t->decimal('hourly_rate', 15, 2)->nullable();
                $t->integer('max_minutes_per_day')->nullable();
                $t->boolean('is_system')->default(false);
                $t->tinyInteger('status')->default(1);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_settings')) {
            Schema::create('payroll_settings', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->boolean('overtime_enabled')->default(false);
                $t->boolean('overtime_requires_approval')->default(false);
                $t->decimal('overtime_monthly_hours', 8, 2)->default(240);
                $t->decimal('overtime_hourly_rate', 15, 2)->nullable();
                $t->integer('overtime_max_minutes_per_period')->nullable();
                $t->unsignedBigInteger('overtime_component_type_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('salary_items')) {
            Schema::create('salary_items', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name')->nullable();
                $t->string('type')->nullable();
                $t->unsignedBigInteger('component_type_id')->nullable();
                $t->string('origin')->nullable();
                $t->string('status')->nullable();
                $t->text('description')->nullable();
                $t->string('salary_item_value')->nullable();
                $t->decimal('amount', 15, 2)->default(0);
                $t->unsignedBigInteger('account_id')->nullable();
                $t->unsignedBigInteger('salary_slips_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('salary_slips')) {
            Schema::create('salary_slips', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('employee_id');
                $t->unsignedBigInteger('payroll_id')->nullable();
                $t->decimal('basic_amount', 15, 2)->default(0);
                $t->date('start_date')->nullable();
                $t->date('end_date')->nullable();
                $t->string('status')->default('active');
                $t->string('frequency', 20)->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('code', 64)->nullable();
                $t->unsignedBigInteger('salary_slip_id')->nullable();
                $t->unsignedBigInteger('employee_id');
                $t->date('period_from');
                $t->date('period_to');
                $t->date('scheduled_date')->nullable();
                $t->decimal('basic_amount', 15, 2)->default(0);
                $t->string('status', 20)->default('pending');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_run_items')) {
            Schema::create('payroll_run_items', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('payroll_run_id');
                $t->string('item_type', 32);
                $t->string('source_table')->nullable();
                $t->unsignedBigInteger('source_id')->nullable();
                $t->string('name')->nullable();
                $t->decimal('amount', 15, 2)->default(0);
                $t->text('description')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_photo_types')) {
            Schema::create('attendance_photo_types', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name');
                $t->string('phase')->default('both');
                $t->boolean('is_required')->default(false);
                $t->boolean('is_active')->default(true);
                $t->integer('sort_order')->default(0);
                $t->string('icon')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_photos')) {
            Schema::create('attendance_photos', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('attendance_day_id');
                $t->unsignedBigInteger('photo_type_id')->nullable();
                $t->string('phase')->default('checkin');
                $t->string('path')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->timestamps();
            });
        }
    }
}
