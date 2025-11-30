<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use App\Notifications\LowStockNotification;
use App\Models\User;

class InventoryItemController extends Controller
{
    /**
     * Display a listing of inventory items for a specific branch.
     */
    public function index(Request $request, $branchId)
    {
        $branch = Branch::with('inventoryItems')->findOrFail($branchId);
        $this->authorize('viewInventory', $branch);

        $requiredType = $request->query('type');
        if ($requiredType && strtolower($branch->type) !== strtolower($requiredType)) {
            return response()->json([]);
        }

        return response()->json($branch->inventoryItems);
    }




    /**
     * Store a newly created inventory item in a branch.
     */
    public function store(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'price2' => 'nullable|numeric|min:0',
            'price3' => 'nullable|numeric|min:0',
            'buying_price' => 'required|numeric|min:0',
            'stock' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'is_butchery' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Handle image upload BEFORE create
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('inventory_images', 'public');
            $validated['image'] = $path; // e.g. "inventory_images/file.jpg"
        }

        $item = $branch->inventoryItems()->create($validated);

        return response()->json([
            'message' => 'Inventory item added successfully',
            'item'    => $item,
        ], 201);
    }

    /**
     * Update an existing inventory item in a branch.
     */
    public function update(Request $request, $branchId, $itemId)
    {
        $branch = Branch::findOrFail($branchId);
        $item   = $branch->inventoryItems()->findOrFail($itemId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'price2' => 'sometimes|nullable|numeric|min:0',
            'price3' => 'sometimes|nullable|numeric|min:0',
            'buying_price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|numeric|min:0',
            'unit' => 'sometimes|required|string|max:50',
            'is_butchery' => 'sometimes|required|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Handle image upload

        if ($request->hasFile('image')) {
            if ($item->image && file_exists(storage_path('app/public/'.$item->image))) {
                @unlink(storage_path('app/public/'.$item->image));
            }
            $validated['image'] = $request->file('image')->store('inventory_images', 'public');
        }

        $item->update($validated);

        if ($item->quantity < 10) {
            $owner = User::where('role', 'Owner')->first();
            $owner->notify(new LowStockNotification($item));
        }

        return response()->json([
            'message' => 'Inventory item updated successfully',
            'item'    => $item,
        ]);
    }

    /**
     * Remove an inventory item from storage.
     */
    public function destroy($branchId, $itemId)
    {
        $branch = Branch::findOrFail($branchId);
        $item   = $branch->inventoryItems()->findOrFail($itemId);
    
        // ✅ delete image file if exists
        if ($item->image) {
            // assumes you stored images in storage/app/public/items/...
            $imagePath = storage_path('app/public/' . $item->image);
    
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
    
        $item->delete();
    
        return response()->json(['message' => 'Inventory item and image deleted successfully']);
    }

        /**
     * Analyze inventory performance over a given period (daily, weekly, monthly, yearly).
     * Returns insights like total stock value, stock turnover, and top-performing items.
     */
    public function performance(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $period = $request->query('period', 'monthly'); // default = monthly
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        if (!in_array($period, $validPeriods)) {
            return response()->json(['error' => 'Invalid period.'], 400);
        }

        // Define the SQLite-compatible date grouping
        switch ($period) {
            case 'daily':
                $periodExpr = \DB::raw("date(sales.created_at) as period");
                break;
            case 'weekly':
                $periodExpr = \DB::raw("strftime('%Y-%W', sales.created_at) as period");
                break;
            case 'yearly':
                $periodExpr = \DB::raw("strftime('%Y', sales.created_at) as period");
                break;
            default:
                $periodExpr = \DB::raw("strftime('%Y-%m', sales.created_at) as period");
        }

        // Join sales and sale_items to track product movement
        $data = \DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('inventory_items', 'sale_items.inventory_item_id', '=', 'inventory_items.id')
            ->where('sales.branch_id', $branchId)
            ->select([
                $periodExpr,
                'inventory_items.name',
                \DB::raw('SUM(sale_items.quantity) as total_sold'),
                \DB::raw('SUM(sale_items.price) as total_revenue'),
                \DB::raw('AVG(inventory_items.stock) as avg_stock'),
                \DB::raw('MAX(inventory_items.stock) as current_stock'),
                \DB::raw('SUM(sale_items.price) - SUM(sale_items.quantity * inventory_items.buying_price) as profit'),
            ])
            ->groupBy('period', 'inventory_items.name')
            ->orderByRaw('MIN(sales.created_at) DESC')
            ->limit(20)
            ->get();

        // Compute summary KPIs
        $totalRevenue = $data->sum('total_revenue');
        $totalProfit = $data->sum('profit');
        $totalStockValue = InventoryItem::where('branch_id', $branchId)
            ->selectRaw('SUM(stock * buying_price) as total_value')
            ->value('total_value');

        $topProducts = $data->sortByDesc('total_sold')->take(5)->values();
        $slowMovers = $data->sortBy('total_sold')->take(5)->values();

        return response()->json([
            'branch' => $branch->name,
            'period' => $period,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_profit' => $totalProfit,
                'stock_value' => $totalStockValue,
            ],
            'top_products' => $topProducts,
            'slow_movers' => $slowMovers,
            'data' => $data,
        ]);
    }

    
}

