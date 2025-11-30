<?php
namespace App\Http\Controllers;

use App\Models\WarehouseItem;
use Illuminate\Http\Request;

class WarehouseItemController extends Controller
{
    public function index(){ return response()->json(WarehouseItem::orderBy('name')->get()); }
    public function store(Request $r){ $w = WarehouseItem::create($r->all()); return response()->json($w,201); }
    public function show(WarehouseItem $warehouseItem){ return response()->json($warehouseItem); }
    public function update(Request $r, WarehouseItem $warehouseItem){ $warehouseItem->update($r->all()); return response()->json($warehouseItem); }
    public function destroy(WarehouseItem $warehouseItem){ $warehouseItem->delete(); return response(null,204); }

    // dispatch action
    public function dispatch(Request $r, WarehouseItem $warehouseItem)
    {
        $data = $r->validate([
            'branch'=>'required|exists:branches,id',
            'quantity'=>'required|numeric|min:0.01',
            'notes'=>'nullable|string'
        ]);
        $qty = (float)$data['quantity'];
        if ($warehouseItem->stock < $qty) {
            return response()->json(['error'=>'Insufficient stock'],400);
        }
        $warehouseItem->stock -= $qty;
        $warehouseItem->save();

        $dispatch = $warehouseItem->dispatches()->create([
            'branch_id'=>$data['branch'],
            'quantity'=>$qty,
            'notes'=>$data['notes'] ?? null,
            'performed_by_id' => $r->user()?->id
        ]);

        return response()->json(['item'=>$warehouseItem,'dispatch'=>$dispatch]);
    }
}
