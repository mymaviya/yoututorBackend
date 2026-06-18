<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ProposalSection;
use App\Models\ProposalTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ProposalVersionService;
use App\Models\ServiceCatalog;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\ProposalSentMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ProposalController extends Controller
{
    public function index()
    {
        $proposals = Proposal::with(['template', 'creator'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $proposals,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proposal_template_id' => 'nullable|exists:proposal_templates,id',
            'service_catalog_id' => 'nullable|exists:service_catalogs,id',

            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:30',
            'organization_name' => 'nullable|string|max:255',
            'project_name' => 'required|string|max:255',
            'project_type' => 'nullable|string|max:100',
            'timeline_days' => 'nullable|integer|min:1',
            'gst_applicable' => 'boolean',
            'gst_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_terms' => 'nullable|string',
            'notes' => 'nullable|string',

            'items' => 'nullable|array',
            'items.*.module_name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $template = null;

            if (!empty($validated['proposal_template_id'])) {
                $template = ProposalTemplate::with('sections')
                    ->find($validated['proposal_template_id']);
            }

            $items = $validated['items'] ?? [];

            if (empty($items) && !empty($validated['service_catalog_id'])) {
                $catalog = ServiceCatalog::with('items')
                    ->find($validated['service_catalog_id']);

                if ($catalog) {
                    $items = $catalog->items->map(function ($item) {
                        return [
                            'module_name' => $item->module_name,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                        ];
                    })->toArray();
                }
            }

            $subtotal = collect($items)->sum(function ($item) {
                $quantity = $item['quantity'] ?? 1;
                $unitPrice = $item['unit_price'] ?? 0;

                return $quantity * $unitPrice;
            });

            $gstApplicable = $validated['gst_applicable'] ?? true;
            $gstPercentage = $validated['gst_percentage'] ?? 18;

            $gstAmount = $gstApplicable ? ($subtotal * $gstPercentage / 100) : 0;
            $grandTotal = $subtotal + $gstAmount;

            $proposal = Proposal::create([
                'proposal_template_id' => $validated['proposal_template_id'] ?? null,
                'proposal_no' => $this->generateProposalNo(),
                'client_name' => $validated['client_name'],
                'client_email' => $validated['client_email'] ?? null,
                'client_phone' => $validated['client_phone'] ?? null,
                'organization_name' => $validated['organization_name'] ?? null,
                'project_name' => $validated['project_name'],
                'project_type' => $validated['project_type'] ?? $template?->project_type,
                'timeline_days' => $validated['timeline_days'] ?? null,
                'gst_applicable' => $gstApplicable,
                'gst_percentage' => $gstPercentage,
                'subtotal' => $subtotal,
                'gst_amount' => $gstAmount,
                'grand_total' => $grandTotal,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            if ($template) {
                foreach ($template->sections as $section) {
                    ProposalSection::create([
                        'proposal_id' => $proposal->id,
                        'title' => $section->title,
                        'section_key' => $section->section_key,
                        'content' => $section->content,
                        'sort_order' => $section->sort_order,
                        'is_visible' => true,
                    ]);
                }
            }

            foreach ($items as $index => $item) {
                $quantity = $item['quantity'] ?? 1;
                $unitPrice = $item['unit_price'] ?? 0;

                ProposalItem::create([
                    'proposal_id' => $proposal->id,
                    'module_name' => $item['module_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $quantity * $unitPrice,
                    'sort_order' => $index + 1,
                ]);
            }

            $proposal->load(['template', 'sections', 'items']);

            return response()->json([
                'status' => true,
                'message' => 'Proposal created successfully.',
                'data' => $proposal,
            ], 201);
        });
    }

    public function show($id)
    {
        $proposal = Proposal::with([
            'template',
            'sections',
            'items',
            'versions',
            'creator',
        ])->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $proposal,
        ]);
    }

    private function generateProposalNo(): string
    {
        $prefix = 'PROP-' . date('Y') . '-';

        $last = Proposal::where('proposal_no', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $next = 1;

        if ($last) {
            $lastNumber = (int) str_replace($prefix, '', $last->proposal_no);
            $next = $lastNumber + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function update(Request $request, $id)
    {
        $proposal = Proposal::with([
            'sections',
            'items'
        ])->findOrFail($id);

        ProposalVersionService::createSnapshot($proposal);

        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:30',
            'organization_name' => 'nullable|string|max:255',
            'project_name' => 'required|string|max:255',
            'timeline_days' => 'nullable|integer',
            'payment_terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'gst_percentage' => 'nullable|numeric',
        ]);

        $proposal->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Proposal updated successfully.',
            'data' => $proposal->fresh(),
        ]);
    }

    public function updateSections(Request $request, $id)
    {
        $proposal = Proposal::findOrFail($id);

        foreach ($request->sections as $section) {

            ProposalSection::where('id', $section['id'])
                ->where('proposal_id', $proposal->id)
                ->update([
                    'title' => $section['title'],
                    'content' => $section['content'],
                    'is_visible' => $section['is_visible'] ?? true,
                ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Sections updated successfully.'
        ]);
    }

    public function updateItems(Request $request, $id)
    {
        $proposal = Proposal::findOrFail($id);

        ProposalItem::where('proposal_id', $proposal->id)->delete();

        $subtotal = 0;

        foreach ($request->items as $index => $item) {

            $total =
                ($item['quantity'] ?? 1)
                *
                ($item['unit_price'] ?? 0);

            $subtotal += $total;

            ProposalItem::create([
                'proposal_id' => $proposal->id,
                'module_name' => $item['module_name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? 0,
                'total' => $total,
                'sort_order' => $index + 1,
            ]);
        }

        $gstAmount =
            ($subtotal * $proposal->gst_percentage) / 100;

        $proposal->update([
            'subtotal' => $subtotal,
            'gst_amount' => $gstAmount,
            'grand_total' => $subtotal + $gstAmount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Deliverables updated successfully.',
        ]);
    }

    public function versions($id)
    {
        $proposal = Proposal::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $proposal->versions()
                ->latest()
                ->get()
        ]);
    }

    public function send($id)
    {
        $proposal = Proposal::with(['sections', 'items', 'creator'])
            ->findOrFail($id);

        if (!$proposal->client_email) {
            return response()->json([
                'status' => false,
                'message' => 'Client email is required before sending proposal.',
            ], 422);
        }

        $pdf = Pdf::loadView('pdf.proposals.proposal', [
            'proposal' => $proposal,
        ])->setPaper('a4');

        $fileName = $proposal->proposal_no . '.pdf';
        $folderPath = storage_path('app/public/proposals');

        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $pdfPath = $folderPath . '/' . $fileName;

        $pdf->save($pdfPath);

        $pdfUrl = url('storage/proposals/' . $fileName);

        Mail::to($proposal->client_email)
            ->queue(new ProposalSentMail($proposal, $pdfPath));

        $proposal->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $whatsappMessage =
            "Dear {$proposal->client_name},\n\n" .
            "Greetings from Maviya IT Services.\n\n" .
            "We have shared the project proposal for {$proposal->project_name} on your email.\n\n" .
            "You can also download the proposal PDF from the link below:\n" .
            "{$pdfUrl}\n\n" .
            "Kindly review it and share your approval or required changes.\n\n" .
            "Regards,\n" .
            "Maviya IT Services\n" .
            "+91 9648209795";

        $whatsappUrl = null;

        if ($proposal->client_phone) {
            $mobile = preg_replace('/\D/', '', $proposal->client_phone);

            if (strlen($mobile) === 10) {
                $mobile = '91' . $mobile;
            }

            $whatsappUrl = 'https://wa.me/' . $mobile . '?text=' . urlencode($whatsappMessage);
        }

        return response()->json([
            'status' => true,
            'message' => 'Proposal sent successfully by email.',
            'data' => [
                'proposal' => $proposal->fresh(),
                'pdf_url' => $pdfUrl,
                'whatsapp_url' => $whatsappUrl,
                'whatsapp_message' => $whatsappMessage,
            ],
        ]);
    }

    public function approve($id)
    {
        $proposal = Proposal::findOrFail($id);

        $proposal->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Proposal approved successfully.',
            'data' => $proposal->fresh(),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $proposal = Proposal::findOrFail($id);

        $proposal->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'notes' => $request->notes ?? $proposal->notes,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Proposal rejected successfully.',
            'data' => $proposal->fresh(),
        ]);
    }

    public function requestChanges(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string',
        ]);

        $proposal = Proposal::findOrFail($id);

        $proposal->update([
            'status' => 'change_requested',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Change request saved successfully.',
            'data' => $proposal->fresh(),
        ]);
    }

    public function convertToQuotation($id)
    {
        $proposal = Proposal::with('items')->findOrFail($id);

        if ($proposal->status !== 'approved') {
            return response()->json([
                'status' => false,
                'message' => 'Only approved proposals can be converted to quotation.',
            ], 422);
        }

        $quotation = DB::transaction(function () use ($proposal) {
            $quotation = Quotation::create([
                'proposal_id' => $proposal->id,
                'quotation_no' => $this->generateQuotationNo(),
                'client_name' => $proposal->client_name,
                'organization_name' => $proposal->organization_name,
                'project_name' => $proposal->project_name,
                'quotation_date' => now()->toDateString(),
                'valid_until' => now()->addDays(15)->toDateString(),
                'subtotal' => $proposal->subtotal,
                'gst_percentage' => $proposal->gst_percentage,
                'gst_amount' => $proposal->gst_amount,
                'grand_total' => $proposal->grand_total,
                'status' => 'draft',
                'terms' => $proposal->payment_terms,
                'created_by' => auth()->id(),
            ]);

            foreach ($proposal->items as $index => $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_name' => $item->module_name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                    'sort_order' => $index + 1,
                ]);
            }

            $proposal->update([
                'status' => 'converted_to_quotation',
            ]);

            return $quotation->load('items');
        });

        return response()->json([
            'status' => true,
            'message' => 'Proposal converted to quotation successfully.',
            'data' => $quotation,
        ]);
    }

    private function generateQuotationNo(): string
    {
        $prefix = 'QUO-' . date('Y') . '-';

        $last = Quotation::where('quotation_no', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $next = 1;

        if ($last) {
            $lastNumber = (int) str_replace($prefix, '', $last->quotation_no);
            $next = $lastNumber + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function generatePdf($id)
    {
        $proposal = Proposal::with(['sections', 'items', 'creator'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.proposals.proposal', [
            'proposal' => $proposal,
        ])->setPaper('a4');

        return $pdf->download($proposal->proposal_no . '.pdf');
    }

    public function destroy($id)
    {
        $proposal = Proposal::findOrFail($id);

        if (in_array($proposal->status, ['approved', 'converted_to_quotation'])) {
            return response()->json([
                'status' => false,
                'message' => 'Approved or converted proposals cannot be deleted.',
            ], 422);
        }

        $proposal->delete();

        return response()->json([
            'status' => true,
            'message' => 'Proposal deleted successfully.',
        ]);
    }
}
