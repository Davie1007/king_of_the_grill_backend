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
            'price' => 'required|numeric|min:0',       // retail price
            'price2' => 'nullable|numeric|min:0',      // wholesale price
            'buying_price' => 'required|numeric|min:0',
            'stock' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // ✅ Branch type logic
        if (strtolower($branch->type) === 'butchery') {
            $validated['is_butchery'] = true;
        } else {
            $validated['is_butchery'] = false;
        }

        // ✅ Drinks and Gas share similar non-butchery logic
        if (strtolower($branch->type) === 'drinks') {
            // Drinks use wholesale + retail + buying_price
            $validated['price'] = $request->input('price');    // retail
            $validated['price2'] = $request->input('price2');  // wholesale
        } elseif (strtolower($branch->type) === 'gas') {
            // Gas may have two pricing levels, or one (reuse your existing pattern)
            $validated['price2'] = $request->input('price2');
        }

        // ✅ Image handling
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('inventory_images', 'public');
        }

        $item = $branch->inventoryItems()->create($validated);

        return response()->json([
            'message' => ucfirst($branch->type) . ' item added successfully',
            'item' => $item,
        ], 201);
    }


    /**
     * Update an existing inventory item in a branch.
     */
    public function update(Request $request, $branchId, $itemId)
    {
        $branch = Branch::findOrFail($branchId);
        $item = $branch->inventoryItems()->findOrFail($itemId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'price2' => 'sometimes|nullable|numeric|min:0',
            'buying_price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:50',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $validated['is_butchery'] = strtolower($branch->type) === 'butchery';

        if ($request->hasFile('image')) {
            if ($item->image && file_exists(storage_path('app/public/' . $item->image))) {
                unlink(storage_path('app/public/' . $item->image));
            }
            $validated['image'] = $request->file('image')->store('inventory_images', 'public');
        }

        $item->update($validated);

        return response()->json([
            'message' => ucfirst($branch->type) . ' item updated successfully',
            'item' => $item,
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

