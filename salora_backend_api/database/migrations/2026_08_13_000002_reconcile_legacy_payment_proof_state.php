<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('provider_service_requests') || !Schema::hasTable('invoices') || !Schema::hasTable('payment_proofs')) {
            return;
        }

        DB::table('provider_service_requests')
            ->orderBy('id')
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    $invoice = !empty($request->invoice_id)
                        ? DB::table('invoices')->where('id', $request->invoice_id)->first()
                        : null;

                    $invoice ??= DB::table('invoices')
                        ->where('source_type', 'provider_service')
                        ->where('source_id', $request->id)
                        ->orderByDesc('id')
                        ->first();

                    if (!$invoice) {
                        continue;
                    }

                    $proof = DB::table('payment_proofs')
                        ->where('invoice_id', $invoice->id)
                        ->orderByDesc('id')
                        ->first();

                    $invoiceStatus = strtolower((string) ($invoice->status ?? 'unpaid'));
                    $proofStatus = strtolower((string) ($proof->status ?? ''));

                    $paymentStatus = match (true) {
                        $invoiceStatus === 'paid', $proofStatus === 'approved' => 'approved',
                        $proofStatus === 'pending', $invoiceStatus === 'proof_uploaded' => 'proof_uploaded',
                        $proofStatus === 'rejected' => 'rejected',
                        default => strtolower((string) ($request->payment_status ?? 'unpaid')),
                    };

                    $updates = [
                        'payment_status' => $paymentStatus,
                        'updated_at' => now(),
                    ];

                    if (Schema::hasColumn('provider_service_requests', 'invoice_id')) {
                        $updates['invoice_id'] = $invoice->id;
                    }

                    if (Schema::hasColumn('provider_service_requests', 'invoice_number')) {
                        $updates['invoice_number'] = $invoice->invoice_number ?? $request->invoice_number ?? null;
                    }
                    if ($proof) {
                        if (Schema::hasColumn('provider_service_requests', 'payment_method')) {
                            $updates['payment_method'] = $proof->payment_method ?? null;
                        }
                        if (Schema::hasColumn('provider_service_requests', 'payment_proof_path')) {
                            $updates['payment_proof_path'] = $proof->image_url ?? null;
                        }
                        if (Schema::hasColumn('provider_service_requests', 'payment_uploaded_at')) {
                            $updates['payment_uploaded_at'] = $proof->uploaded_at ?? $proof->created_at ?? null;
                        }
                        if (Schema::hasColumn('provider_service_requests', 'payment_reviewed_at')) {
                            $updates['payment_reviewed_at'] = $proof->reviewed_at ?? null;
                        }
                        if (Schema::hasColumn('provider_service_requests', 'payment_rejection_reason')) {
                            $updates['payment_rejection_reason'] = $proof->rejection_reason ?? null;
                        }
                    }

                    if (Schema::hasColumn('provider_service_requests', 'payment_deadline_at')) {
                        $updates['payment_deadline_at'] = null;
                    }

                    DB::table('provider_service_requests')->where('id', $request->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Reconciliation only. Never roll payment state backwards.
    }
};
