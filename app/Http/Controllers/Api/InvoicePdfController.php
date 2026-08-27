<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoicePdfController extends Controller
{
    /**
     * Download invoice as PDF.
     */
    public function download(
        Request $request,
        LsankInvoice $invoice
    ) {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Load related records
        |--------------------------------------------------------------------------
        */
        $invoice->load([
            'application.user',
            'payment.paymentMethod',
            'receipt',
        ]);

        $application = $invoice->application;
        $payment = $invoice->payment;

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan bagi invois ini tidak dijumpai.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */
        if (!$this->canAccessInvoice($user, $invoice)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dibenarkan mengakses invois ini.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Applicant / Company Details
        |--------------------------------------------------------------------------
        */

        $applicantName =
            $application->applicant_name
            ?: $application->name
            ?: $application->user?->name
            ?: '-';

        $companyName =
            $application->business_name
            ?: $application->company_name
            ?: $application->organization_name
            ?: '-';

        $displayName =
            $companyName !== '-'
            ? $companyName
            : $applicantName;

        $email =
            $application->email
            ?: $application->business_email
            ?: $application->company_email
            ?: $application->user?->email
            ?: '-';

        $phone =
            $application->phone
            ?: $application->phone_no
            ?: $application->business_phone
            ?: $application->company_phone
            ?: $application->user?->phone
            ?: '-';

        $address =
            $application->business_address
            ?: $application->address
            ?: $application->company_address
            ?: $application->mailing_address
            ?: '-';

        /*
        |--------------------------------------------------------------------------
        | File Number
        |--------------------------------------------------------------------------
        */
        $fileNo =
            $application->application_ref_no
            ?: $application->file_no
            ?: $application->reference_no
            ?: $application->application_no
            ?: '-';

        /*
        |--------------------------------------------------------------------------
        | Activity Information
        |--------------------------------------------------------------------------
        */
        $activityName =
            $application->activity_name
            ?: $application->activity_type
            ?: $application->license_type
            ?: $invoice->description
            ?: 'Bayaran Permohonan';

        /*
        |--------------------------------------------------------------------------
        | Invoice Date
        |--------------------------------------------------------------------------
        */
        $invoiceDate =
            $invoice->invoice_date
            ?: $invoice->issued_at
            ?: $invoice->created_at;

        /*
        |--------------------------------------------------------------------------
        | Payment Status
        |--------------------------------------------------------------------------
        */
        $paymentStatus = strtolower(
            trim((string) ($payment?->payment_status ?? ''))
        );

        $isPaid = in_array($paymentStatus, [
            'paid',
            'success',
            'successful',
            'sudah_bayar',
            'completed',
        ], true)
            || strtolower(
                trim((string) $invoice->status)
            ) === 'paid';

        /*
        |--------------------------------------------------------------------------
        | Data For PDF
        |--------------------------------------------------------------------------
        */
        $pdfData = [
            'invoice' => $invoice,
            'application' => $application,
            'payment' => $payment,

            'applicantName' => $applicantName,
            'companyName' => $companyName,
            'displayName' => $displayName,

            'email' => $email,
            'phone' => $phone,
            'address' => $address,

            'fileNo' => $fileNo,
            'activityName' => $activityName,
            'invoiceDate' => $invoiceDate,

            'isPaid' => $isPaid,
        ];

        /*
        |--------------------------------------------------------------------------
        | Generate PDF
        |--------------------------------------------------------------------------
        */
        $pdf = Pdf::loadView(
            'pdf.invoice',
            $pdfData
        )->setPaper('a4', 'portrait');

        /*
        |--------------------------------------------------------------------------
        | File Name
        |--------------------------------------------------------------------------
        */
        $invoiceNo =
            $invoice->invoice_no
            ?: 'INV-' . $invoice->invoice_id;

        $safeInvoiceNo = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '-',
            $invoiceNo
        );

        return $pdf->download(
            'Invois-' . $safeInvoiceNo . '.pdf'
        );
    }

    /**
     * Check whether authenticated user can access invoice.
     */
    private function canAccessInvoice(
        $user,
        LsankInvoice $invoice
    ): bool {
        if (!$user) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Owner Invoice
        |--------------------------------------------------------------------------
        */
        if (
            (int) $invoice->user_id ===
            (int) $user->user_id
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Owner Application
        |--------------------------------------------------------------------------
        */
        if (
            $invoice->application &&
            (int) $invoice->application->user_id ===
            (int) $user->user_id
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | LSANK Internal Staff
        |--------------------------------------------------------------------------
        */
        $userType = strtolower(
            trim((string) $user->user_type)
        );

        return in_array($userType, [
            'admin',
            'kewangan',
            'pengarah',
            'penguatkuasa',
            'teknikal_badan_perairan',
            'ketua_unit_badan_perairan',
            'teknikal_efluen',
            'ketua_unit_efluen',
        ], true);
    }
}
