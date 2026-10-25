<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryOrder extends Model
{
    protected $fillable = ['reference', 'customer_id', 'assigned_staff_id', 'pickup_at', 'delivery_at', 'pickup_address', 'customer_note', 'status', 'payment_method', 'payment_status', 'total_minor'];
    protected function casts(): array { return ['pickup_at' => 'datetime', 'delivery_at' => 'datetime', 'total_minor' => 'integer']; }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function staff() { return $this->belongsTo(User::class, 'assigned_staff_id'); }
    public function items() { return $this->hasMany(LaundryOrderItem::class); }
    public function events() { return $this->hasMany(LaundryOrderEvent::class); }
}
