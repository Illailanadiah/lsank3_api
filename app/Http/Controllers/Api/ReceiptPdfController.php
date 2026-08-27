<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReceiptPdfController extends Controller
{
    /**
     * Download official receipt as PDF.
     */
    public function download(
        Request $request,
        LsankReceipt $receipt
    ) {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Load relationships
        |--------------------------------------------------------------------------
        */
        $receipt->load([
            'invoice.application.user',
            'payment.paymentMethod',
        ]);

        $invoice = $receipt->invoice;
        $payment = $receipt->payment;

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invois bagi resit ini tidak dijumpai.',
            ], 404);
        }

        $application = $invoice->application;

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan bagi resit ini tidak dijumpai.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */
        if (!$this->canAccessReceipt($user, $receipt)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dibenarkan mengakses resit ini.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Receipt must be valid
        |--------------------------------------------------------------------------
        */
        if (
            strtolower(trim((string) $receipt->status))
            !== 'valid'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Resit ini tidak sah atau telah dibatalkan.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Payment must be successful
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
        ], true);

        /*
         * Fallback sebab invoice lama mungkin hanya
         * update status invoice.
         */
        if (
            !$isPaid &&
            strtolower(trim((string) $invoice->status)) === 'paid'
        ) {
            $isPaid = true;
        }

        if (!$isPaid) {
            return response()->json([
                'success' => false,
                'message' =>
                'Resit hanya boleh dimuat turun selepas bayaran berjaya.',
            ], 422);
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
            $application->address
            ?: $application->business_address
            ?: $application->company_address
            ?: $application->mailing_address
            ?: '-';

        $fileNo =
            $application->application_ref_no
            ?: $application->file_no
            ?: $application->real_file_no
            ?: $application->draft_file_no
            ?: $application->reference_no
            ?: $application->application_no
            ?: $invoice->file_no
            ?: '-';

        $activityName =
            $application->activity_name
            ?: $application->activity_type
            ?: $application->license_type
            ?: $invoice->description
            ?: 'Bayaran Permohonan';

        $receiptDate =
            $receipt->receipt_date
            ?: $receipt->issued_at
            ?: $receipt->paid_at
            ?: $payment?->paid_at
            ?: $payment?->payment_date
            ?: $receipt->created_at;

        /*
        |--------------------------------------------------------------------------
        | Generate PDF
        |--------------------------------------------------------------------------
        */
        $pdfData = [
            'receipt' => $receipt,
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
            'receiptDate' => $receiptDate,
        ];

        $pdf = Pdf::loadView(
            'pdf.receipt',
            $pdfData
        )->setPaper('a4', 'portrait');

        /*
        |--------------------------------------------------------------------------
        | Save copy to storage
        |--------------------------------------------------------------------------
        */
        $receiptNo =
            $receipt->receipt_no
            ?: 'RESIT-' . $receipt->receipt_id;

        $safeReceiptNo = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '-',
            $receiptNo
        );

        $fileName =
            'receipts/'
            . $safeReceiptNo
            . '.pdf';

        Storage::disk('local')->put(
            $fileName,
            $pdf->output()
        );

        /*
        |--------------------------------------------------------------------------
        | Save PDF path
        |--------------------------------------------------------------------------
        */
        if ($receipt->receipt_pdf_path !== $fileName) {
            $receipt->update([
                'receipt_pdf_path' => $fileName,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Download response
        |--------------------------------------------------------------------------
        */
        $downloadPdf = Pdf::loadView(
            'pdf.receipt',
            $pdfData
        )->setPaper('a4', 'portrait');

        return $downloadPdf->download(
            'Resit-' . $safeReceiptNo . '.pdf'
        );
    }

    private function canAccessReceipt(
        $user,
        LsankReceipt $receipt
    ): bool {
        if (!$user) {
            return false;
        }

        $invoice = $receipt->invoice;

        if (!$invoice) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Owner invoice
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
        | Owner application
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
        | Staff roles
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
