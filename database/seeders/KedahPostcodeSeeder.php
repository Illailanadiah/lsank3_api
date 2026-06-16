<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LsankDistrict;
use App\Models\LsankPostcode;

class KedahPostcodeSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Kota Setar' => [
                ['city_name' => 'Alor Setar', 'postcode' => '05000'],
                ['city_name' => 'Anak Bukit', 'postcode' => '06550'],
                ['city_name' => 'Kuala Kedah', 'postcode' => '06600'],
            ],
            'Kuala Muda' => [
                ['city_name' => 'Sungai Petani', 'postcode' => '08000'],
                ['city_name' => 'Bedong', 'postcode' => '08100'],
                ['city_name' => 'Gurun', 'postcode' => '08300'],
            ],
            'Kulim' => [
                ['city_name' => 'Kulim', 'postcode' => '09000'],
                ['city_name' => 'Padang Serai', 'postcode' => '09400'],
                ['city_name' => 'Lunas', 'postcode' => '09600'],
            ],
            'Kubang Pasu' => [
                ['city_name' => 'Jitra', 'postcode' => '06000'],
                ['city_name' => 'Changlun', 'postcode' => '06010'],
                ['city_name' => 'Kodiang', 'postcode' => '06100'],
            ],
            'Baling' => [
                ['city_name' => 'Baling', 'postcode' => '09100'],
                ['city_name' => 'Kupang', 'postcode' => '09200'],
                ['city_name' => 'Kuala Ketil', 'postcode' => '09300'],
            ],
            'Sik' => [
                ['city_name' => 'Sik', 'postcode' => '08200'],
                ['city_name' => 'Jeniang', 'postcode' => '08700'],
            ],
            'Padang Terap' => [
                ['city_name' => 'Kuala Nerang', 'postcode' => '06300'],
                ['city_name' => 'Naka', 'postcode' => '06350'],
            ],
            'Langkawi' => [
                ['city_name' => 'Langkawi', 'postcode' => '07000'],
            ],
            'Yan' => [
                ['city_name' => 'Yan', 'postcode' => '06900'],
                ['city_name' => 'Guar Chempedak', 'postcode' => '08800'],
            ],
            'Bandar Baharu' => [
                ['city_name' => 'Bandar Baharu', 'postcode' => '34950'],
                ['city_name' => 'Serdang', 'postcode' => '09800'],
            ],
            'Pendang' => [
                ['city_name' => 'Pendang', 'postcode' => '06700'],
                ['city_name' => 'Tokai', 'postcode' => '06720'],
            ],
            'Pokok Sena' => [
                ['city_name' => 'Pokok Sena', 'postcode' => '06400'],
            ],
        ];

        foreach ($data as $districtName => $postcodes) {
            $district = LsankDistrict::where('district_name', $districtName)->first();

            if (!$district) {
                continue;
            }

            foreach ($postcodes as $item) {
                LsankPostcode::updateOrCreate(
                    [
                        'district_id' => $district->district_id,
                        'city_name' => $item['city_name'],
                        'postcode' => $item['postcode'],
                    ],
                    [
                        'state_name' => 'Kedah',
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}