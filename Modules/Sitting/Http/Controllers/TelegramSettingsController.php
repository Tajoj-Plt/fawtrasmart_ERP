<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TelegramSetting;
use App\Services\TelegramNotificationService;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * إعدادات بوت Telegram — توكن، قناة واحدة، تفعيل التقرير اليومي ومحتواه.
 * صفٌّ واحد للنظام كلّه، لا لكل مستخدم — خلافاً لـ`SMPTController` الذي
 * يتبعه هذا في البنية.
 *
 * @see TelegramNotificationService المستهلك الوحيد للإعداد
 */
class TelegramSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index()
    {
        return view('sitting::telegram.index', [
            'settings' => TelegramSetting::current(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bot_token' => 'nullable|string|max:255',
            'chat_id' => 'nullable|string|max:255',
            'sales_chat_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        /* رمزُ البوت لا يُعاد إلى الشاشة بعد حفظه، فحقلُه يصل فارغاً في كل حفظٍ
           لا يُراد به تغييرُه — والفارغُ يعني «أبقِ المحفوظ» لا «امسحه». */
        if (blank($data['bot_token'] ?? null)) {
            unset($data['bot_token']);
        }

        foreach ([
            'daily_report_enabled',
            'report_show_sales', 'report_show_collections', 'report_show_returns',
            'report_show_expenses', 'report_show_stock',
            ...TelegramSetting::SALES_FLAGS,
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        try {
            $settings = TelegramSetting::current();
            $settings->update($data);

            OperationLog::record('updated', 'Sitting.Telegram', $settings, __('settings.telegram.saved'));

            return redirect()->back()->with('success', __('settings.telegram.saved'));
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Sitting.Telegram', 'store');

            return redirect()->back()->with('error', __('settings.telegram.save_error', ['error' => $e->getMessage()]));
        }
    }
}
