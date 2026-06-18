<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCatalogItem extends Model
{
    protected $fillable = [
        'service_catalog_id',
        'module_name',
        'description',
        'quantity',
        'unit_price',
        'total',
        'timeline_days',
        'is_optional',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'is_optional' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function catalog()
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_catalog_id');
    }
}