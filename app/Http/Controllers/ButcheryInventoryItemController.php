<?php

namespace App\Http\Controllers;

use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ButcheryInventoryItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $branch = $request->query('branch');
        
        $items = InventoryItem::where('branch_id', $branch)->where('isButchery', true)->get()->toArray();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'unit' => 'required|string',
            'stock' => 'required|numeric',
            'image' => 'nullable|string',
            'is_butchery' => 'required|boolean',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $item = InventoryItem::create($validated);
        return new InventoryItemResource($item);
    }

    public function show($id)
    {
        $item = InventoryItem::findOrFail($id);
        return new InventoryItemResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric',
            'unit' => 'sometimes|string',
            'stock' => 'sometimes|numeric',
            'image' => 'nullable|string',
            'is_butchery' => 'sometimes|boolean',
            'branch_id' => 'sometimes|exists:branches,id',
        ]);

        $item->update($validated);
        return new InventoryItemResource($item);
    }

    public function destroy($id)
    {
        $item = InventoryItem::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
?>
