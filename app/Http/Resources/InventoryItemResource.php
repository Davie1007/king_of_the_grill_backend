<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'unit' => $this->unit,
            'stock' => $this->stock,
            'image' => $this->image,
            'isButchery' => $this->is_butchery,
            'branch_id' => $this->branch_id,
        ];
    }
}
?>