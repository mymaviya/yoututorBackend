<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SidebarMenu;
use Illuminate\Http\Request;


class SidebarMenuController extends Controller
{


    public function index()
    {
        return SidebarMenu::orderBy('sort_order')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'route_name' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'permission_slug' => 'nullable|string|max:255',
            'role_slug' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $menu = SidebarMenu::create($data);

        return response()->json([
            'message' => 'Sidebar menu created successfully',
            'data' => $menu,
        ]);
    }

    public function show(SidebarMenu $sidebarMenu)
    {
        return $sidebarMenu;
    }

    public function update(Request $request, SidebarMenu $sidebarMenu)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'route_name' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'permission_slug' => 'nullable|string|max:255',
            'role_slug' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $sidebarMenu->update($data);

        return response()->json([
            'message' => 'Sidebar menu updated successfully',
            'data' => $sidebarMenu,
        ]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'menus' => 'required|array',
            'menus.*.id' => 'required|exists:sidebar_menus,id',
            'menus.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->menus as $menu) {
            SidebarMenu::where('id', $menu['id'])->update([
                'sort_order' => $menu['sort_order'],
            ]);
        }

        return response()->json([
            'message' => 'Sidebar order updated successfully',
        ]);
    }

    public function destroy(SidebarMenu $sidebarMenu)
    {
        $sidebarMenu->delete();

        return response()->json([
            'message' => 'Sidebar menu deleted successfully',
        ]);
    }
}
