<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\BannerPosition;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;

/**
 * متحكم بانرات المتجر الإلكتروني
 * Store Banners Controller
 *
 * يدير عرض البانرات والإعلانات
 */
class StoreBannerController extends Controller
{
    use FormatsImages;

    /**
     * عرض قائمة البانرات
     * List banners
     */
    public function index(Request $request): JsonResponse
    {
        $query = StoreBanner::active()
            ->valid()
            ->ordered();

        // تصفية حسب الموقع
        if ($request->filled('position')) {
            $position = BannerPosition::tryFrom($request->position);
            if ($position) {
                $query->byPosition($position);
            }
        }

        $banners = $query->get();

        $isMobile = $request->boolean('mobile');

        return response()->json([
            'success' => true,
            'banners' => $banners->map(fn ($banner) => $this->formatBanner($banner, $isMobile)),
        ]);
    }

    /**
     * بانرات الصفحة الرئيسية (السلايدر)
     * Home sliders
     */
    public function homeSliders(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 5), 10);
        $isMobile = $request->boolean('mobile');

        $banners = StoreBanner::getHomeSliders($limit);

        return response()->json([
            'success' => true,
            'banners' => $banners->map(fn ($banner) => $this->formatBanner($banner, $isMobile)),
        ]);
    }

    /**
     * بانرات الشريط الجانبي
     * Sidebar banners
     */
    public function sidebar(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 3), 10);
        $isMobile = $request->boolean('mobile');

        $banners = StoreBanner::getSidebarBanners($limit);

        return response()->json([
            'success' => true,
            'banners' => $banners->map(fn ($banner) => $this->formatBanner($banner, $isMobile)),
        ]);
    }

    /**
     * بانرات حسب الموقع
     * Banners by position
     */
    public function byPosition(Request $request, string $position): JsonResponse
    {
        $positionEnum = BannerPosition::tryFrom($position);

        if (! $positionEnum) {
            return response()->json([
                'success' => false,
                'message' => 'موقع البانر غير صالح',
            ], 400);
        }

        $limit = $request->get('limit');
        $isMobile = $request->boolean('mobile');

        $banners = StoreBanner::getForPosition($positionEnum, $limit);

        return response()->json([
            'success' => true,
            'banners' => $banners->map(fn ($banner) => $this->formatBanner($banner, $isMobile)),
        ]);
    }

    /**
     * تنسيق البانر
     */
    private function formatBanner(StoreBanner $banner, bool $isMobile = false): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->getTitleForLocale(),
            'subtitle' => $banner->subtitle,
            'image' => $this->formatImageUrl($banner->getImageForDevice($isMobile)),
            'link' => $banner->full_link,
            'link_type' => $banner->link_type?->value,
            'link_value' => $banner->link_value,
            'position' => $banner->position?->value,
            'button_text' => $banner->button_text,
            'starts_at' => $banner->starts_at?->toDateTimeString(),
            'ends_at' => $banner->ends_at?->toDateTimeString(),
        ];
    }
}
