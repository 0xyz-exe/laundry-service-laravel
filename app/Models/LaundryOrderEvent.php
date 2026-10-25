<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryOrderEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['laundry_order_id', 'actor_user_id', 'event_type', 'from_value', 'to_value', 'note', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function order() { return $this->belongsTo(LaundryOrder::class, 'laundry_order_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
