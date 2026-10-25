<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryOrderItem extends Model
{
    protected $fillable = ['laundry_order_id', 'laundry_service_id', 'service_name', 'unit', 'quantity_units', 'unit_price_minor', 'line_total_minor'];
    protected function casts(): array { return ['quantity_units' => 'integer', 'unit_price_minor' => 'integer', 'line_total_minor' => 'integer']; }
    public function order() { return $this->belongsTo(LaundryOrder::class, 'laundry_order_id'); }
    public function service() { return $this->belongsTo(LaundryService::class, 'laundry_service_id'); }
}
