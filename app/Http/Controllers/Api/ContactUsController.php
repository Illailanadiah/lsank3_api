<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankEnquiry;
use App\Models\LsankNotification;
use App\Models\LsankSupportContact;
use App\Models\LsankUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactUsController extends Controller
{
    // ============================================================
    // CONTACT INFO
    // ============================================================

    public function info()
    {
        $contact = LsankSupportContact::query()
            ->orderBy('contact_id')
            ->first();

        if (!$contact) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'contact_id' =>
                $contact->contact_id,

                'organization_name' =>
                $contact->organization_name,

                'address' =>
                $contact->address,

                'phone' =>
                $contact->phone,

                'email' =>
                $contact->email,

                'website' =>
                $contact->website,

                'operating_hours' =>
                $contact->operating_hours,

                'map_url' =>
                $contact->map_url,
            ],
        ]);
    }

    // ============================================================
    // USER ENQUIRIES
    // ============================================================

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $items = LsankEnquiry::query()
            ->where(
                'user_id',
                $user->user_id
            )
            ->orderByDesc(
                'enquiry_id'
            )
            ->get()
            ->map(function ($enquiry) {
                return $this->formatEnquiry(
                    $enquiry
                );
            })
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    // ============================================================
    // CREATE ENQUIRY
    // ============================================================

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'subject' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        $enquiry = DB::transaction(function () use (
            $user,
            $validated
        ) {
            $enquiry = LsankEnquiry::create([
                'user_id' =>
                $user->user_id,

                'name' =>
                $user->name,

                'email' =>
                $user->email,

                'phone_no' =>
                $user->phone,

                'subject' =>
                trim(
                    $validated['subject']
                ),

                'message' =>
                trim(
                    $validated['message']
                ),

                'status' =>
                'new',
            ]);

            $this->createAdminEnquiryNotifications(
                $enquiry
            );

            return $enquiry;
        });

        Log::info(
            'USER ENQUIRY CREATED',
            [
                'enquiry_id' =>
                $enquiry->enquiry_id,

                'user_id' =>
                $user->user_id,

                'subject' =>
                $enquiry->subject,
            ]
        );

        return response()->json([
            'success' => true,
            'message' =>
            'Pertanyaan berjaya dihantar.',
            'data' =>
            $this->formatEnquiry(
                $enquiry->fresh()
            ),
        ], 201);
    }

    // ============================================================
    // USER ENQUIRY DETAIL
    // ============================================================

    public function show(
        Request $request,
        LsankEnquiry $enquiry
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (
            (int) $enquiry->user_id !==
            (int) $user->user_id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Anda tidak dibenarkan melihat pertanyaan ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' =>
            $this->formatEnquiry(
                $enquiry
            ),
        ]);
    }

    // ============================================================
    // CREATE ADMIN NOTIFICATIONS
    // ============================================================

    private function createAdminEnquiryNotifications(
        LsankEnquiry $enquiry
    ): void {
        $admins = LsankUser::query()
            ->where(
                'status',
                'active'
            )
            ->whereIn(
                'user_type',
                [
                    'admin',
                    'super_admin',
                ]
            )
            ->get([
                'user_id',
            ]);

        if ($admins->isEmpty()) {
            Log::warning(
                'NO ACTIVE ADMIN FOUND FOR ENQUIRY NOTIFICATION',
                [
                    'enquiry_id' =>
                    $enquiry->enquiry_id,
                ]
            );

            return;
        }

        foreach ($admins as $admin) {
            $eventKey =
                'enquiry_created:'
                . $enquiry->enquiry_id
                . ':admin:'
                . $admin->user_id;

            LsankNotification::firstOrCreate(
                [
                    'event_key' =>
                    $eventKey,
                ],
                [
                    'user_id' =>
                    $admin->user_id,

                    'title' =>
                    'Pertanyaan Pengguna Baharu',

                    'message' =>
                    'Pertanyaan baharu daripada '
                        . (
                            $enquiry->name
                            ?: 'pengguna'
                        )
                        . ': '
                        . $enquiry->subject,

                    'notification_type' =>
                    'in_app',

                    'event_type' =>
                    'enquiry_created',

                    'audience' =>
                    'admin',

                    'severity' =>
                    'info',

                    /*
                     * NotificationController susun priority
                     * secara ascending.
                     *
                     * 1 = sangat penting
                     * 2 = perlu perhatian
                     * 3 = biasa
                     */
                    'priority' =>
                    2,

                    'related_module' =>
                    'enquiry',

                    'related_id' =>
                    $enquiry->enquiry_id,

                    /*
                     * Admin memang perlu semak enquiry baru.
                     */
                    'action_required' =>
                    true,

                    'action_label' =>
                    'Lihat Pertanyaan',

                    'action_url' =>
                    '/admin/enquiries/'
                        . $enquiry->enquiry_id,

                    /*
                     * Tak perlu ribbon.
                     * Cukup notification bell.
                     */
                    'show_as_ribbon' =>
                    false,

                    'ribbon_duration_seconds' =>
                    7,

                    'shown_count' =>
                    0,

                    'is_read' =>
                    false,

                    'read_at' =>
                    null,

                    'dismissed_at' =>
                    null,

                    'action_completed_at' =>
                    null,

                    'expires_at' =>
                    null,

                    'metadata' => [
                        'enquiry_id' =>
                        $enquiry->enquiry_id,

                        'user_id' =>
                        $enquiry->user_id,

                        'sender_name' =>
                        $enquiry->name,

                        'sender_email' =>
                        $enquiry->email,

                        'sender_phone' =>
                        $enquiry->phone_no,

                        'subject' =>
                        $enquiry->subject,

                        'status' =>
                        $enquiry->status,
                    ],

                    'sent_at' =>
                    now(),
                ]
            );
        }

        Log::info(
            'ADMIN ENQUIRY NOTIFICATIONS CREATED',
            [
                'enquiry_id' =>
                $enquiry->enquiry_id,

                'admin_count' =>
                $admins->count(),

                'admin_ids' =>
                $admins
                    ->pluck('user_id')
                    ->values()
                    ->all(),
            ]
        );
    }

    // ============================================================
    // FORMAT RESPONSE
    // ============================================================

    private function formatEnquiry(
        LsankEnquiry $enquiry
    ): array {
        return [
            'id' =>
            $enquiry->enquiry_id,

            'enquiry_id' =>
            $enquiry->enquiry_id,

            'user_id' =>
            $enquiry->user_id,

            'name' =>
            $enquiry->name,

            'email' =>
            $enquiry->email,

            /*
             * Flutter lama guna "phone".
             * Database guna phone_no.
             * Return kedua-dua supaya compatible.
             */
            'phone' =>
            $enquiry->phone_no,

            'phone_no' =>
            $enquiry->phone_no,

            'subject' =>
            $enquiry->subject,

            'message' =>
            $enquiry->message,

            'status' =>
            $enquiry->status,

            'created_at' =>
            $enquiry->created_at
                ? $enquiry->created_at
                ->format(
                    'Y-m-d H:i:s'
                )
                : null,

            'updated_at' =>
            $enquiry->updated_at
                ? $enquiry->updated_at
                ->format(
                    'Y-m-d H:i:s'
                )
                : null,
        ];
    }
}
