<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Invoice;
use App\Services\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;

class InvoiceController extends Controller
{
    public function __construct(private EmailService $emailService) {}

    public function store(Application $application): RedirectResponse
    {
        $validated = Request::validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'in:scaling_fee,monthly,other'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = $application->invoices()->create([
            'invoice_number' => 'INV-' . strtoupper(uniqid()),
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'due_date' => $validated['due_date'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'draft',
        ]);

        return Redirect::back()->with('success', 'Invoice created.');
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        try {
            $this->emailService->sendInvoice($invoice);

            $invoice->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $invoice->application->status->transitionTo('invoice_sent', 'Scaling fee invoice sent');

            return Redirect::back()->with('success', 'Invoice sent successfully.');
        } catch (\Exception $e) {
            return Redirect::back()->with('error', 'Failed to send invoice: ' . $e->getMessage());
        }
    }

    public function markAsPaid(Invoice $invoice): RedirectResponse
    {
        $validated = Request::validate([
            'payment_method' => ['required', 'string', 'max:50'],
        ]);

        $invoice->markAsPaid($validated['payment_method']);

        return Redirect::back()->with('success', 'Invoice marked as paid.');
    }
}