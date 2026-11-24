<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Ecosystem\Http\Controllers\EcosystemController;
use Modules\Ecosystem\Http\Controllers\PointsSettingController;
use Modules\Ecosystem\Http\Controllers\PointsTransactionController;
use Modules\Ecosystem\Http\Controllers\PointsTierController;
use Modules\Ecosystem\Http\Controllers\TierUpgradeLogController;
use Modules\Ecosystem\Http\Controllers\PointsUsersController;
use Modules\Ecosystem\Http\Controllers\RewardController;
use Modules\Ecosystem\Http\Controllers\RewardRedemptionController;
use Modules\Ecosystem\Http\Controllers\AchievementController;
use Modules\Ecosystem\Http\Controllers\UserAchievementController;
use Modules\Ecosystem\Http\Controllers\ChallengeController;
use Modules\Ecosystem\Http\Controllers\ChallengeTeamController;
use Modules\Ecosystem\Http\Controllers\ReferralSettingController;
use Modules\Ecosystem\Http\Controllers\ReferralController;
use Modules\Ecosystem\Http\Controllers\PointsExpirySettingController;
use Modules\Ecosystem\Http\Controllers\PointsBatchController;
use Modules\Ecosystem\Http\Controllers\PointsNotificationTemplateController;
use Modules\Ecosystem\Http\Controllers\PointsNotificationLogController;
use Modules\Ecosystem\Http\Controllers\PointsReportController;
use Modules\Ecosystem\Http\Controllers\PointsGeneralSettingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch', 'auth'],
    ],
    function () {
        // Ecosystem Module Routes
        Route::prefix('ecosystem')->group(function () {
            // ==========================================
            // لوحة تحكم النظام البيئي (Dashboard)
            // ==========================================
            Route::get('/dashboard', [EcosystemController::class, 'index'])->name('ecosystem.dashboard');
    
            
            // ==========================================
            // إعدادات النقاط (Points Settings)
            // ==========================================
            Route::prefix('points')->name('ecosystem.points.')->group(function () {

                // ==========================================
                // الإعدادات العامة (General Settings) - متاح دائماً حتى لو النظام معطل
                // ==========================================
                Route::get('general-settings', [PointsGeneralSettingController::class, 'index'])->name('general-settings.index');
                Route::post('general-settings', [PointsGeneralSettingController::class, 'update'])->name('general-settings.update');
                Route::post('general-settings/toggle-system', [PointsGeneralSettingController::class, 'toggleSystem'])->name('general-settings.toggle-system');
                Route::post('general-settings/toggle-subsystem/{subsystem}', [PointsGeneralSettingController::class, 'toggleSubsystem'])->name('general-settings.toggle-subsystem');

                // ==========================================
                // التقارير (Reports) - متاح دائماً للمراجعة حتى لو النظام معطل
                // ==========================================
                Route::prefix('reports')->name('reports.')->group(function () {
                    Route::get('points', [PointsReportController::class, 'points'])->name('points');
                    Route::get('tiers', [PointsReportController::class, 'tiers'])->name('tiers');
                    Route::get('rewards', [PointsReportController::class, 'rewards'])->name('rewards');
                    Route::get('achievements', [PointsReportController::class, 'achievements'])->name('achievements');
                    Route::get('challenges', [PointsReportController::class, 'challenges'])->name('challenges');
                    Route::get('referrals', [PointsReportController::class, 'referrals'])->name('referrals');
                    Route::get('expiry', [PointsReportController::class, 'expiry'])->name('expiry');
                    Route::get('daily-summary', [PointsReportController::class, 'dailySummary'])->name('daily-summary');
                    Route::get('export/{type}', [PointsReportController::class, 'export'])->name('export');
                });

                // ==========================================
                // جميع الروابط التالية تتطلب تفعيل النظام الرئيسي
                // ==========================================
                Route::middleware(['points.system'])->group(function () {

                    // ==========================================
                    // إعدادات النقاط (Points Settings) - نظام أساسي
                    // ==========================================
                    Route::get('settings/export', [PointsSettingController::class, 'export'])->name('settings.export');
                    Route::post('settings/bulk-delete', [PointsSettingController::class, 'bulkDelete'])->name('settings.bulk-delete');
                    Route::post('settings/{setting}/toggle-status', [PointsSettingController::class, 'toggleStatus'])->name('settings.toggle-status');
                    Route::resource('settings', PointsSettingController::class)->names('settings');

                    // ==========================================
                    // معاملات النقاط (Points Transactions) - نظام أساسي
                    // ==========================================
                    Route::get('transactions/export', [PointsTransactionController::class, 'export'])->name('transactions.export');
                    Route::post('transactions/bulk-delete', [PointsTransactionController::class, 'bulkDelete'])->name('transactions.bulk-delete');
                    Route::post('transactions/{transaction}/toggle-status', [PointsTransactionController::class, 'toggleStatus'])->name('transactions.toggle-status');
                    Route::resource('transactions', PointsTransactionController::class)->names('transactions');

                    // ==========================================
                    // مستخدمي النقاط (Points Users) - نظام أساسي
                    // ==========================================
                    Route::get('users/employees', [PointsUsersController::class, 'employees'])->name('users.employees');
                    Route::get('users/employees/{id}', [PointsUsersController::class, 'showEmployee'])->name('users.employees.show');
                    Route::get('users/clients', [PointsUsersController::class, 'clients'])->name('users.clients');
                    Route::get('users/clients/{id}', [PointsUsersController::class, 'showClient'])->name('users.clients.show');

                    // ==========================================
                    // نظام المستويات (Tiers) - نظام فرعي
                    // ==========================================
                    Route::middleware(['points.subsystem:tiers'])->group(function () {
                        Route::get('tiers/export', [PointsTierController::class, 'export'])->name('tiers.export');
                        Route::post('tiers/bulk-delete', [PointsTierController::class, 'bulkDelete'])->name('tiers.bulk-delete');
                        Route::post('tiers/reorder', [PointsTierController::class, 'reorder'])->name('tiers.reorder');
                        Route::post('tiers/{tier}/toggle-status', [PointsTierController::class, 'toggleStatus'])->name('tiers.toggle-status');
                        Route::resource('tiers', PointsTierController::class)->names('tiers');

                        Route::get('upgrade-logs/export', [TierUpgradeLogController::class, 'export'])->name('upgrade-logs.export');
                        Route::post('upgrade-logs/bulk-delete', [TierUpgradeLogController::class, 'bulkDelete'])->name('upgrade-logs.bulk-delete');
                        Route::get('upgrade-logs', [TierUpgradeLogController::class, 'index'])->name('upgrade-logs.index');
                        Route::get('upgrade-logs/{log}', [TierUpgradeLogController::class, 'show'])->name('upgrade-logs.show');
                    });

                    // ==========================================
                    // نظام المكافآت (Rewards) - نظام فرعي
                    // ==========================================
                    Route::middleware(['points.subsystem:rewards'])->group(function () {
                        Route::get('rewards/export', [RewardController::class, 'export'])->name('rewards.export');
                        Route::post('rewards/bulk-delete', [RewardController::class, 'bulkDelete'])->name('rewards.bulk-delete');
                        Route::post('rewards/{reward}/toggle-status', [RewardController::class, 'toggleStatus'])->name('rewards.toggle-status');
                        Route::post('rewards/{reward}/toggle-featured', [RewardController::class, 'toggleFeatured'])->name('rewards.toggle-featured');
                        Route::resource('rewards', RewardController::class)->names('rewards');

                        Route::get('redemptions/export', [RewardRedemptionController::class, 'export'])->name('redemptions.export');
                        Route::post('redemptions/{redemption}/approve', [RewardRedemptionController::class, 'approve'])->name('redemptions.approve');
                        Route::post('redemptions/{redemption}/process', [RewardRedemptionController::class, 'process'])->name('redemptions.process');
                        Route::post('redemptions/{redemption}/deliver', [RewardRedemptionController::class, 'deliver'])->name('redemptions.deliver');
                        Route::post('redemptions/{redemption}/cancel', [RewardRedemptionController::class, 'cancel'])->name('redemptions.cancel');
                        Route::get('redemptions/verify-voucher', [RewardRedemptionController::class, 'verifyVoucherPage'])->name('redemptions.verify-voucher');
                        Route::post('redemptions/verify-voucher/check', [RewardRedemptionController::class, 'verifyVoucher'])->name('redemptions.verify-voucher.check');
                        Route::post('redemptions/use-voucher', [RewardRedemptionController::class, 'useVoucher'])->name('redemptions.use-voucher');
                        Route::get('redemptions/client-invoices/{clientId}', [RewardRedemptionController::class, 'getClientInvoices'])->name('redemptions.client-invoices');
                        Route::get('redemptions', [RewardRedemptionController::class, 'index'])->name('redemptions.index');
                        Route::get('redemptions/create', [RewardRedemptionController::class, 'create'])->name('redemptions.create');
                        Route::post('redemptions', [RewardRedemptionController::class, 'store'])->name('redemptions.store');
                        Route::get('redemptions/{redemption}', [RewardRedemptionController::class, 'show'])->name('redemptions.show');
                    });

                    // ==========================================
                    // نظام الإنجازات (Achievements) - نظام فرعي
                    // ==========================================
                    Route::middleware(['points.subsystem:achievements'])->group(function () {
                        Route::get('achievements/export', [AchievementController::class, 'export'])->name('achievements.export');
                        Route::post('achievements/bulk-delete', [AchievementController::class, 'bulkDelete'])->name('achievements.bulk-delete');
                        Route::post('achievements/{achievement}/toggle-status', [AchievementController::class, 'toggleStatus'])->name('achievements.toggle-status');
                        Route::resource('achievements', AchievementController::class)->names('achievements');

                        Route::get('user-achievements/export', [UserAchievementController::class, 'export'])->name('user-achievements.export');
                        Route::get('user-achievements/employee/{employeeId}', [UserAchievementController::class, 'employeeAchievements'])->name('user-achievements.employee');
                        Route::get('user-achievements/client/{clientId}', [UserAchievementController::class, 'clientAchievements'])->name('user-achievements.client');
                        Route::get('user-achievements', [UserAchievementController::class, 'index'])->name('user-achievements.index');
                        Route::get('user-achievements/{id}', [UserAchievementController::class, 'show'])->name('user-achievements.show');
                    });

                    // ==========================================
                    // نظام التحديات (Challenges) - نظام فرعي
                    // ==========================================
                    Route::middleware(['points.subsystem:challenges'])->group(function () {
                        Route::post('challenges/teams/{team}/add-member', [ChallengeTeamController::class, 'addMember'])->name('challenges.teams.add-member');
                        Route::delete('challenges/teams/{team}/remove-member/{participant}', [ChallengeTeamController::class, 'removeMember'])->name('challenges.teams.remove-member');
                        Route::get('challenges/teams/get-available-users', [ChallengeTeamController::class, 'getAvailableUsers'])->name('challenges.teams.get-available-users');
                        Route::resource('challenges/teams', ChallengeTeamController::class)->names('challenges.teams')->parameters(['teams' => 'team']);
                        Route::get('challenges/leaderboard-all', [ChallengeController::class, 'leaderboardAll'])->name('challenges.leaderboard.index');
                        Route::post('challenges/bulk-delete', [ChallengeController::class, 'bulkDelete'])->name('challenges.bulk-delete');
                        Route::post('challenges/{challenge}/toggle-status', [ChallengeController::class, 'toggleStatus'])->name('challenges.toggle-status');
                        Route::get('challenges/{challenge}/bulk-enroll', [ChallengeController::class, 'bulkEnrollForm'])->name('challenges.bulk-enroll-form');
                        Route::post('challenges/{challenge}/bulk-enroll', [ChallengeController::class, 'bulkEnroll'])->name('challenges.bulk-enroll');
                        Route::post('challenges/{challenge}/enroll', [ChallengeController::class, 'enroll'])->name('challenges.enroll');
                        Route::post('challenges/{challenge}/finalize', [ChallengeController::class, 'finalize'])->name('challenges.finalize');
                        Route::delete('challenges/{challenge}/participants/{participant}', [ChallengeController::class, 'removeParticipant'])->name('challenges.remove-participant');
                        Route::get('challenges/{challenge}/leaderboard', [ChallengeController::class, 'leaderboard'])->name('challenges.leaderboard');
                        Route::get('challenges/get-users', [ChallengeController::class, 'getUsers'])->name('challenges.get-users');
                        Route::resource('challenges', ChallengeController::class)->names('challenges');
                    });

                    // ==========================================
                    // نظام الإحالات (Referrals) - نظام فرعي
                    // ==========================================
                    Route::middleware(['points.subsystem:referrals'])->group(function () {
                        Route::get('referral-settings/export', [ReferralSettingController::class, 'export'])->name('referral-settings.export');
                        Route::post('referral-settings/bulk-delete', [ReferralSettingController::class, 'bulkDelete'])->name('referral-settings.bulk-delete');
                        Route::post('referral-settings/{referral_setting}/toggle-status', [ReferralSettingController::class, 'toggleStatus'])->name('referral-settings.toggle-status');
                        Route::resource('referral-settings', ReferralSettingController::class)->names('referral-settings');

                        Route::get('referrals/export', [ReferralController::class, 'export'])->name('referrals.export');
                        Route::post('referrals/bulk-delete', [ReferralController::class, 'bulkDelete'])->name('referrals.bulk-delete');
                        Route::post('referrals/{referral}/confirm', [ReferralController::class, 'confirm'])->name('referrals.confirm');
                        Route::post('referrals/{referral}/cancel', [ReferralController::class, 'cancel'])->name('referrals.cancel');
                        Route::get('referrals', [ReferralController::class, 'index'])->name('referrals.index');
                        Route::get('referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show');
                    });

                    // ==========================================
                    // نظام انتهاء الصلاحية (Expiry) - نظام فرعي
                    // ==========================================
                    Route::middleware(['points.subsystem:expiry'])->group(function () {
                        Route::get('expiry-settings/export', [PointsExpirySettingController::class, 'export'])->name('expiry-settings.export');
                        Route::post('expiry-settings/bulk-delete', [PointsExpirySettingController::class, 'bulkDelete'])->name('expiry-settings.bulk-delete');
                        Route::post('expiry-settings/{expiry_setting}/toggle-status', [PointsExpirySettingController::class, 'toggleStatus'])->name('expiry-settings.toggle-status');
                        Route::resource('expiry-settings', PointsExpirySettingController::class)->names('expiry-settings')->parameters(['expiry-settings' => 'expiry_setting']);

                        Route::get('batches/export', [PointsBatchController::class, 'export'])->name('batches.export');
                        Route::get('batches', [PointsBatchController::class, 'index'])->name('batches.index');
                        Route::get('batches/{batch}', [PointsBatchController::class, 'show'])->name('batches.show');
                    });

                    // ==========================================
                    // نظام الإشعارات (Notifications) - نظام فرعي
                    // ==========================================
                    Route::middleware(['points.subsystem:notifications'])->group(function () {
                        Route::get('notification-templates/export', [PointsNotificationTemplateController::class, 'export'])->name('notification-templates.export');
                        Route::post('notification-templates/bulk-delete', [PointsNotificationTemplateController::class, 'bulkDelete'])->name('notification-templates.bulk-delete');
                        Route::post('notification-templates/{notification_template}/toggle-status', [PointsNotificationTemplateController::class, 'toggleStatus'])->name('notification-templates.toggle-status');
                        Route::resource('notification-templates', PointsNotificationTemplateController::class)->names('notification-templates')->parameters(['notification-templates' => 'notification_template']);

                        Route::get('notification-logs/export', [PointsNotificationLogController::class, 'export'])->name('notification-logs.export');
                        Route::post('notification-logs/bulk-mark-read', [PointsNotificationLogController::class, 'bulkMarkAsRead'])->name('notification-logs.bulk-mark-read');
                        Route::post('notification-logs/{notification_log}/mark-read', [PointsNotificationLogController::class, 'markAsRead'])->name('notification-logs.mark-read');
                        Route::get('notification-logs', [PointsNotificationLogController::class, 'index'])->name('notification-logs.index');
                        Route::get('notification-logs/{notification_log}', [PointsNotificationLogController::class, 'show'])->name('notification-logs.show');
                    });

                    // ==========================================
                    // العمليات التلقائية (Automated Actions)
                    // ==========================================
                    Route::prefix('actions')->name('actions.')->group(function () {
                        // منح أفضل موظف شهرياً يدوياً
                        Route::post('monthly-top-performer', function () {
                            try {
                                \Illuminate\Support\Facades\Artisan::call('points:monthly-top-performer');
                                $output = \Illuminate\Support\Facades\Artisan::output();
                                return back()->with('success', 'تم تنفيذ أمر أفضل موظف شهرياً بنجاح: ' . $output);
                            } catch (\Exception $e) {
                                return back()->with('error', 'فشل في تنفيذ الأمر: ' . $e->getMessage());
                            }
                        })->name('monthly-top-performer');
                    });

                }); // end points.system middleware
            });
        });
    }
);
