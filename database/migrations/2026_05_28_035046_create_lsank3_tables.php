<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | USER & ACCESS MANAGEMENT
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->enum('user_type', ['public', 'internal', 'admin'])->default('public');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('lsank_roles', function (Blueprint $table) {
            $table->id('role_id');
            $table->string('role_name');
            $table->string('role_code')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_user_roles', function (Blueprint $table) {
            $table->id('user_role_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->cascadeOnDelete();
            $table->foreign('role_id')->references('role_id')->on('lsank_roles')->cascadeOnDelete();
            $table->foreign('assigned_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_districts', function (Blueprint $table) {
            $table->id('district_id');
            $table->string('district_name');
            $table->string('state_name')->default('Kedah');
            $table->string('postcode_prefix')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_user_profiles', function (Blueprint $table) {
            $table->id('profile_id');
            $table->unsignedBigInteger('user_id');
            $table->string('ic_no')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->cascadeOnDelete();
            $table->foreign('district_id')->references('district_id')->on('lsank_districts')->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | APPLICANT & COMPANY
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_applicants', function (Blueprint $table) {
            $table->id('applicant_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('applicant_type', ['individual', 'company', 'agency'])->default('individual');
            $table->string('applicant_name');
            $table->string('identity_no')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->cascadeOnDelete();
        });

        Schema::create('lsank_companies', function (Blueprint $table) {
            $table->id('company_id');
            $table->unsignedBigInteger('applicant_id');
            $table->string('company_name');
            $table->string('registration_no')->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('business_email')->nullable();
            $table->string('responsible_officer_name')->nullable();
            $table->string('responsible_officer_phone')->nullable();
            $table->timestamps();

            $table->foreign('applicant_id')->references('applicant_id')->on('lsank_applicants')->cascadeOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | APPLICATION REFERENCE TABLES
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_application_types', function (Blueprint $table) {
            $table->id('application_type_id');
            $table->string('type_name');
            $table->string('type_code')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_application_statuses', function (Blueprint $table) {
            $table->id('application_status_id');
            $table->string('status_name');
            $table->string('status_code')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lsank_activity_types', function (Blueprint $table) {
            $table->id('activity_type_id');
            $table->string('activity_name');
            $table->string('activity_code')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_service_types', function (Blueprint $table) {
            $table->id('service_type_id');
            $table->string('service_name');
            $table->string('service_code')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | MAIN APPLICATION TABLES
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_applications', function (Blueprint $table) {
            $table->id('application_id');
            $table->string('application_ref_no')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('applicant_id')->nullable();
            $table->unsignedBigInteger('application_type_id');
            $table->unsignedBigInteger('application_status_id')->nullable();
            $table->enum('application_category', ['new', 'renewal', 'amendment', 'cancellation'])->default('new');
            $table->timestamp('submitted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->cascadeOnDelete();
            $table->foreign('applicant_id')->references('applicant_id')->on('lsank_applicants')->nullOnDelete();
            $table->foreign('application_type_id')->references('application_type_id')->on('lsank_application_types')->restrictOnDelete();
            $table->foreign('application_status_id')->references('application_status_id')->on('lsank_application_statuses')->nullOnDelete();
        });

        Schema::create('lsank_water_body_applications', function (Blueprint $table) {
            $table->id('water_body_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('activity_type_id')->nullable();
            $table->text('activity_location')->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->string('operating_days')->nullable();
            $table->string('operating_time')->nullable();
            $table->decimal('motorized_fee', 12, 2)->default(0);
            $table->decimal('non_motorized_fee', 12, 2)->default(0);
            $table->text('activity_details')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->cascadeOnDelete();
            $table->foreign('activity_type_id')->references('activity_type_id')->on('lsank_activity_types')->nullOnDelete();
        });

        Schema::create('lsank_effluent_applications', function (Blueprint $table) {
            $table->id('effluent_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('service_type_id')->nullable();
            $table->text('activity_location')->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->text('composition')->nullable();
            $table->string('frequency')->nullable();
            $table->string('flow_rate')->nullable();
            $table->text('sampling_method')->nullable();
            $table->text('contingency_plan')->nullable();
            $table->text('disposal_method')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->cascadeOnDelete();
            $table->foreign('service_type_id')->references('service_type_id')->on('lsank_service_types')->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | DOCUMENTS
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_document_types', function (Blueprint $table) {
            $table->id('document_type_id');
            $table->unsignedBigInteger('application_type_id')->nullable();
            $table->string('document_name');
            $table->boolean('is_required')->default(false);
            $table->integer('max_file_size_mb')->default(5);
            $table->string('allowed_formats')->default('pdf,jpg,jpeg,png');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('application_type_id')->references('application_type_id')->on('lsank_application_types')->nullOnDelete();
        });

        Schema::create('lsank_application_documents', function (Blueprint $table) {
            $table->id('document_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('document_type_id')->nullable();
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('file_type', 50)->nullable();
            $table->integer('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->enum('status', ['uploaded', 'verified', 'rejected'])->default('uploaded');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->cascadeOnDelete();
            $table->foreign('document_type_id')->references('document_type_id')->on('lsank_document_types')->nullOnDelete();
            $table->foreign('uploaded_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | REVIEW & APPROVAL
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_application_reviews', function (Blueprint $table) {
            $table->id('review_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('reviewer_id');
            $table->unsignedBigInteger('review_role_id')->nullable();
            $table->enum('review_status', ['supported', 'not_supported', 'correction_required'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->cascadeOnDelete();
            $table->foreign('reviewer_id')->references('user_id')->on('lsank_users')->cascadeOnDelete();
            $table->foreign('review_role_id')->references('role_id')->on('lsank_roles')->nullOnDelete();
        });

        Schema::create('lsank_approval_records', function (Blueprint $table) {
            $table->id('approval_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->enum('approval_status', ['approved', 'rejected'])->nullable();
            $table->text('approval_remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->cascadeOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | LICENSE
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_license_statuses', function (Blueprint $table) {
            $table->id('license_status_id');
            $table->string('status_name');
            $table->string('status_code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lsank_licenses', function (Blueprint $table) {
            $table->id('license_id');
            $table->string('license_no')->unique();
            $table->string('file_no')->nullable();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->string('holder_name');
            $table->string('license_type');
            $table->date('start_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('license_status_id')->nullable();
            $table->string('qr_code_path', 500)->nullable();
            $table->string('license_pdf_path', 500)->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->nullOnDelete();
            $table->foreign('license_status_id')->references('license_status_id')->on('lsank_license_statuses')->nullOnDelete();
        });

        Schema::create('lsank_license_conditions', function (Blueprint $table) {
            $table->id('condition_id');
            $table->unsignedBigInteger('license_id');
            $table->text('condition_text');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('license_id')->references('license_id')->on('lsank_licenses')->cascadeOnDelete();
            $table->foreign('created_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_license_history', function (Blueprint $table) {
            $table->id('history_id');
            $table->unsignedBigInteger('license_id');
            $table->unsignedBigInteger('application_id')->nullable();
            $table->string('action_type');
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('license_id')->references('license_id')->on('lsank_licenses')->cascadeOnDelete();
            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_renewal_applications', function (Blueprint $table) {
            $table->id('renewal_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('license_id');
            $table->date('old_expiry_date')->nullable();
            $table->date('new_expiry_date')->nullable();
            $table->string('renewal_status')->default('draft');
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->cascadeOnDelete();
            $table->foreign('license_id')->references('license_id')->on('lsank_licenses')->cascadeOnDelete();
        });

        Schema::create('lsank_amendment_applications', function (Blueprint $table) {
            $table->id('amendment_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('license_id');
            $table->string('amendment_type')->nullable();
            $table->longText('old_information')->nullable();
            $table->longText('new_information')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->cascadeOnDelete();
            $table->foreign('license_id')->references('license_id')->on('lsank_licenses')->cascadeOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | PAYMENT, INVOICE & RECEIPT
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_payment_methods', function (Blueprint $table) {
            $table->id('payment_method_id');
            $table->string('method_name');
            $table->string('method_code')->unique();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_fee_rates', function (Blueprint $table) {
            $table->id('fee_rate_id');
            $table->unsignedBigInteger('application_type_id')->nullable();
            $table->unsignedBigInteger('activity_type_id')->nullable();
            $table->string('fee_name');
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('application_type_id')->references('application_type_id')->on('lsank_application_types')->nullOnDelete();
            $table->foreign('activity_type_id')->references('activity_type_id')->on('lsank_activity_types')->nullOnDelete();
        });

        Schema::create('lsank_invoices', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->string('invoice_no')->unique();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->unsignedBigInteger('license_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['unpaid', 'paid', 'cancelled', 'expired'])->default('unpaid');
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('lsank_applications')->nullOnDelete();
            $table->foreign('license_id')->references('license_id')->on('lsank_licenses')->nullOnDelete();
            $table->foreign('user_id')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_invoice_items', function (Blueprint $table) {
            $table->id('invoice_item_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('fee_rate_id')->nullable();
            $table->string('item_description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('invoice_id')->references('invoice_id')->on('lsank_invoices')->cascadeOnDelete();
            $table->foreign('fee_rate_id')->references('fee_rate_id')->on('lsank_fee_rates')->nullOnDelete();
        });

        Schema::create('lsank_payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('payment_status', ['pending', 'successful', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('payment_date')->nullable();
            $table->string('transaction_ref_no')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('invoice_id')->on('lsank_invoices')->cascadeOnDelete();
            $table->foreign('payment_method_id')->references('payment_method_id')->on('lsank_payment_methods')->nullOnDelete();
        });

        Schema::create('lsank_payment_transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->unsignedBigInteger('payment_id');
            $table->string('gateway_name')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('response_code')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('payment_id')->on('lsank_payments')->cascadeOnDelete();
        });

        Schema::create('lsank_receipts', function (Blueprint $table) {
            $table->id('receipt_id');
            $table->string('receipt_no')->unique();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('payment_id');
            $table->date('receipt_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('receipt_pdf_path', 500)->nullable();
            $table->enum('status', ['valid', 'cancelled'])->default('valid');
            $table->timestamps();

            $table->foreign('invoice_id')->references('invoice_id')->on('lsank_invoices')->cascadeOnDelete();
            $table->foreign('payment_id')->references('payment_id')->on('lsank_payments')->cascadeOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | COMPOUND, COMPLAINT & LEGAL
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_compound_statuses', function (Blueprint $table) {
            $table->id('compound_status_id');
            $table->string('status_name');
            $table->string('status_code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lsank_offence_types', function (Blueprint $table) {
            $table->id('offence_type_id');
            $table->string('offence_name');
            $table->string('offence_code')->unique();
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_compounds', function (Blueprint $table) {
            $table->id('compound_id');
            $table->string('compound_no')->unique();
            $table->unsignedBigInteger('license_id')->nullable();
            $table->unsignedBigInteger('offence_type_id')->nullable();
            $table->date('compound_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedBigInteger('compound_status_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('license_id')->references('license_id')->on('lsank_licenses')->nullOnDelete();
            $table->foreign('offence_type_id')->references('offence_type_id')->on('lsank_offence_types')->nullOnDelete();
            $table->foreign('compound_status_id')->references('compound_status_id')->on('lsank_compound_statuses')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_complaint_statuses', function (Blueprint $table) {
            $table->id('complaint_status_id');
            $table->string('status_name');
            $table->string('status_code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lsank_complaints', function (Blueprint $table) {
            $table->id('complaint_id');
            $table->string('complaint_no')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->text('location')->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->unsignedBigInteger('complaint_status_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->nullOnDelete();
            $table->foreign('complaint_status_id')->references('complaint_status_id')->on('lsank_complaint_statuses')->nullOnDelete();
        });

        Schema::create('lsank_complaint_documents', function (Blueprint $table) {
            $table->id('complaint_document_id');
            $table->unsignedBigInteger('complaint_id');
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->foreign('complaint_id')->references('complaint_id')->on('lsank_complaints')->cascadeOnDelete();
        });

        Schema::create('lsank_legal_case_statuses', function (Blueprint $table) {
            $table->id('legal_case_status_id');
            $table->string('status_name');
            $table->string('status_code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lsank_legal_cases', function (Blueprint $table) {
            $table->id('legal_case_id');
            $table->string('case_no')->unique();
            $table->unsignedBigInteger('license_id')->nullable();
            $table->unsignedBigInteger('compound_id')->nullable();
            $table->unsignedBigInteger('complaint_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('case_title');
            $table->text('case_description')->nullable();
            $table->unsignedBigInteger('legal_case_status_id')->nullable();
            $table->date('notice_date')->nullable();
            $table->date('action_date')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('license_id')->references('license_id')->on('lsank_licenses')->nullOnDelete();
            $table->foreign('compound_id')->references('compound_id')->on('lsank_compounds')->nullOnDelete();
            $table->foreign('complaint_id')->references('complaint_id')->on('lsank_complaints')->nullOnDelete();
            $table->foreign('invoice_id')->references('invoice_id')->on('lsank_invoices')->nullOnDelete();
            $table->foreign('legal_case_status_id')->references('legal_case_status_id')->on('lsank_legal_case_statuses')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_legal_case_documents', function (Blueprint $table) {
            $table->id('legal_document_id');
            $table->unsignedBigInteger('legal_case_id');
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->text('document_description')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->foreign('legal_case_id')->references('legal_case_id')->on('lsank_legal_cases')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION & SUPPORT
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->enum('notification_type', ['email', 'whatsapp', 'in_app'])->default('in_app');
            $table->string('related_module')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_notification_templates', function (Blueprint $table) {
            $table->id('template_id');
            $table->string('template_name');
            $table->string('template_code')->unique();
            $table->enum('channel', ['email', 'whatsapp', 'in_app'])->default('in_app');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_notification_logs', function (Blueprint $table) {
            $table->id('notification_log_id');
            $table->unsignedBigInteger('notification_id');
            $table->string('recipient')->nullable();
            $table->enum('delivery_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('provider_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('notification_id')->references('notification_id')->on('lsank_notifications')->cascadeOnDelete();
        });

        Schema::create('lsank_announcements', function (Blueprint $table) {
            $table->id('announcement_id');
            $table->string('title');
            $table->longText('content');
            $table->timestamp('publish_start')->nullable();
            $table->timestamp('publish_end')->nullable();
            $table->enum('status', ['draft', 'published', 'unpublished'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_faqs', function (Blueprint $table) {
            $table->id('faq_id');
            $table->string('category')->nullable();
            $table->text('question');
            $table->longText('answer');
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_checklists', function (Blueprint $table) {
            $table->id('checklist_id');
            $table->unsignedBigInteger('application_type_id')->nullable();
            $table->string('checklist_name');
            $table->text('description')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('application_type_id')->references('application_type_id')->on('lsank_application_types')->nullOnDelete();
        });

        Schema::create('lsank_support_contacts', function (Blueprint $table) {
            $table->id('contact_id');
            $table->string('organization_name')->default('Lembaga Sumber Air Negeri Kedah');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('operating_hours')->nullable();
            $table->text('map_url')->nullable();
            $table->timestamps();
        });

        Schema::create('lsank_enquiries', function (Blueprint $table) {
            $table->id('enquiry_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('status', ['new', 'in_progress', 'closed'])->default('new');
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | SYSTEM, AUDIT, REPORTING & MAINTENANCE
        |--------------------------------------------------------------------------
        */

        Schema::create('lsank_audit_logs', function (Blueprint $table) {
            $table->id('audit_log_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('module_name')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_login_attempts', function (Blueprint $table) {
            $table->id('login_attempt_id');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('status', ['success', 'failed'])->default('failed');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_password_resets', function (Blueprint $table) {
            $table->id('password_reset_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reset_token')->nullable();
            $table->string('verification_code')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('lsank_users')->cascadeOnDelete();
        });

        Schema::create('lsank_system_settings', function (Blueprint $table) {
            $table->id('setting_id');
            $table->string('setting_key')->unique();
            $table->longText('setting_value')->nullable();
            $table->string('setting_group')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_templates', function (Blueprint $table) {
            $table->id('template_id');
            $table->string('template_name');
            $table->enum('template_type', ['license', 'invoice', 'receipt', 'email', 'letter'])->default('letter');
            $table->string('template_file_path', 500)->nullable();
            $table->longText('content')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('lsank_reference_data', function (Blueprint $table) {
            $table->id('reference_id');
            $table->string('reference_group');
            $table->string('reference_code');
            $table->string('reference_value');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['reference_group', 'reference_code']);
        });

        Schema::create('lsank_locations', function (Blueprint $table) {
            $table->id('location_id');
            $table->string('location_name')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->timestamps();

            $table->foreign('district_id')->references('district_id')->on('lsank_districts')->nullOnDelete();
        });

        Schema::create('lsank_reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->string('report_name');
            $table->string('report_type')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_report_exports', function (Blueprint $table) {
            $table->id('export_id');
            $table->unsignedBigInteger('report_id');
            $table->enum('export_format', ['pdf', 'excel', 'csv'])->default('pdf');
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('exported_by')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->foreign('report_id')->references('report_id')->on('lsank_reports')->cascadeOnDelete();
            $table->foreign('exported_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });

        Schema::create('lsank_migration_logs', function (Blueprint $table) {
            $table->id('migration_log_id');
            $table->string('migration_name');
            $table->string('source_table')->nullable();
            $table->string('target_table')->nullable();
            $table->integer('total_records')->default(0);
            $table->integer('success_records')->default(0);
            $table->integer('failed_records')->default(0);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('lsank_backup_logs', function (Blueprint $table) {
            $table->id('backup_log_id');
            $table->enum('backup_type', ['full', 'incremental', 'manual'])->default('manual');
            $table->string('backup_file_path', 500)->nullable();
            $table->string('backup_size')->nullable();
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('lsank_system_issues', function (Blueprint $table) {
            $table->id('issue_id');
            $table->string('issue_no')->unique();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('module_name')->nullable();
            $table->string('issue_title');
            $table->text('issue_description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->foreign('reported_by')->references('user_id')->on('lsank_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_system_issues');
        Schema::dropIfExists('lsank_backup_logs');
        Schema::dropIfExists('lsank_migration_logs');
        Schema::dropIfExists('lsank_report_exports');
        Schema::dropIfExists('lsank_reports');
        Schema::dropIfExists('lsank_locations');
        Schema::dropIfExists('lsank_reference_data');
        Schema::dropIfExists('lsank_templates');
        Schema::dropIfExists('lsank_system_settings');
        Schema::dropIfExists('lsank_password_resets');
        Schema::dropIfExists('lsank_login_attempts');
        Schema::dropIfExists('lsank_audit_logs');

        Schema::dropIfExists('lsank_enquiries');
        Schema::dropIfExists('lsank_support_contacts');
        Schema::dropIfExists('lsank_checklists');
        Schema::dropIfExists('lsank_faqs');
        Schema::dropIfExists('lsank_announcements');
        Schema::dropIfExists('lsank_notification_logs');
        Schema::dropIfExists('lsank_notification_templates');
        Schema::dropIfExists('lsank_notifications');

        Schema::dropIfExists('lsank_legal_case_documents');
        Schema::dropIfExists('lsank_legal_cases');
        Schema::dropIfExists('lsank_legal_case_statuses');
        Schema::dropIfExists('lsank_complaint_documents');
        Schema::dropIfExists('lsank_complaints');
        Schema::dropIfExists('lsank_complaint_statuses');
        Schema::dropIfExists('lsank_compounds');
        Schema::dropIfExists('lsank_offence_types');
        Schema::dropIfExists('lsank_compound_statuses');

        Schema::dropIfExists('lsank_receipts');
        Schema::dropIfExists('lsank_payment_transactions');
        Schema::dropIfExists('lsank_payments');
        Schema::dropIfExists('lsank_invoice_items');
        Schema::dropIfExists('lsank_invoices');
        Schema::dropIfExists('lsank_fee_rates');
        Schema::dropIfExists('lsank_payment_methods');

        Schema::dropIfExists('lsank_amendment_applications');
        Schema::dropIfExists('lsank_renewal_applications');
        Schema::dropIfExists('lsank_license_history');
        Schema::dropIfExists('lsank_license_conditions');
        Schema::dropIfExists('lsank_licenses');
        Schema::dropIfExists('lsank_license_statuses');

        Schema::dropIfExists('lsank_approval_records');
        Schema::dropIfExists('lsank_application_reviews');

        Schema::dropIfExists('lsank_application_documents');
        Schema::dropIfExists('lsank_document_types');
        Schema::dropIfExists('lsank_effluent_applications');
        Schema::dropIfExists('lsank_water_body_applications');
        Schema::dropIfExists('lsank_applications');
        Schema::dropIfExists('lsank_service_types');
        Schema::dropIfExists('lsank_activity_types');
        Schema::dropIfExists('lsank_application_statuses');
        Schema::dropIfExists('lsank_application_types');

        Schema::dropIfExists('lsank_companies');
        Schema::dropIfExists('lsank_applicants');

        Schema::dropIfExists('lsank_user_profiles');
        Schema::dropIfExists('lsank_districts');
        Schema::dropIfExists('lsank_user_roles');
        Schema::dropIfExists('lsank_roles');
        Schema::dropIfExists('lsank_users');
    }
};