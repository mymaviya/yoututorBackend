<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceCatalog;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    public function index()
    {
        $catalogs = ServiceCatalog::withCount('items')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $catalogs,
        ]);
    }

    public function show($id)
    {
        $catalog = ServiceCatalog::with('items')
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $catalog,
        ]);
    }
}