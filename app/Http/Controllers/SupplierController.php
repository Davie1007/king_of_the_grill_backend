<?php
namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(){ return response()->json(Supplier::orderBy('created_at','desc')->get()); }
    public function store(Request $r){ $s = Supplier::create($r->all()); return response()->json($s,201); }
    public function show(Supplier $supplier){ return response()->json($supplier); }
    public function update(Request $r, Supplier $supplier){ $supplier->update($r->all()); return response()->json($supplier); }
    public function destroy(Supplier $supplier){ $supplier->delete(); return response(null,204); }
}
