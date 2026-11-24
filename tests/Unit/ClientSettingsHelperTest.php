<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\ClientSettingsHelper;
use App\Models\GeneralClientSetting;

class ClientSettingsHelperTest extends TestCase
{
    public function test_location_required_setting()
    {
        $setting = GeneralClientSetting::where('key', 'location_group_settings')->first();

        if ($setting) {
            $this->assertIsBool($setting->location_required);
        }
    }

    public function test_group_required_setting()
    {
        $setting = GeneralClientSetting::where('key', 'location_group_settings')->first();

        if ($setting) {
            $this->assertIsBool($setting->group_required);
        }
    }

    public function test_employee_can_edit_visit_status()
    {
        $setting = GeneralClientSetting::where('key', 'employee_visit_permissions')->first();

        if ($setting) {
            $this->assertIsBool($setting->employee_can_edit_visit_status);
        }
    }

    public function test_employee_can_add_visit_notes()
    {
        $setting = GeneralClientSetting::where('key', 'employee_visit_permissions')->first();

        if ($setting) {
            $this->assertIsBool($setting->employee_can_add_visit_notes);
        }
    }

    public function test_route_tracking_enabled()
    {
        $setting = GeneralClientSetting::where('key', 'route_tracking_settings')->first();

        if ($setting) {
            $this->assertIsBool($setting->enable_route_tracking);
        }
    }

    public function test_helper_location_required()
    {
        $result = ClientSettingsHelper::isLocationRequired();
        $this->assertIsBool($result);
    }

    public function test_helper_group_required()
    {
        $result = ClientSettingsHelper::isGroupRequired();
        $this->assertIsBool($result);
    }

    public function test_helper_employee_permissions()
    {
        $canEdit = ClientSettingsHelper::canEmployeeEditVisitStatus();
        $canAddNotes = ClientSettingsHelper::canEmployeeAddVisitNotes();

        $this->assertIsBool($canEdit);
        $this->assertIsBool($canAddNotes);
    }

    public function test_helper_route_tracking()
    {
        $enabled = ClientSettingsHelper::isRouteTrackingEnabled();
        $this->assertIsBool($enabled);
    }

    public function test_helper_all_settings()
    {
        $settings = ClientSettingsHelper::getAllSettings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('location_group', $settings);
        $this->assertArrayHasKey('employee_permissions', $settings);
        $this->assertArrayHasKey('route_tracking', $settings);
    }
}
