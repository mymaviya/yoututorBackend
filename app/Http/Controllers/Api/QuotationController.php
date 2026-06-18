<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\QuotationSentMail;
use Illuminate\Support\Facades\Mail;

class QuotationController extends Controller
{
    public function index()
    {
        $quotations = Quotation::with(['proposal', 'creator'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $quotations,
        ]);
    }

    public function show($id)
    {
        $quotation = Quotation::with(['proposal', 'items', 'invoices', 'creator'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $quotation,
        ]);
    }

    public function update(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);

        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'organization_name' => 'nullable|string|max:255',
            'project_name' => 'required|string|max:255',
            'quotation_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'gst_percentage' => 'nullable|numeric|min:0|max:100',
            'terms' => 'nullable|string',

            'items' => 'nullable|array',
            'items.*.item_name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($quotation, $validated) {
            $quotation->update([
                'client_name' => $validated['client_name'],
                'organization_name' => $validated['organization_name'] ?? null,
                'project_name' => $validated['project_name'],
                'quotation_date' => $validated['quotation_date'] ?? $quotation->quotation_date,
                'valid_until' => $validated['valid_until'] ?? $quotation->valid_until,
                'gst_percentage' => $validated['gst_percentage'] ?? $quotation->gst_percentage,
                'terms' => $validated['terms'] ?? null,
            ]);

            if (isset($validated['items'])) {
                QuotationItem::where('quotation_id', $quotation->id)->delete();

                $subtotal = 0;

                foreach ($validated['items'] as $index => $item) {
                    $quantity = $item['quantity'] ?? 1;
                    $unitPrice = $item['unit_price'] ?? 0;
                    $total = $quantity * $unitPrice;
                    $subtotal += $total;

                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'item_name' => $item['item_name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $total,
                        'sort_order' => $index + 1,
                    ]);
                }

                $gstAmount = ($subtotal * $quotation->gst_percentage) / 100;

                $quotation->update([
                    'subtotal' => $subtotal,
                    'gst_amount' => $gstAmount,
                    'grand_total' => $subtotal + $gstAmount,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Quotation updated successfully.',
                'data' => $quotation->fresh(['items']),
            ]);
        });
    }

    public function send($id)
    {
        $quotation = Quotation::with(['items', 'proposal', 'creator'])
            ->findOrFail($id);

        $clientEmail = $quotation->client_email ?? $quotation->proposal?->client_email;
        $clientPhone = $quotation->client_phone ?? $quotation->proposal?->client_phone;

        if (!$clientEmail) {
            return response()->json([
                'status' => false,
                'message' => 'Client email is required before sending quotation.',
            ], 422);
        }

        $pdf = Pdf::loadView('pdf.quotations.quotation', [
            'quotation' => $quotation,
        ])->setPaper('a4');

        $fileName = $quotation->quotation_no . '.pdf';
        $folderPath = storage_path('app/public/quotations');

        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $pdfPath = $folderPath . DIRECTORY_SEPARATOR . $fileName;
        $pdf->save($pdfPath);

        $pdfUrl = url('storage/quotations/' . $fileName);

        Mail::to($clientEmail)
            ->queue(new QuotationSentMail($quotation, $pdfPath));

        $quotation->update([
            'status' => 'sent',
        ]);

        $whatsappMessage =
            "Dear {$quotation->client_name},\n\n" .
            "Greetings from Maviya IT Services.\n\n" .
            "We have shared the quotation for {$quotation->project_name} on your email.\n\n" .
            "You can also download the quotation PDF from the link below:\n" .
            "{$pdfUrl}\n\n" .
            "Kindly review it and share your approval or required changes.\n\n" .
            "Regards,\n" .
            "Maviya IT Services\n" .
            "+91 9648209795";

        $whatsappUrl = null;

        if ($clientPhone) {
            $mobile = preg_replace('/\D/', '', $clientPhone);

            if (strlen($mobile) === 10) {
                $mobile = '91' . $mobile;
            }

            if (strlen($mobile) >= 11 && strlen($mobile) <= 15) {
                $whatsappUrl = 'https://wa.me/' . $mobile . '?text=' . rawurlencode($whatsappMessage);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Quotation sent successfully by email.',
            'data' => [
                'quotation' => $quotation->fresh(),
                'pdf_url' => $pdfUrl,
                'whatsapp_url' => $whatsappUrl,
                'whatsapp_message' => $whatsappMessage,
            ],
        ]);
    }

    public function accept($id)
    {
        $quotation = Quotation::findOrFail($id);

        $quotation->update([
            'status' => 'accepted',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Quotation accepted successfully.',
            'data' => $quotation->fresh(),
        ]);
    }

    public function reject($id)
    {
        $quotation = Quotation::findOrFail($id);

        $quotation->update([
            'status' => 'rejected',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Quotation rejected successfully.',
            'data' => $quotation->fresh(),
        ]);
    }

    public function convertToInvoice($id)
    {
        $quotation = Quotation::with('items')->findOrFail($id);

        if ($quotation->status !== 'accepted') {
            return response()->json([
                'status' => false,
                'message' => 'Only accepted quotations can be converted to invoice.',
            ], 422);
        }

        $invoice = DB::transaction(function () use ($quotation) {
            $invoice = Invoice::create([
                'quotation_id' => $quotation->id,
                'proposal_id' => $quotation->proposal_id,
                'invoice_no' => $this->generateInvoiceNo(),
                'client_name' => $quotation->client_name,
                'organization_name' => $quotation->organization_name,
                'project_name' => $quotation->project_name,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'subtotal' => $quotation->subtotal,
                'gst_percentage' => $quotation->gst_percentage,
                'gst_amount' => $quotation->gst_amount,
                'grand_total' => $quotation->grand_total,
                'paid_amount' => 0,
                'balance_amount' => $quotation->grand_total,
                'status' => 'draft',
                'notes' => $quotation->terms,
                'created_by' => auth()->id(),
            ]);

            foreach ($quotation->items as $index => $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_name' => $item->item_name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                    'sort_order' => $index + 1,
                ]);
            }

            $quotation->update([
                'status' => 'converted_to_invoice',
            ]);

            return $invoice->load('items');
        });

        return response()->json([
            'status' => true,
            'message' => 'Quotation converted to invoice successfully.',
            'data' => $invoice,
        ]);
    }

    private function generateInvoiceNo(): string
    {
        $prefix = 'INV-' . date('Y') . '-';

        $last = Invoice::where('invoice_no', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $next = 1;

        if ($last) {
            $lastNumber = (int) str_replace($prefix, '', $last->invoice_no);
            $next = $lastNumber + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function generatePdf($id)
    {
        $quotation = Quotation::with(['items', 'creator'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.quotations.quotation', [
            'quotation' => $quotation,
        ])->setPaper('a4');

        return $pdf->download($quotation->quotation_no . '.pdf');
    }
}
