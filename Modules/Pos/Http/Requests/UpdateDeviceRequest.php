<?php

namespace Modules\Pos\Http\Requests;

/**
 * التعديل يرث قواعدَ الإنشاء — لا نسخةً ثانية تتباعد عنها.
 */
class UpdateDeviceRequest extends StoreDeviceRequest
{
    public function rules(): array
    {
        return parent::rules();
    }
}
