<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['name', 'description', 'price', 'stock', 'sold_count', 'category_id'];
    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'sold_count' => 'integer'
    ];

    // Relationship exists — but controller never uses with() to eager load it
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
