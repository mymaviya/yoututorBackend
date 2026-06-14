<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidebarMenu extends Model
{
    protected $fillable = [
        'parent_id',
        'title',
        'icon',
        'route',
        'route_name',
        'group_name',
        'parent_menu',
        'permission_slug',
        'role_slug',
        'badge',
        'badge_color',
        'sort_order',
        'show_in_sidebar',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(SidebarMenu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(SidebarMenu::class, 'parent_id')->orderBy('sort_order');
    }
}
