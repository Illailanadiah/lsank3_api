<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_applicant_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('applicant_type_id')->primary();
            $table->string('type_code', 30)->unique();
            $table->string('type_name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('lsank_districts', function (Blueprint $table) {
            $table->unsignedTinyInteger('district_code')->nullable()->after('district_id');
            $table->unique('district_code', 'lsank_districts_district_code_unique');
            $table->unique('district_name', 'lsank_districts_district_name_unique');
        });

        Schema::create('lsank_application_categories', function (Blueprint $table) {
            $table->bigIncrements('category_id');
            $table->unsignedBigInteger('application_type_id');
            $table->string('category_code', 60)->unique();
            $table->string('category_name');
            $table->string('reference_prefix', 10);
            $table->boolean('is_one_off')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['application_type_id', 'reference_prefix'],
                'lsank_categories_type_prefix_unique'
            );
            $table->foreign('application_type_id', 'lsank_categories_type_fk')
                ->references('application_type_id')
                ->on('lsank_application_types')
                ->restrictOnDelete();
        });

        Schema::create('lsank_file_number_sequences', function (Blueprint $table) {
            $table->bigIncrements('sequence_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('district_id');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(
                ['category_id', 'district_id'],
                'lsank_file_sequences_category_district_unique'
            );
            $table->foreign('category_id', 'lsank_file_sequences_category_fk')
                ->references('category_id')
                ->on('lsank_application_categories')
                ->restrictOnDelete();
            $table->foreign('district_id', 'lsank_file_sequences_district_fk')
                ->references('district_id')
                ->on('lsank_districts')
                ->restrictOnDelete();
        });

        Schema::table('lsank_applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('applicant_type_id')->nullable()->after('applicant_type');
            $table->unsignedBigInteger('district_id')->nullable()->after('district');
            $table->unsignedBigInteger('category_id')->nullable()->after('district_id');
            $table->boolean('is_one_off')->default(false)->after('category_id');
            $table->unsignedBigInteger('file_running_number')->nullable()->after('is_one_off');

            $table->index('applicant_type_id', 'lsank_applications_applicant_type_idx');
            $table->index('district_id', 'lsank_applications_district_idx');
            $table->index('category_id', 'lsank_applications_category_idx');
            $table->unique(
                ['category_id', 'district_id', 'file_running_number'],
                'lsank_applications_file_number_unique'
            );

            $table->foreign('applicant_type_id', 'lsank_applications_applicant_type_fk')
                ->references('applicant_type_id')
                ->on('lsank_applicant_types')
                ->nullOnDelete();
            $table->foreign('district_id', 'lsank_applications_district_fk')
                ->references('district_id')
                ->on('lsank_districts')
                ->nullOnDelete();
            $table->foreign('category_id', 'lsank_applications_category_fk')
                ->references('category_id')
                ->on('lsank_application_categories')
                ->nullOnDelete();
        });

        $now = now();

        DB::table('lsank_applicant_types')->upsert([
            ['applicant_type_id' => 1, 'type_code' => 'INDIVIDU', 'type_name' => 'Individu', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['applicant_type_id' => 2, 'type_code' => 'SYARIKAT', 'type_name' => 'Syarikat', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['applicant_type_id'], ['type_code', 'type_name', 'is_active', 'updated_at']);

        $districts = [
            1 => 'Kota Setar',
            2 => 'Kuala Muda',
            3 => 'Kulim',
            4 => 'Kubang Pasu',
            5 => 'Baling',
            6 => 'Sik',
            7 => 'Padang Terap',
            8 => 'Langkawi',
            9 => 'Yan',
            10 => 'Bandar Baharu',
            11 => 'Pendang',
            12 => 'Pokok Sena',
        ];

        foreach ($districts as $code => $name) {
            DB::table('lsank_districts')->updateOrInsert(
                ['district_name' => $name],
                ['district_code' => $code, 'state_name' => 'Kedah', 'status' => 'active', 'updated_at' => $now, 'created_at' => $now]
            );
        }

        /*
|--------------------------------------------------------------------------
| Ensure application master types exist
|--------------------------------------------------------------------------
*/

DB::table('lsank_application_types')->updateOrInsert(
    ['type_code' => 'WATER'],
    [
        'type_name' => 'Aktiviti Badan Perairan',
        'description' => 'Permohonan aktiviti badan perairan',
        'status' => 'active',
        'updated_at' => $now,
        'created_at' => $now,
    ]
);

DB::table('lsank_application_types')->updateOrInsert(
    ['type_code' => 'EFFLUENT'],
    [
        'type_name' => 'Aktiviti Pelepasan Efluen',
        'description' => 'Permohonan aktiviti pelepasan efluen',
        'status' => 'active',
        'updated_at' => $now,
        'created_at' => $now,
    ]
);

$waterTypeId = DB::table('lsank_application_types')
    ->whereRaw('UPPER(type_code) = ?', ['WATER'])
    ->value('application_type_id');

$effluentTypeId = DB::table('lsank_application_types')
    ->whereRaw('UPPER(type_code) = ?', ['EFFLUENT'])
    ->value('application_type_id');

/*
 * During migrate --pretend, the inserts above are only displayed
 * and are not actually executed. These fallback IDs allow the
 * migration preview to continue.
 */
if (app()->runningInConsole() && in_array('--pretend', $_SERVER['argv'] ?? [], true)) {
    $waterTypeId ??= 1;
    $effluentTypeId ??= 2;
}

if (!$waterTypeId || !$effluentTypeId) {
    throw new RuntimeException(
        'Gagal menyediakan WATER dan EFFLUENT dalam lsank_application_types.'
    );
}
        $categories = [
            [$waterTypeId, 'WATER_RECREATION', 'Aktiviti Rekreasi Sukan Air', '600-15', false, 10],
            [$waterTypeId, 'WATER_VESSEL', 'Aktiviti Vesel Rekreasi', '600-16', false, 20],
            [$waterTypeId, 'WATER_ONE_OFF', 'Aktiviti Sekali Beri', '600-17', true, 30],
            [$waterTypeId, 'WATER_CAGE', 'Aktiviti Sangkar', '600-18', false, 40],
            [$waterTypeId, 'WATER_CONSTRUCTION', 'Aktiviti Binaan', '600-19', false, 50],
            [$effluentTypeId, 'EFFLUENT_FRESHWATER_AQUACULTURE', 'Akuakultur Air Tawar Dalam Kolam Atau Sangkar', '600-21', false, 60],
            [$effluentTypeId, 'EFFLUENT_MARINE_AQUACULTURE', 'Akuakultur Air Laut Dalam Kolam', '600-22', false, 70],
            [$effluentTypeId, 'EFFLUENT_DEVELOPMENT', 'Aktiviti Pembangunan Atau Kerja Tanah', '600-23', false, 80],
            [$effluentTypeId, 'EFFLUENT_LIVESTOCK_NON_PIG', 'Penternakan Selain Babi', '600-24', false, 90],
            [$effluentTypeId, 'EFFLUENT_PIG_FARMING', 'Penternakan Babi', '600-25', false, 100],
            [$effluentTypeId, 'EFFLUENT_PETS', 'Haiwan Kesayangan', '600-26', false, 110],
            [$effluentTypeId, 'EFFLUENT_QUARRY', 'Aktiviti Kuari', '600-27', false, 120],
            [$effluentTypeId, 'EFFLUENT_VEHICLE_WORKSHOP', 'Bengkel Kenderaan', '600-28', false, 130],
            [$effluentTypeId, 'EFFLUENT_AGRICULTURE', 'Aktiviti Pertanian', '600-29', false, 140],
        ];

        foreach ($categories as [$typeId, $code, $name, $prefix, $oneOff, $sort]) {
            DB::table('lsank_application_categories')->updateOrInsert(
                ['category_code' => $code],
                [
                    'application_type_id' => $typeId,
                    'category_name' => $name,
                    'reference_prefix' => $prefix,
                    'is_one_off' => $oneOff,
                    'is_active' => true,
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->backfillApplications();
        $this->seedSequencesFromExistingReferences();
    }

    private function backfillApplications(): void
    {
        DB::statement(<<<'SQL'
            UPDATE lsank_applications a
            LEFT JOIN lsank_districts d
                ON LOWER(TRIM(d.district_name)) = LOWER(TRIM(a.district))
            SET a.district_id = d.district_id
            WHERE a.district_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE lsank_applications
            SET applicant_type_id = CASE
                WHEN LOWER(TRIM(applicant_type)) IN ('individu', 'individual') THEN 1
                WHEN LOWER(TRIM(applicant_type)) IN ('syarikat', 'company') THEN 2
                ELSE applicant_type_id
            END
            WHERE applicant_type_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE lsank_applications a
            JOIN lsank_application_categories c
              ON a.application_ref_no LIKE CONCAT(c.reference_prefix, '/%')
              OR LOWER(TRIM(a.activity_name)) = LOWER(TRIM(c.category_name))
              OR LOWER(TRIM(a.activity_type)) = LOWER(TRIM(c.category_name))
            SET a.category_id = c.category_id,
                a.is_one_off = c.is_one_off
            WHERE a.category_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE lsank_applications
            SET file_running_number = CAST(SUBSTRING_INDEX(application_ref_no, '/', -1) AS UNSIGNED)
            WHERE application_ref_no REGEXP '^600-[0-9]{2}/[0-9]{1,2}/[0-9]+$'
              AND file_running_number IS NULL
        SQL);
    }

    private function seedSequencesFromExistingReferences(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO lsank_file_number_sequences
                (category_id, district_id, last_number, created_at, updated_at)
            SELECT category_id, district_id, MAX(file_running_number), NOW(), NOW()
            FROM lsank_applications
            WHERE category_id IS NOT NULL
              AND district_id IS NOT NULL
              AND file_running_number IS NOT NULL
            GROUP BY category_id, district_id
            ON DUPLICATE KEY UPDATE
                last_number = GREATEST(last_number, VALUES(last_number)),
                updated_at = VALUES(updated_at)
        SQL);
    }

    public function down(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            $table->dropForeign('lsank_applications_applicant_type_fk');
            $table->dropForeign('lsank_applications_district_fk');
            $table->dropForeign('lsank_applications_category_fk');
            $table->dropUnique('lsank_applications_file_number_unique');
            $table->dropIndex('lsank_applications_applicant_type_idx');
            $table->dropIndex('lsank_applications_district_idx');
            $table->dropIndex('lsank_applications_category_idx');
            $table->dropColumn([
                'applicant_type_id',
                'district_id',
                'category_id',
                'is_one_off',
                'file_running_number',
            ]);
        });

        Schema::dropIfExists('lsank_file_number_sequences');
        Schema::dropIfExists('lsank_application_categories');

        Schema::table('lsank_districts', function (Blueprint $table) {
            $table->dropUnique('lsank_districts_district_code_unique');
            $table->dropUnique('lsank_districts_district_name_unique');
            $table->dropColumn('district_code');
        });

        Schema::dropIfExists('lsank_applicant_types');
    }
};
