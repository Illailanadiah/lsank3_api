<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankApplication;
use App\Models\LsankInvoice;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('year');
        $applicationType = $request->query('application_type');
        $search = trim((string) $request->query('search', ''));

        $query = LsankInvoice::query()
            ->with([
                'application',
                'payment',
            ])
            ->whereNotIn('status', [
                'cancelled',
                'void',
            ]);

        if (!empty($year)) {
            $query->whereYear('invoice_date', $year);
        }

        $invoices = $query
            ->orderByDesc('invoice_date')
            ->orderByDesc('invoice_id')
            ->get();

        $statements = [];

        foreach ($invoices as $invoice) {
            $application = $invoice->application;

            if (!$application) {
                continue;
            }

            $sourceType = $this->resolveApplicationType($application);

            if (
                !empty($applicationType) &&
                $applicationType !== 'all' &&
                $sourceType !== $applicationType
            ) {
                continue;
            }

            $invoiceDate = $invoice->invoice_date
                ? \Carbon\Carbon::parse($invoice->invoice_date)
                : null;

            if (!$invoiceDate) {
                continue;
            }

            $statementYear = (int) $invoiceDate->year;

            $key =
                $application->application_id .
                '-' .
                $sourceType .
                '-' .
                $statementYear;

            if (!isset($statements[$key])) {
                $applicationNo =
                    $application->application_ref_no
                    ?? $application->application_no
                    ?? $application->file_no
                    ?? '-';

                $companyName =
                    $application->company_name
                    ?? $application->business_name
                    ?? $application->organisation_name
                    ?? $application->organization_name
                    ?? '-';

                $applicantName =
                    $application->applicant_name
                    ?? $application->applicant_full_name
                    ?? $application->name
                    ?? '-';

                $licenseNo =
                    $application->license_no
                    ?? $application->license_number
                    ?? $application->licence_no
                    ?? $application->licence_number
                    ?? $applicationNo;

                $statements[$key] = [
                    'application_id' =>
                    (string) $application->application_id,

                    'application_no' =>
                    (string) $applicationNo,

                    'file_no' =>
                    (string) $applicationNo,

                    'company_name' =>
                    (string) $companyName,

                    'applicant_name' =>
                    (string) $applicantName,

                    'license_no' =>
                    (string) $licenseNo,

                    'application_type' =>
                    $sourceType,

                    'license_type' =>
                    $sourceType === 'effluent'
                        ? 'Efluen'
                        : 'Badan Perairan',

                    'year' =>
                    $statementYear,

                    'total_debit' =>
                    0.00,

                    'total_credit' =>
                    0.00,

                    'balance' =>
                    0.00,

                    'invoice_count' =>
                    0,

                    'paid_invoice_count' =>
                    0,

                    'latest_date' =>
                    $invoiceDate->toDateString(),
                ];
            }

            $amount = (float) (
                $invoice->total_amount
                ?? $invoice->amount
                ?? $invoice->invoice_amount
                ?? 0
            );

            $statements[$key]['total_debit'] += $amount;

            $statements[$key]['invoice_count']++;

            if ($this->invoiceIsPaid($invoice)) {
                $statements[$key]['total_credit'] += $amount;

                $statements[$key]['paid_invoice_count']++;
            }

            if (
                $invoiceDate->greaterThan(
                    \Carbon\Carbon::parse(
                        $statements[$key]['latest_date']
                    )
                )
            ) {
                $statements[$key]['latest_date'] =
                    $invoiceDate->toDateString();
            }
        }

        $data = collect(array_values($statements))
            ->map(function ($item) use ($search) {
                $balance =
                    (float) $item['total_debit']
                    -
                    (float) $item['total_credit'];

                if ($balance < 0) {
                    $balance = 0;
                }

                $item['total_debit'] =
                    round((float) $item['total_debit'], 2);

                $item['total_credit'] =
                    round((float) $item['total_credit'], 2);

                $item['balance'] =
                    round($balance, 2);

                $item['status'] =
                    $balance <= 0.005
                    ? 'Selesai'
                    : 'Ada Tunggakan';

                return $item;
            })
            ->filter(function ($item) use ($search) {
                if ($search === '') {
                    return true;
                }

                $search = strtolower($search);

                return
                    str_contains(
                        strtolower($item['company_name']),
                        $search
                    )
                    ||
                    str_contains(
                        strtolower($item['applicant_name']),
                        $search
                    )
                    ||
                    str_contains(
                        strtolower($item['application_no']),
                        $search
                    )
                    ||
                    str_contains(
                        strtolower($item['license_no']),
                        $search
                    );
            })
            ->sortByDesc(function ($item) {
                return sprintf(
                    '%04d-%s',
                    $item['year'],
                    $item['latest_date']
                );
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function show(
        Request $request,
        $applicationId,
        $year
    ) {
        $application = LsankApplication::query()
            ->where('application_id', $applicationId)
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $invoices = LsankInvoice::query()
            ->with([
                'payment',
            ])
            ->where(
                'application_id',
                $applicationId
            )
            ->whereYear(
                'invoice_date',
                $year
            )
            ->whereNotIn('status', [
                'cancelled',
                'void',
            ])
            ->orderBy('invoice_date')
            ->orderBy('invoice_id')
            ->get();

        $transactions = [];

        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($invoices as $invoice) {
            $amount = (float) (
                $invoice->total_amount
                ?? $invoice->amount
                ?? $invoice->invoice_amount
                ?? 0
            );

            if ($amount <= 0) {
                continue;
            }

            $paymentType =
                $invoice->payment_type
                ?? $invoice->invoice_type
                ?? 'Bayaran';

            $invoiceNo =
                $invoice->invoice_no
                ?? $invoice->invoice_number
                ?? '-';

            $invoiceDate =
                $invoice->invoice_date
                ? \Carbon\Carbon::parse(
                    $invoice->invoice_date
                )
                : null;

            $totalDebit += $amount;

            $transactions[] = [
                'date' =>
                $invoiceDate?->toDateString(),

                'receipt_no' =>
                '-',

                'invoice_no' =>
                $invoiceNo,

                'description' =>
                $paymentType,

                'debit' =>
                round($amount, 2),

                'credit' =>
                0.00,

                'type' =>
                'debit',
            ];

            if ($this->invoiceIsPaid($invoice)) {
                $payment = $invoice->payment;

                $receiptNo =
                    $payment?->receipt_no
                    ?? '-';

                $paymentDate =
                    $payment?->paid_at
                    ?? $payment?->payment_date
                    ?? $payment?->created_at
                    ?? $invoice->invoice_date;

                $totalCredit += $amount;

                $transactions[] = [
                    'date' =>
                    $paymentDate
                        ? \Carbon\Carbon::parse(
                            $paymentDate
                        )->toDateString()
                        : null,

                    'receipt_no' =>
                    $receiptNo,

                    'invoice_no' =>
                    $invoiceNo,

                    'description' =>
                    'Bayaran ' . $paymentType,

                    'debit' =>
                    0.00,

                    'credit' =>
                    round($amount, 2),

                    'type' =>
                    'credit',
                ];
            }
        }

        usort(
            $transactions,
            function ($a, $b) {
                $aDate = $a['date'] ?? '';
                $bDate = $b['date'] ?? '';

                if ($aDate === $bDate) {
                    if (
                        $a['type'] === 'debit' &&
                        $b['type'] === 'credit'
                    ) {
                        return -1;
                    }

                    if (
                        $a['type'] === 'credit' &&
                        $b['type'] === 'debit'
                    ) {
                        return 1;
                    }

                    return 0;
                }

                return strcmp(
                    $aDate,
                    $bDate
                );
            }
        );

        $balance =
            $totalDebit - $totalCredit;

        if ($balance < 0) {
            $balance = 0;
        }

        $applicationNo =
            $application->application_ref_no
            ?? $application->application_no
            ?? $application->file_no
            ?? '-';

        $companyName =
            $application->company_name
            ?? $application->business_name
            ?? $application->organisation_name
            ?? $application->organization_name
            ?? '-';

        $applicantName =
            $application->applicant_name
            ?? $application->applicant_full_name
            ?? $application->name
            ?? '-';

        $licenseNo =
            $application->license_no
            ?? $application->license_number
            ?? $application->licence_no
            ?? $application->licence_number
            ?? $applicationNo;

        $sourceType =
            $this->resolveApplicationType(
                $application
            );

        return response()->json([
            'success' => true,

            'data' => [
                'application_id' =>
                (string) $application->application_id,

                'application_no' =>
                (string) $applicationNo,

                'company_name' =>
                (string) $companyName,

                'applicant_name' =>
                (string) $applicantName,

                'license_no' =>
                (string) $licenseNo,

                'application_type' =>
                $sourceType,

                'license_type' =>
                $sourceType === 'effluent'
                    ? 'Efluen'
                    : 'Badan Perairan',

                'year' =>
                (int) $year,

                'opening_balance' =>
                0.00,

                'total_debit' =>
                round($totalDebit, 2),

                'total_credit' =>
                round($totalCredit, 2),

                'closing_balance' =>
                round($balance, 2),

                'status' =>
                $balance <= 0.005
                    ? 'Selesai'
                    : 'Ada Tunggakan',

                'transactions' =>
                $transactions,
            ],
        ]);
    }

    private function invoiceIsPaid($invoice): bool
    {
        $invoiceStatus =
            strtolower(
                trim(
                    (string) (
                        $invoice->payment_status
                        ?? $invoice->status
                        ?? ''
                    )
                )
            );

        if (
            in_array(
                $invoiceStatus,
                [
                    'paid',
                    'success',
                    'completed',
                    'selesai',
                    'sudah_bayar',
                ],
                true
            )
        ) {
            return true;
        }

        $payment = $invoice->payment;

        if (!$payment) {
            return false;
        }

        $paymentStatus =
            strtolower(
                trim(
                    (string) (
                        $payment->payment_status
                        ?? $payment->status
                        ?? ''
                    )
                )
            );

        return in_array(
            $paymentStatus,
            [
                'paid',
                'success',
                'completed',
                'selesai',
                'sudah_bayar',
            ],
            true
        );
    }

    private function resolveApplicationType(
        $application
    ): string {
        $raw = strtolower(
            trim(
                (string) (
                    $application->application_type_source
                    ?? $application->application_type
                    ?? $application->module_type
                    ?? $application->category
                    ?? $application->type
                    ?? ''
                )
            )
        );

        if (
            str_contains($raw, 'effluent') ||
            str_contains($raw, 'efluen') ||
            str_contains($raw, 'pelepasan')
        ) {
            return 'effluent';
        }

        return 'water';
    }
}