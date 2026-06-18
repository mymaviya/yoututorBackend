<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProposalTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProposalTemplateController extends Controller
{
    public function index()
    {
        $templates = ProposalTemplate::withCount('sections')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $templates,
        ]);
    }

    public function show($id)
    {
        $template = ProposalTemplate::with('sections')
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $template,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:proposal_templates,slug',
            'project_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',

            'sections' => 'nullable|array',
            'sections.*.title' => 'required_with:sections|string|max:255',
            'sections.*.section_key' => 'nullable|string|max:255',
            'sections.*.content' => 'nullable|string',
            'sections.*.sort_order' => 'nullable|integer|min:0',
            'sections.*.is_editable' => 'boolean',
        ]);

        return DB::transaction(function () use ($validated) {
            $template = ProposalTemplate::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? Str::slug($validated['name']),
                'project_type' => $validated['project_type'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            foreach (($validated['sections'] ?? []) as $index => $section) {
                $template->sections()->create([
                    'title' => $section['title'],
                    'section_key' => $section['section_key'] ?? Str::slug($section['title'], '_'),
                    'content' => $section['content'] ?? null,
                    'sort_order' => $section['sort_order'] ?? $index + 1,
                    'is_editable' => $section['is_editable'] ?? true,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Proposal template created successfully.',
                'data' => $template->load('sections'),
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $template = ProposalTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:proposal_templates,slug,' . $template->id,
            'project_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',

            'sections' => 'nullable|array',
            'sections.*.id' => 'nullable|exists:proposal_template_sections,id',
            'sections.*.title' => 'required_with:sections|string|max:255',
            'sections.*.section_key' => 'nullable|string|max:255',
            'sections.*.content' => 'nullable|string',
            'sections.*.sort_order' => 'nullable|integer|min:0',
            'sections.*.is_editable' => 'boolean',
        ]);

        return DB::transaction(function () use ($template, $validated) {
            $template->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? Str::slug($validated['name']),
                'project_type' => $validated['project_type'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            $keepIds = [];

            foreach (($validated['sections'] ?? []) as $index => $section) {
                $sectionModel = $template->sections()->updateOrCreate(
                    ['id' => $section['id'] ?? null],
                    [
                        'title' => $section['title'],
                        'section_key' => $section['section_key'] ?? Str::slug($section['title'], '_'),
                        'content' => $section['content'] ?? null,
                        'sort_order' => $section['sort_order'] ?? $index + 1,
                        'is_editable' => $section['is_editable'] ?? true,
                    ]
                );

                $keepIds[] = $sectionModel->id;
            }

            $template->sections()
                ->whereNotIn('id', $keepIds)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => 'Proposal template updated successfully.',
                'data' => $template->fresh('sections'),
            ]);
        });
    }

    public function destroy($id)
    {
        $template = ProposalTemplate::findOrFail($id);

        if ($template->proposals()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'This template is already used in proposals and cannot be deleted.',
            ], 422);
        }

        $template->delete();

        return response()->json([
            'status' => true,
            'message' => 'Proposal template deleted successfully.',
        ]);
    }
}
