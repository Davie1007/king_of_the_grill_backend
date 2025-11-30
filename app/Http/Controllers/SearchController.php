<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\InventoryItem;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\Payment;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Owner') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = $request->query('q');
        if (empty($query)) {
            return response()->json([]);
        }

        $results = [];

        // Search Inventory Items
        $inventory = InventoryItem::where('name', 'like', "%$query%")
            ->orWhere('description', 'like', "%$query%") // If description field exists
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => 'inventory',
                    'details' => "Stock: {$item->stock} {$item->unit}",
                    'link' => "/inventory/{$item->id}",
                ];
            });
        $results = array_merge($results, $inventory->toArray());

        // Search Employees
        $employees = Employee::where('name', 'like', "%$query%")
            ->orWhere('idNumber', 'like', "%$query%")
            ->orWhere('position', 'like', "%$query%")
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'type' => 'employee',
                    'details' => "Position: {$employee->position}, Branch: {$employee->branch->name}",
                    'link' => "/employee/{$employee->id}",
                ];
            });
        $results = array_merge($results, $employees->toArray());

        // Search Branches
        $branches = Branch::where('name', 'like', "%$query%")
            ->get()
            ->map(function ($branch) {
                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'type' => 'branch',
                    'details' => "Type: {$branch->type}", // e.g., butchery, gas, drinks
                    'link' => "/branch/{$branch->id}",
                ];
            });
        $results = array_merge($results, $branches->toArray());

        // Search Sales
        $sales = Sale::where('mpesa_ref', 'like', "%$query%")
            ->orWhere('amount', 'like', "%$query%") // If amount is searchable
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'name' => "Sale #{$sale->id}",
                    'type' => 'sale',
                    'details' => "Amount: {$sale->amount} KES, Branch: {$sale->branch->name}",
                    'link' => "/sale/{$sale->id}",
                ];
            });
        $results = array_merge($results, $sales->toArray());

        // Search Expenses
        $expenses = Expense::where('description', 'like', "%$query%")
            ->orWhere('amount', 'like', "%$query%")
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'name' => "Expense #{$expense->id}",
                    'type' => 'expense',
                    'details' => "Amount: {$expense->amount} KES, Description: {$expense->description}",
                    'link' => "/expense/{$expense->id}",
                ];
            });
        $results = array_merge($results, $expenses->toArray());

        // Search Payments
        $payments = Payment::where('mpesa_ref', 'like', "%$query%")
            ->orWhere('amount', 'like', "%$query%")
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'name' => "Payment #{$payment->id}",
                    'type' => 'payment',
                    'details' => "Amount: {$payment->amount} KES, Ref: {$payment->mpesa_ref}",
                    'link' => "/payment/{$payment->id}",
                ];
            });
        $results = array_merge($results, $payments->toArray());

        return response()->json($results);
    }
}