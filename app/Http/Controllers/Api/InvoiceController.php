<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Invoice list (table view)
     */
    public function index()
    {
        $invoices = Invoice::with(['lead:id,business_name'])
            ->select([
                'id',
                'invoice_date',
                'lead_id',
                'grand_total',
                'type',
                'status',
                'description',
            ])
            ->latest()
            ->get();

        return response()->json([
            'data' => $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'date' => $invoice->invoice_date?->format('d/m/Y'),
                    'client' => $invoice->lead?->business_name,
                    'amount' => $invoice->grand_total,
                    'type' => ucfirst($invoice->type),
                    'description' => $invoice->description,
                    'status' => $invoice->status ?? 'draft',
                ];
            })
        ]);
    }

    /**
     * Store new invoice
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:export,proforma,up,non_up',
            'lead_id' => 'required|exists:leads,id',
            'bank_detail_id' => 'required|exists:bank_details,id',

            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',

            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        $invoice = DB::transaction(function () use ($validated) {

            $invoice = Invoice::create([
                'invoice_no' => 'INV-' . now()->format('YmdHis') . '-' . random_int(100, 999),
                'invoice_date' => now(),
                'type' => $validated['type'],
                'lead_id' => $validated['lead_id'],
                'bank_detail_id' => $validated['bank_detail_id'],
                'cgst' => $validated['cgst'] ?? 0,
                'sgst' => $validated['sgst'] ?? 0,
                'status' => 'draft',
            ]);

            $subTotal = 0;

            foreach ($validated['items'] as $item) {
                $invoice->items()->create($item);
                $subTotal += $item['amount'];
            }

            $invoice->update([
                'sub_total' => $subTotal,
                'grand_total' => $subTotal + $invoice->cgst + $invoice->sgst,
            ]);

            return $invoice;
        });

        return response()->json([
            'message' => 'Invoice created successfully',
            'data' => $invoice->load(['items', 'lead', 'bankDetail'])
        ], 201);
    }

    /**
     * Show single invoice (detail view)
     */
    public function show($id)
    {
        return response()->json([
            'data' => Invoice::with([
                'lead',
                'bankDetail',
                'items'
            ])->findOrFail($id)
        ]);
    }

    /**
     * Delete invoice
     */
    public function destroy($id)
    {
        Invoice::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully'
        ]);
    }
}
