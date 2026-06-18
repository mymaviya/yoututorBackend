<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemoEnquiry;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\Subscription;

class CrmDashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => [
                'demo_enquiries' => [
                    'total' => DemoEnquiry::count(),
                    'new' => DemoEnquiry::where('status', 'new')->count(),
                    'contacted' => DemoEnquiry::where('status', 'contacted')->count(),
                    'converted' => DemoEnquiry::where('status', 'converted')->count(),
                    'latest' => DemoEnquiry::latest()->take(5)->get(),
                ],

                'proposals' => [
                    'total' => Proposal::count(),
                    'draft' => Proposal::where('status', 'draft')->count(),
                    'sent' => Proposal::where('status', 'sent')->count(),
                    'approved' => Proposal::where('status', 'approved')->count(),
                    'rejected' => Proposal::where('status', 'rejected')->count(),
                    'latest' => Proposal::latest()->take(5)->get(),
                ],

                'quotations' => [
                    'total' => Quotation::count(),
                    'draft' => Quotation::where('status', 'draft')->count(),
                    'sent' => Quotation::where('status', 'sent')->count(),
                    'accepted' => Quotation::where('status', 'accepted')->count(),
                    'rejected' => Quotation::where('status', 'rejected')->count(),
                    'latest' => Quotation::latest()->take(5)->get(),
                ],

                'invoices' => [
                    'total' => Invoice::count(),
                    'draft' => Invoice::where('status', 'draft')->count(),
                    'sent' => Invoice::where('status', 'sent')->count(),
                    'paid' => Invoice::where('status', 'paid')->count(),
                    'partial' => Invoice::where('status', 'partially_paid')->count(),
                    'latest' => Invoice::latest()->take(5)->get(),
                ],

                'revenue' => [
                    'invoiced' => Invoice::sum('grand_total'),
                    'received' => Invoice::sum('paid_amount'),
                    'balance' => Invoice::sum('balance_amount'),
                ],

                'quick_stats' => [
                    'total_pipeline_value' => Proposal::sum('grand_total') + Quotation::sum('grand_total'),
                    'approved_proposal_value' => Proposal::where('status', 'approved')->sum('grand_total'),
                    'accepted_quotation_value' => Quotation::where('status', 'accepted')->sum('grand_total'),
                    'pending_invoice_value' => Invoice::whereIn('status', ['sent', 'partially_paid'])->sum('balance_amount'),
                ],

                'subscriptions' => [
                    'total' => Subscription::count(),
                    'trial' => Subscription::where('status', 'trial')->count(),
                    'active' => Subscription::where('status', 'active')->count(),
                    'expired' => Subscription::where('status', 'expired')->count(),
                    'cancelled' => Subscription::where('status', 'cancelled')->count(),
                    'pending_payment' => Subscription::where('status', 'pending_payment')->count(),
                    'expiring_soon' => Subscription::where('status', 'active')
                        ->whereDate('ends_at', '>=', now())
                        ->whereDate('ends_at', '<=', now()->addDays(7))
                        ->count(),
                ],
            ],
        ]);
    }
}
