<?php
// app/Http/Controllers/ExpenseController.php
namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Branch;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * List all expenses for a branch.
     */
    public function index(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);
        $perPage = $request->query('per_page', 10);

        $expenses = $branch->expenses()
            ->latest()
            ->paginate($perPage);

        return response()->json($expenses);
    }


    /**
     * Store a new expense.
     */
    public function store(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $validated = $request->validate([
            'title'  => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => ['nullable','string',Rule::in(['Fuel','Salaries','Utilities','Rent','Repairs','Transport','Miscellaneous'])],
            'note'   => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id ?? null;

        $expense = $branch->expenses()->create($validated);

        return response()->json($expense, 201);
    }

    /**
     * Update an expense.
     */
    public function update(Request $request, $branchId, $expenseId)
    {
        $branch = Branch::findOrFail($branchId);
        $expense = $branch->expenses()->findOrFail($expenseId);

        $validated = $request->validate([
            'title'  => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => ['nullable','string',Rule::in(['Fuel','Salaries','Utilities','Rent','Repairs','Transport','Miscellaneous'])],
            'note'   => 'nullable|string',
        ]);

        $expense->update($validated);

        return response()->json($expense);
    }

    public function grouped(Request $request, $branchId)
    {
        $period = $request->query('period','daily');
    
        $start = match ($period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'yearly' => now()->startOfYear(),
            default => now()->startOfDay(),
        };
    
        $end = now();
    
        // load expenses for this branch & time window
        $expenses = \App\Models\Expense::where('branch_id', $branchId)
            ->whereBetween('created_at',[$start,$end])
            ->get();
    
        // group in PHP using Carbon formatting
        $grouped = [];
        foreach ($expenses as $exp) {
            $label = match ($period) {
                'weekly' => $exp->created_at->format('D'),     // Mon, Tue...
                'monthly' => $exp->created_at->format('d M'),  // 01 Sep...
                'yearly' => $exp->created_at->format('M'),     // Jan...
                default => $exp->created_at->format('H:i'),    // 14:00...
            };
    
            $cat = $exp->category ?? 'Misc';
    
            if (!isset($grouped[$label])) $grouped[$label] = [];
            if (!isset($grouped[$label][$cat])) $grouped[$label][$cat] = 0;
    
            $grouped[$label][$cat] += $exp->amount;
        }
    
        // convert to array of rows for the chart
        $rows = [];
        foreach ($grouped as $label => $cats) {
            $row = ['period'=>$label];
            foreach ($cats as $cat=>$sum) {
                $row[$cat] = $sum;
            }
            $rows[] = $row;
        }
    
        return response()->json($rows);
    }

    public function destroy($branchId, $expenseId)
    {
        $branch = Branch::findOrFail($branchId);
        $expense = $branch->expenses()->findOrFail($expenseId);

        $expense->delete();

        return response()->json(['message' => 'Expense deleted']);
    }
}
