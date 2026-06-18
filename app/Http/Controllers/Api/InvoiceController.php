<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\InvoiceSentMail;
use Illuminate\Support\Facades\Mail;
use App\Models\InvoicePayment;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['quotation', 'proposal', 'creator'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $invoices,
        ]);
    }

    public function show($id)
    {
        $invoice = Invoice::with([
            'quotation',
            'proposal',
            'items',
            'payments',
            'creator',
        ])->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $invoice,
        ]);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'organization_name' => 'nullable|string|max:255',
            'project_name' => 'required|string|max:255',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'gst_percentage' => 'nullable|numeric|min:0|max:100',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            'items' => 'nullable|array',
            'items.*.item_name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($invoice, $validated) {
            $invoice->update([
                'client_name' => $validated['client_name'],
                'organization_name' => $validated['organization_name'] ?? null,
                'project_name' => $validated['project_name'],
                'invoice_date' => $validated['invoice_date'] ?? $invoice->invoice_date,
                'due_date' => $validated['due_date'] ?? $invoice->due_date,
                'gst_percentage' => $validated['gst_percentage'] ?? $invoice->gst_percentage,
                'paid_amount' => $validated['paid_amount'] ?? $invoice->paid_amount,
                'notes' => $validated['notes'] ?? null,
            ]);

            if (isset($validated['items'])) {
                InvoiceItem::where('invoice_id', $invoice->id)->delete();

                $subtotal = 0;

                foreach ($validated['items'] as $index => $item) {
                    $quantity = $item['quantity'] ?? 1;
                    $unitPrice = $item['unit_price'] ?? 0;
                    $total = $quantity * $unitPrice;
                    $subtotal += $total;

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_name' => $item['item_name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $total,
                        'sort_order' => $index + 1,
                    ]);
                }

                $gstAmount = ($subtotal * $invoice->gst_percentage) / 100;
                $grandTotal = $subtotal + $gstAmount;
                $paidAmount = $invoice->paid_amount ?? 0;
                $balanceAmount = max($grandTotal - $paidAmount, 0);

                $status = $invoice->status;

                if ($paidAmount <= 0) {
                    $status = 'sent';
                } elseif ($paidAmount < $grandTotal) {
                    $status = 'partially_paid';
                } else {
                    $status = 'paid';
                }

                $invoice->update([
                    'subtotal' => $subtotal,
                    'gst_amount' => $gstAmount,
                    'grand_total' => $grandTotal,
                    'balance_amount' => $balanceAmount,
                    'status' => $status,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Invoice updated successfully.',
                'data' => $invoice->fresh(['items']),
            ]);
        });
    }

    public function send($id)
    {
        $invoice = Invoice::with(['items', 'quotation', 'proposal', 'creator'])
            ->findOrFail($id);

        $clientEmail = $invoice->client_email
            ?? $invoice->quotation?->client_email
            ?? $invoice->proposal?->client_email;

        $clientPhone = $invoice->client_phone
            ?? $invoice->quotation?->client_phone
            ?? $invoice->proposal?->client_phone;

        if (!$clientEmail) {
            return response()->json([
                'status' => false,
                'message' => 'Client email is required before sending invoice.',
            ], 422);
        }

        $pdf = Pdf::loadView('pdf.invoices.invoice', [
            'invoice' => $invoice,
        ])->setPaper('a4');

        $fileName = $invoice->invoice_no . '.pdf';
        $folderPath = storage_path('app/public/invoices');

        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $pdfPath = $folderPath . DIRECTORY_SEPARATOR . $fileName;
        $pdf->save($pdfPath);

        $pdfUrl = url('storage/invoices/' . $fileName);

        Mail::to($clientEmail)
            ->queue(new InvoiceSentMail($invoice, $pdfPath));

        $invoice->update([
            'status' => $invoice->paid_amount > 0 ? $invoice->status : 'sent',
        ]);

        $whatsappMessage =
            "Dear {$invoice->client_name},\n\n" .
            "Greetings from Maviya IT Services.\n\n" .
            "We have shared the invoice for {$invoice->project_name} on your email.\n\n" .
            "You can also download the invoice PDF from the link below:\n" .
            "{$pdfUrl}\n\n" .
            "Invoice Amount: Rs. " . number_format($invoice->grand_total, 2) . "\n" .
            "Paid Amount: Rs. " . number_format($invoice->paid_amount, 2) . "\n" .
            "Balance Amount: Rs. " . number_format($invoice->balance_amount, 2) . "\n\n" .
            "Regards,\nMaviya IT Services\n+91 9648209795";

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
            'message' => 'Invoice sent successfully by email.',
            'data' => [
                'invoice' => $invoice->fresh(),
                'pdf_url' => $pdfUrl,
                'whatsapp_url' => $whatsappUrl,
                'whatsapp_message' => $whatsappMessage,
            ],
        ]);
    }

    public function markPaid(Request $request, $id)
    {
        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:1',
            'payment_date' => 'nullable|date',
            'payment_mode' => 'required|string|max:100',
            'reference_no' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $invoice = Invoice::with('payments')->findOrFail($id);

        return DB::transaction(function () use ($invoice, $validated) {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                'amount' => $validated['paid_amount'],
                'payment_mode' => $validated['payment_mode'],
                'reference_no' => $validated['reference_no'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'received_by' => auth()->id(),
            ]);

            $totalPaid = InvoicePayment::where('invoice_id', $invoice->id)->sum('amount');
            $balanceAmount = max($invoice->grand_total - $totalPaid, 0);

            if ($totalPaid <= 0) {
                $status = 'sent';
            } elseif ($totalPaid < $invoice->grand_total) {
                $status = 'partially_paid';
            } else {
                $status = 'paid';
            }

            $invoice->update([
                'paid_amount' => $totalPaid,
                'balance_amount' => $balanceAmount,
                'status' => $status,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment recorded successfully.',
                'data' => $invoice->fresh(['items', 'payments']),
            ]);
        });
    }

    public function cancel($id)
    {
        $invoice = Invoice::findOrFail($id);

        $invoice->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Invoice cancelled successfully.',
            'data' => $invoice->fresh(),
        ]);
    }

    public function generatePdf($id)
    {
        $invoice = Invoice::with(['items', 'creator'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.invoices.invoice', [
            'invoice' => $invoice,
            'settings' => $this->invoiceSettings(),
        ])->setPaper('a4');

        return $pdf->download($invoice->invoice_no . '.pdf');
    }

    private function invoiceSettings(): array
    {
        return DB::table('settings')
            ->pluck('value', 'key')
            ->toArray();
    }
}
