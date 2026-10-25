<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryService extends Model
{
    protected $fillable = ['name', 'description', 'unit', 'price_minor', 'active'];
    protected function casts(): array { return ['price_minor' => 'integer', 'active' => 'boolean']; }
    public function items() { return $this->hasMany(LaundryOrderItem::class); }
}
