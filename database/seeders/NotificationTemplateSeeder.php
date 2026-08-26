<?php

namespace Database\Seeders;

use App\Models\LsankNotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [

            /*
            |--------------------------------------------------------------------------
            | APPLICATION
            |--------------------------------------------------------------------------
            */

            /*
             * application.submitted
             */
            [
                'event_type' =>
                    'application.submitted',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Permohonan Berjaya Dihantar',

                'body_template' =>
                    'Permohonan {{application_no}} telah berjaya dihantar kepada LSANK untuk diproses.',

                'severity' =>
                    'success',

                'priority' =>
                    3,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'application.submitted',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Permohonan {{application_no}} Diterima',

                'title_template' =>
                    'Permohonan Diterima',

                'body_template' =>
                    'Permohonan {{application_no}} telah berjaya diterima oleh LSANK dan akan diproses.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],

            [
                'event_type' =>
                    'application.submitted',

                'audience' =>
                    'department_staff',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Permohonan Baharu',

                'body_template' =>
                    'Permohonan {{application_no}} daripada {{applicant_name}} memerlukan semakan.',

                'severity' =>
                    'warning',

                'priority' =>
                    2,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Permohonan',

                'show_as_ribbon' =>
                    true,

                'ribbon_duration_seconds' =>
                    7,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'application.submitted',

                'audience' =>
                    'department_head',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Permohonan Baharu Diterima',

                'body_template' =>
                    'Permohonan {{application_no}} telah dihantar ke bahagian anda.',

                'severity' =>
                    'info',

                'priority' =>
                    3,

                'mandatory' =>
                    true,
            ],

            /*
             * application.review_required
             */
            [
                'event_type' =>
                    'application.review_required',

                'audience' =>
                    'department_staff',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Semakan Permohonan Diperlukan',

                'body_template' =>
                    'Permohonan {{application_no}} telah ditugaskan kepada anda dan memerlukan semakan.',

                'severity' =>
                    'warning',

                'priority' =>
                    2,

                'action_required' =>
                    true,

                'action_label' =>
                    'Mulakan Semakan',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'application.review_required',

                'audience' =>
                    'department_head',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Permohonan Sedang Dalam Semakan',

                'body_template' =>
                    'Permohonan {{application_no}} memerlukan tindakan semakan oleh pegawai teknikal.',

                'severity' =>
                    'info',

                'priority' =>
                    3,

                'mandatory' =>
                    true,
            ],

            /*
             * application.technical_review_completed
             */
            [
                'event_type' =>
                    'application.technical_review_completed',

                'audience' =>
                    'department_head',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Semakan Teknikal Selesai',

                'body_template' =>
                    'Semakan teknikal bagi permohonan {{application_no}} telah selesai dan memerlukan tindakan anda.',

                'severity' =>
                    'warning',

                'priority' =>
                    2,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Laporan Teknikal',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'application.technical_review_completed',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Semakan Teknikal Selesai',

                'body_template' =>
                    'Permohonan {{application_no}} telah selesai melalui semakan teknikal dan sedang diproses ke peringkat seterusnya.',

                'severity' =>
                    'info',

                'priority' =>
                    3,
            ],

            /*
             * application.approved
             */
            [
                'event_type' =>
                    'application.approved',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Permohonan Diluluskan',

                'body_template' =>
                    'Tahniah. Permohonan {{application_no}} telah diluluskan.',

                'severity' =>
                    'success',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Permohonan',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'application.approved',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Permohonan {{application_no}} Diluluskan',

                'title_template' =>
                    'Permohonan Diluluskan',

                'body_template' =>
                    'Permohonan {{application_no}} telah diluluskan. Sila log masuk ke Sistem LSANK untuk tindakan selanjutnya.',

                'severity' =>
                    'success',

                'priority' =>
                    1,

                'mandatory' =>
                    true,
            ],

            /*
             * application.rejected
             */
            [
                'event_type' =>
                    'application.rejected',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Permohonan Tidak Diluluskan',

                'body_template' =>
                    'Permohonan {{application_no}} tidak diluluskan. Sila semak maklumat keputusan permohonan.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Keputusan',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'application.rejected',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Keputusan Permohonan {{application_no}}',

                'title_template' =>
                    'Keputusan Permohonan',

                'body_template' =>
                    'Permohonan {{application_no}} tidak diluluskan. Sila log masuk ke Sistem LSANK untuk melihat maklumat lanjut.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'mandatory' =>
                    true,
            ],

            /*
            |--------------------------------------------------------------------------
            | INVOICE
            |--------------------------------------------------------------------------
            */

            [
                'event_type' =>
                    'invoice.created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Invois Baharu Dijana',

                'body_template' =>
                    'Invois {{invoice_no}} bagi permohonan {{application_no}} telah dijana dan menunggu bayaran.',

                'severity' =>
                    'warning',

                'priority' =>
                    2,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Invois',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'invoice.created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Invois {{invoice_no}}',

                'title_template' =>
                    'Invois Baharu',

                'body_template' =>
                    'Invois {{invoice_no}} bagi permohonan {{application_no}} telah dijana. Sila buat bayaran melalui Sistem LSANK.',

                'severity' =>
                    'warning',

                'priority' =>
                    2,
            ],

            [
                'event_type' =>
                    'invoice.created',

                'audience' =>
                    'kewangan',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Invois Baharu',

                'body_template' =>
                    'Invois {{invoice_no}} telah dijana bagi permohonan {{application_no}}.',

                'severity' =>
                    'info',

                'priority' =>
                    3,
            ],

            /*
            |--------------------------------------------------------------------------
            | PAYMENT
            |--------------------------------------------------------------------------
            */

            [
                'event_type' =>
                    'payment.received',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Bayaran Berjaya Diterima',

                'body_template' =>
                    'Bayaran bagi invois {{invoice_no}} telah berjaya diterima oleh LSANK.',

                'severity' =>
                    'success',

                'priority' =>
                    3,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'payment.received',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Bayaran {{invoice_no}} Diterima',

                'title_template' =>
                    'Bayaran Berjaya',

                'body_template' =>
                    'Bayaran bagi invois {{invoice_no}} telah berjaya diterima. Resit pembayaran boleh disemak melalui Sistem LSANK.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],

            [
                'event_type' =>
                    'payment.received',

                'audience' =>
                    'kewangan',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Bayaran Baharu Diterima',

                'body_template' =>
                    'Bayaran bagi invois {{invoice_no}} telah diterima.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],

            /*
            |--------------------------------------------------------------------------
            | LICENSE
            |--------------------------------------------------------------------------
            */

            /*
             * license.created
             */
            [
                'event_type' =>
                    'license.created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Lesen Berjaya Dijana',

                'body_template' =>
                    'Lesen {{license_no}} telah berjaya dijana dan boleh diakses melalui Sistem LSANK.',

                'severity' =>
                    'success',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Lihat Lesen',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'license.created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Lesen {{license_no}} Telah Dijana',

                'title_template' =>
                    'Lesen Berjaya Dijana',

                'body_template' =>
                    'Lesen {{license_no}} telah berjaya dijana. Sila log masuk ke Sistem LSANK untuk melihat atau memuat turun lesen.',

                'severity' =>
                    'success',

                'priority' =>
                    1,

                'mandatory' =>
                    true,
            ],

            /*
             * license.expiring
             */
            [
                'event_type' =>
                    'license.expiring',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Lesen Akan Tamat Tempoh',

                'body_template' =>
                    'Lesen {{license_no}} akan tamat tempoh pada {{expiry_date}}. Sila buat pembaharuan sekiranya diperlukan.',

                'severity' =>
                    'warning',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Perbaharui Lesen',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'license.expiring',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Lesen {{license_no}} Akan Tamat Tempoh',

                'title_template' =>
                    'Peringatan Tamat Tempoh Lesen',

                'body_template' =>
                    'Lesen {{license_no}} akan tamat tempoh pada {{expiry_date}}. Sila log masuk ke Sistem LSANK untuk membuat pembaharuan.',

                'severity' =>
                    'warning',

                'priority' =>
                    1,

                'mandatory' =>
                    true,
            ],

            /*
             * license.expired
             */
            [
                'event_type' =>
                    'license.expired',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Lesen Telah Tamat Tempoh',

                'body_template' =>
                    'Lesen {{license_no}} telah tamat tempoh pada {{expiry_date}}.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Lesen',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'license.expired',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Lesen {{license_no}} Telah Tamat Tempoh',

                'title_template' =>
                    'Lesen Tamat Tempoh',

                'body_template' =>
                    'Lesen {{license_no}} telah tamat tempoh pada {{expiry_date}}. Sila log masuk ke Sistem LSANK untuk tindakan lanjut.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'mandatory' =>
                    true,
            ],

            /*
            |--------------------------------------------------------------------------
            | RENEWAL
            |--------------------------------------------------------------------------
            */

            /*
             * renewal.submitted
             */
            [
                'event_type' =>
                    'renewal.submitted',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Permohonan Pembaharuan Dihantar',

                'body_template' =>
                    'Permohonan pembaharuan bagi lesen {{license_no}} telah berjaya dihantar.',

                'severity' =>
                    'success',

                'priority' =>
                    3,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'renewal.submitted',

                'audience' =>
                    'department_staff',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Pembaharuan Lesen Baharu',

                'body_template' =>
                    'Permohonan pembaharuan lesen {{license_no}} memerlukan semakan.',

                'severity' =>
                    'warning',

                'priority' =>
                    2,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Pembaharuan',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'renewal.submitted',

                'audience' =>
                    'department_head',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Permohonan Pembaharuan Baharu',

                'body_template' =>
                    'Permohonan pembaharuan lesen {{license_no}} telah diterima oleh bahagian anda.',

                'severity' =>
                    'info',

                'priority' =>
                    3,
            ],

            /*
             * renewal.approved
             */
            [
                'event_type' =>
                    'renewal.approved',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Pembaharuan Lesen Diluluskan',

                'body_template' =>
                    'Permohonan pembaharuan lesen {{license_no}} telah diluluskan.',

                'severity' =>
                    'success',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Lesen',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'renewal.approved',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Pembaharuan Lesen {{license_no}} Diluluskan',

                'title_template' =>
                    'Pembaharuan Lesen Diluluskan',

                'body_template' =>
                    'Permohonan pembaharuan lesen {{license_no}} telah diluluskan. Sila log masuk ke Sistem LSANK untuk tindakan lanjut.',

                'severity' =>
                    'success',

                'priority' =>
                    1,
            ],

            /*
             * renewal.rejected
             */
            [
                'event_type' =>
                    'renewal.rejected',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Pembaharuan Lesen Tidak Diluluskan',

                'body_template' =>
                    'Permohonan pembaharuan lesen {{license_no}} tidak diluluskan.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Keputusan',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'renewal.rejected',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Keputusan Pembaharuan Lesen {{license_no}}',

                'title_template' =>
                    'Keputusan Pembaharuan Lesen',

                'body_template' =>
                    'Permohonan pembaharuan lesen {{license_no}} tidak diluluskan. Sila semak Sistem LSANK untuk maklumat lanjut.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,
            ],

            /*
            |--------------------------------------------------------------------------
            | NOTICE / PENGUATKUASA
            |--------------------------------------------------------------------------
            */

            /*
             * notice.created
             */
            [
                'event_type' =>
                    'notice.created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Notis Baharu Dikeluarkan',

                'body_template' =>
                    'Notis {{notice_no}} telah dikeluarkan kepada anda dan memerlukan tindakan.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Notis',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'notice.created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Notis {{notice_no}} Dikeluarkan',

                'title_template' =>
                    'Notis Baharu',

                'body_template' =>
                    'Notis {{notice_no}} telah dikeluarkan kepada anda. Sila log masuk ke Sistem LSANK untuk semakan dan tindakan.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'notice.created',

                'audience' =>
                    'penguatkuasa',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Notis Berjaya Dikeluarkan',

                'body_template' =>
                    'Notis {{notice_no}} telah berjaya direkodkan dan dikeluarkan.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],

            [
                'event_type' =>
                    'notice.created',

                'audience' =>
                    'department_head',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Notis Penguatkuasaan Dikeluarkan',

                'body_template' =>
                    'Notis {{notice_no}} telah dikeluarkan bagi pemegang lesen di bawah bahagian anda.',

                'severity' =>
                    'warning',

                'priority' =>
                    2,
            ],

            /*
             * notice.acknowledged
             */
            [
                'event_type' =>
                    'notice.acknowledged',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Penerimaan Notis Direkodkan',

                'body_template' =>
                    'Penerimaan Notis {{notice_no}} telah berjaya direkodkan.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],

            [
                'event_type' =>
                    'notice.acknowledged',

                'audience' =>
                    'penguatkuasa',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Notis Telah Diakui Terima',

                'body_template' =>
                    'Pemegang lesen telah mengakui penerimaan Notis {{notice_no}}.',

                'severity' =>
                    'info',

                'priority' =>
                    2,
            ],

            /*
             * notice.response_received
             */
            [
                'event_type' =>
                    'notice.response_received',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Maklum Balas Notis Diterima',

                'body_template' =>
                    'Maklum balas bagi Notis {{notice_no}} telah berjaya diterima oleh LSANK.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],

            [
                'event_type' =>
                    'notice.response_received',

                'audience' =>
                    'penguatkuasa',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Maklum Balas Notis Baharu',

                'body_template' =>
                    'Maklum balas bagi Notis {{notice_no}} telah diterima dan memerlukan semakan.',

                'severity' =>
                    'warning',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Maklum Balas',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'notice.response_received',

                'audience' =>
                    'department_head',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Maklum Balas Notis Diterima',

                'body_template' =>
                    'Maklum balas bagi Notis {{notice_no}} telah diterima daripada pemegang lesen.',

                'severity' =>
                    'info',

                'priority' =>
                    3,
            ],

            /*
             * notice.response_overdue
             */
            [
                'event_type' =>
                    'notice.response_overdue',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Tindakan Notis Telah Melepasi Tempoh',

                'body_template' =>
                    'Tempoh maklum balas bagi Notis {{notice_no}} telah tamat. Kes boleh dirujuk kepada Unit Perundangan.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Notis',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'notice.response_overdue',

                'audience' =>
                    'penguatkuasa',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Notis Tiada Maklum Balas',

                'body_template' =>
                    'Notis {{notice_no}} telah melepasi tempoh maklum balas dan perlu dirujuk kepada Unit Perundangan.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Notis',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'notice.response_overdue',

                'audience' =>
                    'perundangan',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Notis Memerlukan Tindakan Perundangan',

                'body_template' =>
                    'Notis {{notice_no}} telah melepasi tempoh maklum balas dan memerlukan semakan Unit Perundangan.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Rujukan',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            /*
            |--------------------------------------------------------------------------
            | LEGAL REFERRAL
            |--------------------------------------------------------------------------
            */

            [
                'event_type' =>
                    'legal.referral_created',

                'audience' =>
                    'perundangan',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Rujukan Perundangan Baharu',

                'body_template' =>
                    'Rujukan perundangan baharu berkaitan Notis {{notice_no}} memerlukan tindakan anda.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Rujukan',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.referral_created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Dirujuk Kepada Unit Perundangan',

                'body_template' =>
                    'Kes berkaitan Notis {{notice_no}} telah dirujuk kepada Unit Perundangan LSANK.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.referral_created',

                'audience' =>
                    'penguatkuasa',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Rujukan Perundangan Berjaya',

                'body_template' =>
                    'Kes berkaitan Notis {{notice_no}} telah berjaya dirujuk kepada Unit Perundangan.',

                'severity' =>
                    'info',

                'priority' =>
                    2,
            ],

            [
                'event_type' =>
                    'legal.referral_created',

                'audience' =>
                    'department_head',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Dirujuk Kepada Perundangan',

                'body_template' =>
                    'Kes berkaitan Notis {{notice_no}} bagi pemegang lesen di bawah bahagian anda telah dirujuk kepada Unit Perundangan.',

                'severity' =>
                    'warning',

                'priority' =>
                    2,
            ],

            /*
            |--------------------------------------------------------------------------
            | CIVIL CASE
            |--------------------------------------------------------------------------
            */

            [
                'event_type' =>
                    'legal.civil_created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Tindakan Perundangan Sivil',

                'body_template' =>
                    'Kes Sivil {{case_no}} telah dibuka berkaitan lesen {{license_no}}.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Kes',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.civil_created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Kes Sivil {{case_no}}',

                'title_template' =>
                    'Tindakan Perundangan Sivil',

                'body_template' =>
                    'Kes Sivil {{case_no}} telah dibuka berkaitan lesen {{license_no}}. Sila log masuk ke Sistem LSANK untuk maklumat lanjut.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.civil_created',

                'audience' =>
                    'perundangan',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Sivil Memerlukan Tindakan',

                'body_template' =>
                    'Kes Sivil {{case_no}} bagi {{party_name}} memerlukan semakan.',

                'severity' =>
                    'warning',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Kes Sivil',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.civil_created',

                'audience' =>
                    'penguatkuasa',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Sivil Telah Dibuka',

                'body_template' =>
                    'Kes Sivil {{case_no}} telah dibuka oleh Unit Perundangan.',

                'severity' =>
                    'info',

                'priority' =>
                    2,
            ],

            /*
            |--------------------------------------------------------------------------
            | CRIMINAL CASE
            |--------------------------------------------------------------------------
            */

            [
                'event_type' =>
                    'legal.criminal_created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Tindakan Perundangan Jenayah',

                'body_template' =>
                    'Kes Jenayah {{case_no}} telah dibuka berkaitan lesen {{license_no}}.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Kes',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.criminal_created',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'email',

                'subject_template' =>
                    '[LSANK] Kes Jenayah {{case_no}}',

                'title_template' =>
                    'Tindakan Perundangan Jenayah',

                'body_template' =>
                    'Kes Jenayah {{case_no}} telah dibuka berkaitan lesen {{license_no}}. Sila semak Sistem LSANK untuk maklumat lanjut.',

                'severity' =>
                    'danger',

                'priority' =>
                    1,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.criminal_created',

                'audience' =>
                    'perundangan',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Jenayah Memerlukan Tindakan',

                'body_template' =>
                    'Kes Jenayah {{case_no}} bagi {{party_name}} memerlukan tindakan Unit Perundangan.',

                'severity' =>
                    'warning',

                'priority' =>
                    1,

                'action_required' =>
                    true,

                'action_label' =>
                    'Semak Kes Jenayah',

                'show_as_ribbon' =>
                    true,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.criminal_created',

                'audience' =>
                    'penguatkuasa',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Jenayah Telah Dibuka',

                'body_template' =>
                    'Kes Jenayah {{case_no}} telah dibuka oleh Unit Perundangan.',

                'severity' =>
                    'info',

                'priority' =>
                    2,
            ],

            /*
            |--------------------------------------------------------------------------
            | LEGAL CASE RESOLVED
            |--------------------------------------------------------------------------
            */

            [
                'event_type' =>
                    'legal.case_resolved',

                'audience' =>
                    'license_holder',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Perundangan Selesai',

                'body_template' =>
                    'Kes {{case_no}} telah ditandakan sebagai selesai oleh Unit Perundangan LSANK.',

                'severity' =>
                    'success',

                'priority' =>
                    2,

                'mandatory' =>
                    true,
            ],

            [
                'event_type' =>
                    'legal.case_resolved',

                'audience' =>
                    'perundangan',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Berjaya Diselesaikan',

                'body_template' =>
                    'Kes {{case_no}} telah berjaya ditandakan sebagai selesai.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],

            [
                'event_type' =>
                    'legal.case_resolved',

                'audience' =>
                    'penguatkuasa',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Status Kes Dikemas Kini',

                'body_template' =>
                    'Kes {{case_no}} telah diselesaikan oleh Unit Perundangan.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],

            [
                'event_type' =>
                    'legal.case_resolved',

                'audience' =>
                    'department_head',

                'channel' =>
                    'in_app',

                'title_template' =>
                    'Kes Perundangan Selesai',

                'body_template' =>
                    'Kes {{case_no}} berkaitan bahagian anda telah ditandakan sebagai selesai.',

                'severity' =>
                    'success',

                'priority' =>
                    3,
            ],
        ];

        foreach ($templates as $template) {
            $eventType =
                $template['event_type'];

            $audience =
                $template['audience'];

            $channel =
                $template['channel'];

            $language =
                $template['language']
                ?? 'ms';

            LsankNotificationTemplate::query()
                ->updateOrCreate(
                    [
                        'event_type' =>
                            $eventType,

                        'audience' =>
                            $audience,

                        'channel' =>
                            $channel,

                        'language' =>
                            $language,
                    ],
                    array_merge(
                        [
                            'subject_template' =>
                                null,

                            'title_template' =>
                                null,

                            'body_template' =>
                                '',

                            'provider_template_name' =>
                                null,

                            'provider_parameter_keys' =>
                                null,

                            'severity' =>
                                'info',

                            'priority' =>
                                3,

                            'action_required' =>
                                false,

                            'action_label' =>
                                null,

                            'show_as_ribbon' =>
                                false,

                            'ribbon_duration_seconds' =>
                                7,

                            'mandatory' =>
                                false,

                            'is_enabled' =>
                                true,
                        ],
                        $template,
                        [
                            /*
                             * Always enforce language here so
                             * updateOrCreate remains consistent.
                             */
                            'language' =>
                                $language,
                        ]
                    )
                );
        }
    }
}