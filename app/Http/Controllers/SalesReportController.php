<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesReportController extends Controller
{
    // 1. Sales totals per branch over time
    public function grouped(Request $request)
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        $period = in_array($request->query('period', 'daily'), $validPeriods) ? $request->query('period') : 'daily';
        $phone = $request->query('customer_telephone_number');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
    
        switch ($period) {
            case 'weekly':
                $periodExpr = DB::raw("strftime('%Y-%W', created_at) as period");
                break;
            case 'monthly':
                $periodExpr = DB::raw("strftime('%Y-%m', created_at) as period");
                break;
            case 'yearly':
                $periodExpr = DB::raw("strftime('%Y', created_at) as period");
                break;
            default:
                $periodExpr = DB::raw("date(created_at) as period");
        }
    
        $query = Sale::select([
            $periodExpr,
            'branch_id',
            DB::raw('SUM(total) as total_amount')
        ])
        ->groupBy('period', 'branch_id')
        ->orderByRaw('MIN(created_at) desc');
    
        if ($phone) {
            $query->where('customer_telephone_number', $phone);
        }
    
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
    
        Log::info('Grouped Sales Query', [
            'url' => $request->url(),
            'params' => $request->all(),
            'query' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'data' => $query->get()->toArray(),
            'timestamp' => now()->setTimezone('Africa/Nairobi')->toDateTimeString()
        ]);
    
        $data = $query->get()->map(function($row){
            return [
                'period' => $row->period,
                'branch' => optional($row->branch)->name ?? $row->branch_id,
                'total' => (float) number_format($row->total_amount, 2, '.', '')
            ];
        });
    
        return response()->json($data->isEmpty() ? [] : $data);
    }


    public function paymentsGroupedAll(Request $request)
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        $period = in_array($request->query('period', 'daily'), $validPeriods)
            ? $request->query('period')
            : 'daily';
    
        switch ($period) {
            case 'weekly':
                $periodExpr = DB::raw("strftime('%Y-%W', sales.created_at) as period");
                break;
            case 'monthly':
                $periodExpr = DB::raw("strftime('%Y-%m', sales.created_at) as period");
                break;
            case 'yearly':
                $periodExpr = DB::raw("strftime('%Y', sales.created_at) as period");
                break;
            default:
                $periodExpr = DB::raw("date(sales.created_at) as period");
        }
    
        $query = DB::table('sales')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->select([
                $periodExpr,
                'branches.name as branch',
                'sales.payment_method',
                DB::raw('SUM(sales.total) as total_amount')
            ])
            ->groupBy('period', 'branches.name', 'sales.payment_method')
            ->orderByRaw('MIN(sales.created_at) desc');
    
        if ($request->filled(['start_date', 'end_date'])) {
            $query->whereBetween('sales.created_at', [$request->start_date, $request->end_date]);
        }
    
        return response()->json($query->get());
    }
    

    // 2. Product distribution per branch
    public function productsDistribution(Request $request)
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        $period = in_array($request->query('period', 'daily'), $validPeriods) ? $request->query('period') : 'daily';
        $phone = $request->query('customer_telephone_number');

        switch ($period) {
            case 'weekly':
                $periodExpr = DB::raw("strftime('%Y-%W', sales.created_at) as period");
                break;
            case 'monthly':
                $periodExpr = DB::raw("strftime('%Y-%m', sales.created_at) as period");
                break;
            case 'yearly':
                $periodExpr = DB::raw("strftime('%Y', sales.created_at) as period");
                break;
            default:
                $periodExpr = DB::raw("date(sales.created_at) as period");
        }

        $query = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'sale_items.item_id')
            ->select([
                $periodExpr,
                'sales.branch_id',
                'inventory_items.name as product',
                DB::raw('SUM(sale_items.quantity) as total_qty')
            ])
            ->groupBy('period', 'sales.branch_id', 'inventory_items.name')
            ->orderByRaw('MIN(sales.created_at) desc');

        if ($phone) {
            $query->where('sales.customer_telephone_number', $phone);
        }

        Log::info('Products Distribution Request', [
            'url' => $request->url(),
            'params' => $request->all(),
            'query' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'timestamp' => now()->setTimezone('Africa/Nairobi')->toDateTimeString()
        ]);

        $data = $query->get()->map(function($row){
            return [
                'period'  => $row->period,
                'branch'  => optional($row->branch)->name ?? $row->branch_id,
                'product' => $row->product,
                'value'   => $row->total_qty,
            ];
        });

        return response()->json($data->isEmpty() ? [] : $data);
    }

    // 3. Payments grouped for all branches
    public function paymentsGrouped(Request $request)
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        $period = in_array($request->query('period', 'daily'), $validPeriods) ? $request->query('period') : 'daily';
        $phone = $request->query('customer_telephone_number');

        switch ($period) {
            case 'weekly':
                $periodExpr = DB::raw("strftime('%Y-%W', created_at) as period");
                break;
            case 'monthly':
                $periodExpr = DB::raw("strftime('%Y-%m', created_at) as period");
                break;
            case 'yearly':
                $periodExpr = DB::raw("strftime('%Y', created_at) as period");
                break;
            default:
                $periodExpr = DB::raw("date(created_at) as period");
        }

        $query = Sale::select([
            $periodExpr,
            'branch_id',
            'payment_method',
            DB::raw('SUM(total) as total_amount')
        ])
        ->groupBy('period', 'branch_id', 'payment_method')
        ->orderByRaw('MIN(created_at) desc');

        if ($phone) {
            $query->where('customer_telephone_number', $phone);
        }

        Log::info('Payments Grouped Request', [
            'url' => $request->url(),
            'params' => $request->all(),
            'query' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'timestamp' => now()->setTimezone('Africa/Nairobi')->toDateTimeString()
        ]);

        $data = $query->get()->map(function($row){
            return [
                'period'         => $row->period,
                'branch'         => optional($row->branch)->name ?? $row->branch_id,
                'payment_method' => $row->payment_method,
                'total_amount'   => $row->total_amount,
            ];
        });

        return response()->json($data->isEmpty() ? [] : $data);
    }
    
    public function groupedAll(Request $request)
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        $period = in_array($request->query('period', 'daily'), $validPeriods) ? $request->query('period') : 'daily';

        switch ($period) {
            case 'weekly': $periodExpr = DB::raw("strftime('%Y-%W', created_at) as period"); break;
            case 'monthly': $periodExpr = DB::raw("strftime('%Y-%m', created_at) as period"); break;
            case 'yearly': $periodExpr = DB::raw("strftime('%Y', created_at) as period"); break;
            default: $periodExpr = DB::raw("date(created_at) as period");
        }

        $query = Sale::select([$periodExpr, 'branch_id', 'payment_method', DB::raw('SUM(total) as total')])
            ->groupBy('period', 'branch_id', 'payment_method')
            ->orderByRaw('MIN(created_at) desc');

        $data = $query->get()->map(fn($r) => [
            'period' => $r->period,
            'branch' => optional($r->branch)->name ?? $r->branch_id,
            'method' => $r->payment_method,
            'total' => $r->total
        ]);

        return response()->json($data);
    }

    public function topProducts(Request $request)
    {
        $limit = $request->query('limit', 10);
        $metric = $request->query('metric', 'revenue'); // or quantity

        $query = SaleItem::join('inventory_items', 'sale_items.item_id', '=', 'inventory_items.id')
            ->select(
                'inventory_items.name as product',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.price * sale_items.quantity) as total_revenue')
            )
            ->groupBy('inventory_items.name')
            ->orderByDesc($metric === 'quantity' ? 'total_quantity' : 'total_revenue')
            ->limit($limit);

        $data = $query->get();

        return response()->json($data);
    }

    public function topCustomers(Request $request)
    {
        $limit = $request->query('limit', 10);

        $query = Sale::select(
            DB::raw("COALESCE(customer_name, customer_telephone_number, 'Unknown') as customer"),
            DB::raw('SUM(total) as total_spent'),
            DB::raw('COUNT(*) as purchase_count')
        )
        ->groupBy('customer')
        ->orderByDesc('total_spent')
        ->limit($limit);

        $data = $query->get();

        return response()->json($data);
    }

    public function forecast(Request $request)
    {
        // Simple linear regression style projection
        $sales = Sale::select(DB::raw("strftime('%Y-%m', created_at) as period"), DB::raw("SUM(total) as revenue"))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        if ($sales->count() < 3) {
            return response()->json(['error' => 'Not enough data for forecast'], 400);
        }

        // Convert to numeric series
        $x = range(1, $sales->count());
        $y = $sales->pluck('revenue')->toArray();
        $n = count($x);

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = array_sum(array_map(fn($i) => $x[$i] * $y[$i], array_keys($x)));
        $sumX2 = array_sum(array_map(fn($xi) => $xi ** 2, $x));

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX ** 2);
        $intercept = ($sumY - $slope * $sumX) / $n;

        $nextPeriod = $n + 1;
        $forecast = round($intercept + $slope * $nextPeriod, 2);

        return response()->json([
            'historical' => $sales,
            'predicted_next' => $forecast,
            'growth_rate' => round(($slope / max($y)) * 100, 2),
        ]);
    }

    public function stockTurnover(Request $request)
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        $period = in_array($request->query('period', 'daily'), $validPeriods) ? $request->query('period') : 'daily';

        switch ($period) {
            case 'weekly':
                $periodExpr = DB::raw("strftime('%Y-%W', sales.created_at) as period");
                break;
            case 'monthly':
                $periodExpr = DB::raw("strftime('%Y-%m', sales.created_at) as period");
                break;
            case 'yearly':
                $periodExpr = DB::raw("strftime('%Y', sales.created_at) as period");
                break;
            default:
                $periodExpr = DB::raw("date(sales.created_at) as period");
        }

        // Calculate total quantity sold per item per branch
        $salesQuery = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'sale_items.item_id')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->select([
                'branches.name as branch',
                'inventory_items.name as item',
                DB::raw('SUM(sale_items.quantity) as total_quantity_sold')
            ])
            ->groupBy('branches.name', 'inventory_items.name');

        // Apply period filter
        if ($period !== 'daily') {
            $salesQuery->whereRaw("sales.created_at >= date('now', 'start of $period')");
        } else {
            $salesQuery->whereRaw("sales.created_at >= date('now')");
        }

        $salesData = $salesQuery->get();

        // Get current stock per item per branch (simplified as current stock)
        $inventoryQuery = InventoryItem::join('branches', 'inventory_items.branch_id', '=', 'branches.id')
            ->select([
                'branches.name as branch',
                'inventory_items.name as item',
                DB::raw('inventory_items.stock as current_stock') // Renamed to current_stock
            ])
            ->groupBy('branches.name', 'inventory_items.name');

        $inventoryData = $inventoryQuery->get();

        // Merge sales and inventory data to calculate turnover
        $data = $salesData->map(function ($sale) use ($inventoryData) {
            // Find inventory record matching both branch and item
            $inventory = $inventoryData->firstWhere([
                'branch' => $sale->branch,
                'item' => $sale->item
            ]);
            $currentStock = $inventory ? $inventory->current_stock : 1; // Avoid division by zero
            $turnoverRate = $sale->total_quantity_sold / ($currentStock ?: 1);

            return [
                'branch' => $sale->branch,
                'item' => $sale->item,
                'turnover_rate' => round($turnoverRate, 2),
            ];
        })->values();

        Log::info('Stock Turnover Request', [
            'url' => $request->url(),
            'params' => $request->all(),
            'query' => $salesQuery->toSql(),
            'bindings' => $salesQuery->getBindings(),
            'data' => $data->toArray(),
            'timestamp' => now()->setTimezone('Africa/Nairobi')->toDateTimeString()
        ]);

        return response()->json($data->isEmpty() ? [] : $data);
    }

    public function newReturningCustomers(Request $request)
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        $period = in_array($request->query('period', 'monthly'), $validPeriods) ? $request->query('period') : 'monthly';
        $excludeMpesaCredit = $request->boolean('exclude_mpesa_credit', false);

        // Define period expression
        switch ($period) {
            case 'daily':
                $periodExpr = "date(sales.created_at)";
                $periodFormat = "%Y-%m-%d";
                break;
            case 'weekly':
                $periodExpr = "strftime('%Y-%W', sales.created_at)";
                $periodFormat = "%Y-%W";
                break;
            case 'monthly':
                $periodExpr = "strftime('%Y-%m', sales.created_at)";
                $periodFormat = "%Y-%m";
                break;
            case 'yearly':
                $periodExpr = "strftime('%Y', sales.created_at)";
                $periodFormat = "%Y";
                break;
            default:
                $periodExpr = "strftime('%Y-%m', sales.created_at)";
                $periodFormat = "%Y-%m";
        }

        // CTE for verifiable sales (M-Pesa, Card, Credit) with first_sale_date
        $verifiableQuery = DB::table('sales')
            ->whereIn('payment_method', ['M-Pesa', 'Card', 'Credit'])
            ->selectRaw("
                {$periodExpr} as period,
                COALESCE(sales.customer_telephone_number, sales.customer_id_number) as customer_id,
                MIN(sales.created_at) OVER (PARTITION BY COALESCE(sales.customer_telephone_number, sales.customer_id_number)) as first_sale_date,
                sales.created_at as sale_date
            ");

        if ($excludeMpesaCredit) {
            $verifiableQuery->whereNotIn('sales.payment_method', ['M-Pesa', 'Credit']); // Exclude M-Pesa/Credit, keep Card as verifiable
        }

        // Verifiable new/returning
        $verifiableResults = DB::table(DB::raw("({$verifiableQuery->toSql()}) as verifiable_sales_with_first"))
            ->mergeBindings($verifiableQuery)
            ->selectRaw("
                period,
                COUNT(DISTINCT CASE WHEN period = strftime('{$periodFormat}', first_sale_date) THEN customer_id END) as verifiable_new,
                COUNT(DISTINCT CASE WHEN period > strftime('{$periodFormat}', first_sale_date) THEN customer_id END) as verifiable_returning
            ")
            ->groupBy('period')
            ->get();

        // Cash sales count per period (assume each is a customer)
        $cashQuery = DB::table('sales')
            ->where('payment_method', 'Cash')
            ->selectRaw("
                {$periodExpr} as period,
                COUNT(*) as cash_sales_count
            ")
            ->groupBy('period');

        $cashResults = $cashQuery->get();

        // Merge verifiable and cash data per period
        $periods = $verifiableResults->pluck('period')->merge($cashResults->pluck('period'))->unique();

        $data = $periods->map(function ($p) use ($verifiableResults, $cashResults) {
            $verifiable = $verifiableResults->firstWhere('period', $p);
            $cash = $cashResults->firstWhere('period', $p);

            $verifiable_new = $verifiable ? (int) $verifiable->verifiable_new : 0;
            $verifiable_returning = $verifiable ? (int) $verifiable->verifiable_returning : 0;
            $cash_sales_count = $cash ? (int) $cash->cash_sales_count : 0;

            $verifiable_total = $verifiable_new + $verifiable_returning;
            $verifiable_returning_ratio = $verifiable_total > 0 ? $verifiable_returning / $verifiable_total : 0;

            $estimated_cash_returning = round($cash_sales_count * $verifiable_returning_ratio);
            $estimated_cash_new = $cash_sales_count - $estimated_cash_returning;

            $total_new = $verifiable_new + $estimated_cash_new;
            $total_returning = $verifiable_returning + $estimated_cash_returning;
            $total_customers = $total_new + $total_returning;

            return [
                'period' => $p,
                'new_customers' => $total_new,
                'returning_customers' => $total_returning,
                'total_customers' => $total_customers
            ];
        })->sortBy('period')->values();

        Log::info('New/Returning Customers Request', [
            'url' => $request->url(),
            'params' => $request->all(),
            'verifiableResults' => $verifiableResults->toArray(),
            'cashResults' => $cashResults->toArray(),
            'data' => $data->toArray(),
            'timestamp' => now()->setTimezone('Africa/Nairobi')->toDateTimeString()
        ]);

        return response()->json($data->isEmpty() ? [] : $data);
    }
    public function revenueExpenseProfit(Request $request)
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        $period = in_array($request->query('period', 'monthly'), $validPeriods) ? $request->query('period') : 'monthly';

        // Define period expression
        switch ($period) {
            case 'daily':
                $periodExpr = "date(sales.created_at)";
                $expensePeriodExpr = "date(expenses.created_at)";
                $periodFormat = "%Y-%m-%d";
                break;
            case 'weekly':
                $periodExpr = "strftime('%Y-%W', sales.created_at)";
                $expensePeriodExpr = "strftime('%Y-%W', expenses.created_at)";
                $periodFormat = "%Y-%W";
                break;
            case 'monthly':
                $periodExpr = "strftime('%Y-%m', sales.created_at)";
                $expensePeriodExpr = "strftime('%Y-%m', expenses.created_at)";
                $periodFormat = "%Y-%m";
                break;
            case 'yearly':
                $periodExpr = "strftime('%Y', sales.created_at)";
                $expensePeriodExpr = "strftime('%Y', expenses.created_at)";
                $periodFormat = "%Y";
                break;
            default:
                $periodExpr = "strftime('%Y-%m', sales.created_at)";
                $expensePeriodExpr = "strftime('%Y-%m', expenses.created_at)";
                $periodFormat = "%Y-%m";
        }

        // Query for revenue (sum of sales.total)
        $revenueQuery = DB::table('sales')
            ->selectRaw("{$periodExpr} as period, COALESCE(SUM(total), 0) as revenue")
            ->groupBy('period');

        // Query for expenses (sum of expenses.amount)
        $expenseQuery = DB::table('expenses')
            ->selectRaw("{$expensePeriodExpr} as period, COALESCE(SUM(amount), 0) as expense")
            ->groupBy('period');

        // Combine revenue and expense data
        $results = DB::table(DB::raw("({$revenueQuery->toSql()}) as revenue"))
            ->mergeBindings($revenueQuery)
            ->leftJoin(DB::raw("({$expenseQuery->toSql()}) as expense"), 'revenue.period', '=', 'expense.period')
            ->mergeBindings($expenseQuery)
            ->selectRaw("
                revenue.period,
                COALESCE(revenue.revenue, 0) as revenue,
                COALESCE(expense.expense, 0) as expense,
                COALESCE(revenue.revenue, 0) - COALESCE(expense.expense, 0) as profit
            ")
            ->orderBy('revenue.period') // Fixed: Specify revenue.period instead of ambiguous period
            ->get();

        // Format results
        $data = $results->map(function ($row) {
            return [
                'period' => $row->period,
                'revenue' => (float) number_format($row->revenue, 2, '.', ''),
                'expense' => (float) number_format($row->expense, 2, '.', ''),
                'profit' => (float) number_format($row->profit, 2, '.', '')
            ];
        })->values();

        Log::info('Revenue/Expense/Profit Request', [
            'url' => $request->url(),
            'params' => $request->all(),
            'query' => $results->toArray(), // Simplified logging
            'data' => $data->toArray(),
            'timestamp' => now()->setTimezone('Africa/Nairobi')->toDateTimeString()
        ]);

        return response()->json($data->isEmpty() ? [] : $data);
    }
    public function inventoryIntelligence(Request $request)
    {
        $branches = \App\Models\Branch::select('id','name')->get();

        $inventoryData = \App\Models\InventoryItem::selectRaw('branch_id, SUM(stock * buying_price) as total_value, AVG(stock) as avg_stock')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $turnoverData = $this->stockTurnover($request)->getData(true);

        $merged = $branches->map(function ($branch) use ($inventoryData, $turnoverData) {
            $turnover = collect($turnoverData)->where('branch', $branch->name)->avg('turnover_rate');
            $inv = $inventoryData[$branch->id] ?? null;
            return [
                'branch' => $branch->name,
                'stock_value' => round($inv->total_value ?? 0, 2),
                'avg_stock' => round($inv->avg_stock ?? 0, 2),
                'avg_turnover_rate' => round($turnover ?? 0, 2),
            ];
        });

        return response()->json([
            'summary' => [
                'total_stock_value' => $merged->sum('stock_value'),
                'average_turnover_rate' => round($merged->avg('avg_turnover_rate'), 2),
            ],
            'branches' => $merged->values(),
        ]);
    }

    public function customerBehaviorInsights(Request $request)
    {
        $customerData = $this->newReturningCustomers($request)->getData(true);

        $avgNew = collect($customerData)->avg('new_customers');
        $avgReturning = collect($customerData)->avg('returning_customers');
        $retentionRate = $avgReturning > 0 ? round(($avgReturning / ($avgNew + $avgReturning)) * 100, 2) : 0;

        $avgSpending = \App\Models\Sale::avg('total');
        $topCustomer = \App\Models\Sale::selectRaw("COALESCE(customer_name, customer_telephone_number, 'Unknown') as customer, SUM(total) as total_spent")
            ->groupBy('customer')
            ->orderByDesc('total_spent')
            ->first();

        return response()->json([
            'summary' => [
                'avg_new_customers' => round($avgNew, 2),
                'avg_returning_customers' => round($avgReturning, 2),
                'retention_rate' => $retentionRate,
                'average_spending' => round($avgSpending ?? 0, 2),
            ],
            'top_customer' => $topCustomer,
            'period_data' => $customerData,
        ]);
    }

}