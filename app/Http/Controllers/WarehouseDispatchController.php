<?php
namespace App\Http\Controllers;
use App\Models\WarehouseDispatch;

class WarehouseDispatchController extends Controller
{
    public function index(){ return response()->json(WarehouseDispatch::with(['warehouseItem','branch','performedBy'])->orderBy('created_at','desc')->get()); }
    public function show(WarehouseDispatch $warehouseDispatch){ return response()->json($warehouseDispatch); }
}
