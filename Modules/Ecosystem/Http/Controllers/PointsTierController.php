<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\PointsTier;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PointsTierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PointsTier::query();

        // Filter by user type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        $tiers = $query->ordered()->paginate(20)->withQueryString();

        $userTypes = UserType::values();

        // Statistics
        $stats = [
            'total' => PointsTier::count(),
            'active' => PointsTier::where('is_active', true)->count(),
            'client_tiers' => PointsTier::where('user_type', 'client')->count(),
            'employee_tiers' => PointsTier::where('user_type', 'employee')->count(),
        ];

        return view('ecosystem::points.tiers.index', compact('tiers', 'userTypes', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $userTypes = UserType::values();
        
        // Get existing tiers for reference
        $existingTiers = PointsTier::select('user_type', 'min_points', 'max_points', 'name')
            ->ordered()
            ->get()
            ->groupBy('user_type');

        return view('ecosystem::points.tiers.create', compact('userTypes', 'existingTiers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'user_type' => 'required|string|in:' . implode(',', UserType::values()),
            'min_points' => 'required|integer|min:0',
            'max_points' => 'nullable|integer|min:0|gt:min_points',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'points_multiplier' => 'nullable|numeric|min:1|max:10',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'priority_support' => 'boolean',
            'free_shipping' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'benefits' => 'nullable|array',
            'benefits.*' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('tiers', 'public');
        }

        // Clean benefits array
        if (isset($validated['benefits'])) {
            $validated['benefits'] = array_filter($validated['benefits']);
        }

        // Set defaults
        $validated['is_active'] = $request->has('is_active');
        $validated['priority_support'] = $request->has('priority_support');
        $validated['free_shipping'] = $request->has('free_shipping');
        $validated['points_multiplier'] = $validated['points_multiplier'] ?? 1;
        $validated['discount_percentage'] = $validated['discount_percentage'] ?? 0;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        PointsTier::create($validated);

        return redirect()
            ->route('ecosystem.points.tiers.index')
            ->with('success', 'تم إضافة مستوى النقاط بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(PointsTier $tier)
    {
        $tier->load(['upgrades', 'downgrades', 'rewards']);
        
        // Get next tier for progress info
        $nextTier = $tier->getNextTier();
        
        // Count users in this tier (if you have a way to track this)
        $usersCount = 0; // Placeholder - implement based on your user tracking
        
        return view('ecosystem::points.tiers.show', compact('tier', 'nextTier', 'usersCount'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PointsTier $tier)
    {
        $userTypes = UserType::values();
        
        // Get existing tiers for reference (exclude current)
        $existingTiers = PointsTier::select('user_type', 'min_points', 'max_points', 'name')
            ->where('id', '!=', $tier->id)
            ->ordered()
            ->get()
            ->groupBy('user_type');

        return view('ecosystem::points.tiers.edit', compact('tier', 'userTypes', 'existingTiers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PointsTier $tier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'user_type' => 'required|string|in:' . implode(',', UserType::values()),
            'min_points' => 'required|integer|min:0',
            'max_points' => 'nullable|integer|min:0|gt:min_points',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'points_multiplier' => 'nullable|numeric|min:1|max:10',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'priority_support' => 'boolean',
            'free_shipping' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'benefits' => 'nullable|array',
            'benefits.*' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($tier->image) {
                Storage::disk('public')->delete($tier->image);
            }
            $validated['image'] = $request->file('image')->store('tiers', 'public');
        }

        // Clean benefits array
        if (isset($validated['benefits'])) {
            $validated['benefits'] = array_filter($validated['benefits']);
        }

        // Set defaults
        $validated['is_active'] = $request->has('is_active');
        $validated['priority_support'] = $request->has('priority_support');
        $validated['free_shipping'] = $request->has('free_shipping');
        $validated['points_multiplier'] = $validated['points_multiplier'] ?? 1;
        $validated['discount_percentage'] = $validated['discount_percentage'] ?? 0;

        $tier->update($validated);

        return redirect()
            ->route('ecosystem.points.tiers.index')
            ->with('success', 'تم تحديث مستوى النقاط بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PointsTier $tier)
    {
        // Delete image if exists
        if ($tier->image) {
            Storage::disk('public')->delete($tier->image);
        }

        $tier->delete();

        return redirect()
            ->route('ecosystem.points.tiers.index')
            ->with('success', 'تم حذف مستوى النقاط بنجاح');
    }

    /**
     * Toggle tier status
     */
    public function toggleStatus(PointsTier $tier)
    {
        $tier->update(['is_active' => !$tier->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $tier->is_active,
            'message' => $tier->is_active ? 'تم تفعيل المستوى' : 'تم تعطيل المستوى'
        ]);
    }

    /**
     * Bulk delete tiers
     */
    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'لم يتم تحديد أي عناصر']);
        }

        // Delete images for selected tiers
        $tiers = PointsTier::whereIn('id', $ids)->get();
        foreach ($tiers as $tier) {
            if ($tier->image) {
                Storage::disk('public')->delete($tier->image);
            }
        }

        PointsTier::whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف ' . count($ids) . ' مستوى بنجاح'
        ]);
    }

    /**
     * Export tiers
     */
    public function export(Request $request)
    {
        // Placeholder for export functionality
        return redirect()->back()->with('info', 'ميزة التصدير قيد التطوير');
    }

    /**
     * Reorder tiers
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'tiers' => 'required|array',
            'tiers.*.id' => 'required|exists:points_tiers,id',
            'tiers.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->tiers as $tierData) {
            PointsTier::where('id', $tierData['id'])->update(['sort_order' => $tierData['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة ترتيب المستويات بنجاح'
        ]);
    }
}
