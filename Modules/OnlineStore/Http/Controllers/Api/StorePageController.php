<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\StorePageType;
use App\Http\Controllers\Controller;
use App\Models\Store\StorePage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;

/**
 * متحكم صفحات المتجر الإلكتروني
 * Store Pages Controller
 *
 * يدير عرض الصفحات الثابتة والسياسات
 */
class StorePageController extends Controller
{
    use FormatsImages;

    /**
     * عرض قائمة الصفحات
     * List pages
     */
    public function index(Request $request): JsonResponse
    {
        $query = StorePage::active()->ordered();

        // تصفية حسب النوع
        if ($request->filled('type')) {
            $type = is_string($request->type) ? StorePageType::tryFrom($request->type) : null;
            if ($type) {
                $query->where('type', $type);
            }
        }

        $pages = $query->get();

        return response()->json([
            'success' => true,
            'pages' => $pages->map(fn ($page) => $this->formatPageSummary($page)),
        ]);
    }

    /**
     * عرض صفحة بالمعرف أو الرابط
     * Show page
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        // البحث بالمعرف أو الرابط الصديق
        $page = StorePage::active()
            ->where(function ($query) use ($identifier) {
                $query->where('slug', $identifier)
                    ->orWhere('id', $identifier);
            })
            ->first();

        if (! $page) {
            return response()->json([
                'success' => false,
                'message' => 'الصفحة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $this->formatPage($page),
        ]);
    }

    /**
     * صفحة سياسة الخصوصية
     * Privacy policy page
     */
    public function privacyPolicy(): JsonResponse
    {
        $page = StorePage::active()
            ->whereIn('slug', ['privacy-policy']) // أنواع الصفحة في النظام: page/policy/faq فقط، فتُحدَّد الصفحة بـslug
            ->first();

        if (! $page) {
            return response()->json([
                'success' => false,
                'message' => 'صفحة سياسة الخصوصية غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $this->formatPage($page),
        ]);
    }

    /**
     * صفحة الشروط والأحكام
     * Terms and conditions page
     */
    public function termsConditions(): JsonResponse
    {
        $page = StorePage::active()
            ->whereIn('slug', ['terms-of-service', 'terms-conditions']) // أنواع الصفحة في النظام: page/policy/faq فقط، فتُحدَّد الصفحة بـslug
            ->first();

        if (! $page) {
            return response()->json([
                'success' => false,
                'message' => 'صفحة الشروط والأحكام غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $this->formatPage($page),
        ]);
    }

    /**
     * صفحة سياسة الاسترجاع
     * Return policy page
     */
    public function returnPolicy(): JsonResponse
    {
        $page = StorePage::active()
            ->whereIn('slug', ['return-exchange-policy', 'return-policy']) // أنواع الصفحة في النظام: page/policy/faq فقط، فتُحدَّد الصفحة بـslug
            ->first();

        if (! $page) {
            return response()->json([
                'success' => false,
                'message' => 'صفحة سياسة الاسترجاع غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $this->formatPage($page),
        ]);
    }

    /**
     * صفحة سياسة الشحن
     * Shipping policy page
     */
    public function shippingPolicy(): JsonResponse
    {
        $page = StorePage::active()
            ->whereIn('slug', ['shipping-delivery-policy', 'shipping-policy']) // أنواع الصفحة في النظام: page/policy/faq فقط، فتُحدَّد الصفحة بـslug
            ->first();

        if (! $page) {
            return response()->json([
                'success' => false,
                'message' => 'صفحة سياسة الشحن غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $this->formatPage($page),
        ]);
    }

    /**
     * صفحة من نحن
     * About us page
     */
    public function aboutUs(): JsonResponse
    {
        $page = StorePage::active()
            ->whereIn('slug', ['about-us']) // أنواع الصفحة في النظام: page/policy/faq فقط، فتُحدَّد الصفحة بـslug
            ->first();

        if (! $page) {
            return response()->json([
                'success' => false,
                'message' => 'صفحة من نحن غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $this->formatPage($page),
        ]);
    }

    /**
     * صفحة اتصل بنا
     * Contact us page
     */
    public function contactUs(): JsonResponse
    {
        $page = StorePage::active()
            ->whereIn('slug', ['contact-us']) // أنواع الصفحة في النظام: page/policy/faq فقط، فتُحدَّد الصفحة بـslug
            ->first();

        if (! $page) {
            return response()->json([
                'success' => false,
                'message' => 'صفحة اتصل بنا غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $this->formatPage($page),
        ]);
    }

    /**
     * الأسئلة الشائعة
     * FAQs
     */
    public function faqs(Request $request): JsonResponse
    {
        $page = StorePage::active()
            ->where('type', StorePageType::FAQ)
            ->first();

        if (! $page) {
            return response()->json([
                'success' => false,
                'message' => 'صفحة الأسئلة الشائعة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'page' => $this->formatPage($page),
            'faqs' => $page->faq_items ?? [],
        ]);
    }

    /**
     * تنسيق ملخص الصفحة
     */
    private function formatPageSummary(StorePage $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->getTitleForLocale(),
            'slug' => $page->slug,
            'type' => $page->type?->value,
            'icon' => $page->icon,
            'show_in_menu' => $page->show_in_menu,
            'show_in_footer' => $page->show_in_footer,
        ];
    }

    /**
     * تنسيق الصفحة الكاملة
     */
    private function formatPage(StorePage $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->getTitleForLocale(),
            'slug' => $page->slug,
            'content' => $page->getContentForLocale(),
            'type' => $page->type?->value,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'icon' => $page->icon,
            'image' => $this->formatImageUrl($page->image),
            'show_in_menu' => $page->show_in_menu,
            'show_in_footer' => $page->show_in_footer,
            'updated_at' => $page->updated_at?->toDateTimeString(),
        ];
    }
}
