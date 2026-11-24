<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\TaskSettingsHelper;
use App\Models\TaskSettings;
use App\Models\AccountSetting;

class EnsureProjectSettings
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get the active project ID for the authenticated user
        $activeProjectId = auth()->check() ? auth()->user()->active_project_id : null;
        
        // If we have an active project, load and apply its settings
        if ($activeProjectId) {
            // Apply settings to session for use across modules
            TaskSettingsHelper::applySettingsToModules($activeProjectId);
        }

        // تعميم اللغة الافتراضية المخزنة في إعدادات الحساب على النظام كامل تلقائياً
        if (auth()->check()) {
            $account_setting = AccountSetting::where('user_id', auth()->id())->first();
            if ($account_setting && $account_setting->language) {
                if (!session()->has('locale') || session('locale') !== $account_setting->language) {
                    session(['locale' => $account_setting->language]);
                    app()->setLocale($account_setting->language);
                }
            }
        }
        
        return $next($request);
    }
}