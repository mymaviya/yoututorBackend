<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCatalog extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'project_type',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ServiceCatalogItem::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}