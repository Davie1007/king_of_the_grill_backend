<?php
namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(){ return response()->json(Branch::all()); }
    public function show(Branch $branch){ return response()->json($branch); }
    public function store(Request $r)
    {
        $b = Branch::create($r->only([
            'name', 'tillNumber', 'roleConfig', 'managerId', 'type',
            'latitude', 'longitude', 'service_radius',
            'daily_target', 'weekly_target', 'monthly_target', 'yearly_target'
        ]));
        return response()->json($b, 201);
    }
    public function update(Request $r, Branch $branch)
    {
        $branch->update($r->only([
            'name', 'tillNumber', 'roleConfig', 'managerId', 'type',
            'latitude', 'longitude', 'service_radius',
            'daily_target', 'weekly_target', 'monthly_target', 'yearly_target'
        ]));
        return response()->json($branch);
    }
    public function destroy(Branch $branch){ $branch->delete(); return response(null,204); }

    // statistics endpoints (basic)
    public function statistics(Branch $branch)
    {
        $now = now();

        $dailySales = $branch->sales()
            ->whereDate('created_at', $now->toDateString())
            ->sum('total');

        $weeklySales = $branch->sales()
            ->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->sum('total');

        $monthlySales = $branch->sales()
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('total');

        $yearlySales = $branch->sales()
            ->whereYear('created_at', $now->year)
            ->sum('total');

        return response()->json([
            'daily_sales'   => $dailySales,
            'weekly_sales'  => $weeklySales,
            'monthly_sales' => $monthlySales,
            'yearly_sales'  => $yearlySales,
        ]);
    }

}
