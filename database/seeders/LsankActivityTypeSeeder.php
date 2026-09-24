<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LsankActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $activities = [
            [
                'activity_code' => 'WATER_SPORT',
                'activity_name' => 'Aktiviti Rekreasi Sukan Air',
                'description' => 'Aktiviti rekreasi dan sukan air.',
            ],
            [
                'activity_code' => 'RECREATIONAL_VESSEL',
                'activity_name' => 'Aktiviti Vesel Rekreasi',
                'description' => 'Aktiviti pengoperasian vesel rekreasi.',
            ],
            [
                'activity_code' => 'CAGE',
                'activity_name' => 'Aktiviti Sangkar',
                'description' => 'Aktiviti sangkar dalam badan perairan.',
            ],
            [
                'activity_code' => 'CONSTRUCTION',
                'activity_name' => 'Aktiviti Binaan',
                'description' => 'Aktiviti binaan dalam atau berhampiran badan perairan.',
            ],
            [
                'activity_code' => 'ONE_OFF_WATER_SPORT',
                'activity_name' => 'Aktiviti One-Off Rekreasi Sukan Air',
                'description' => 'Aktiviti rekreasi sukan air secara sekali sahaja.',
            ],
        ];

        foreach ($activities as $activity) {
            DB::table('lsank_activity_types')->updateOrInsert(
                ['activity_code' => $activity['activity_code']],
                [
                    'activity_name' => $activity['activity_name'],
                    'description' => $activity['description'],
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ],
            );
        }
    }
}