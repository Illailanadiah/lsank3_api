<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<title>{{ $license->license_no ?? 'Lesen Aktiviti LSANK' }}</title>
<style>
@page{size:611pt 1007pt;margin:0}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font-family:"DejaVu Sans",sans-serif;color:#000;font-size:10pt}
.page{position:relative;page-break-after:always}
.page:last-child{page-break-after:auto}
.form-page{height:917pt;padding:42pt 58pt 48pt;overflow:hidden}
.reg{position:absolute;top:35pt;right:48pt;font-size:8pt;font-weight:bold;white-space:nowrap}
.center{text-align:center}.b{font-weight:bold}.i{font-style:italic}.u{text-decoration:underline}
.head{margin-top:42pt;line-height:1.12}
.crest{width:60pt;height:60pt;margin:-39pt auto 7pt;object-fit:contain}
.crest-space{height:30pt}
.title{margin-top:5pt}
.lic-title{margin-top:5pt;font-weight:bold;line-height:1.1}
.qr{position:absolute;top:28pt;left:39pt;width:76pt;text-align:center;z-index:5}
.qr img{display:block;width:66pt;height:66pt;margin:auto}
.qr-label{margin-top:2pt;font-size:5.5pt}
.identity,.details,.summary,.issue{width:100%;border-collapse:collapse}
.identity{margin-top:5pt;font-size:8.2pt;table-layout:fixed}
.identity td{padding-bottom:1pt;vertical-align:bottom}
.law{margin:6pt 0 7pt;font-size:8.2pt;line-height:1.12}
.details{font-size:7.8pt;line-height:1.05}
.details td,.summary td{padding:1pt 0;vertical-align:top}
.no{width:13pt}.label{width:210pt}.value{font-weight:bold}.sub{display:block;font-style:italic}
.identity .serial-label{width:52pt}.identity .serial-value{width:82pt}
.identity .licence-label{width:72pt}.identity .licence-value{text-align:right;white-space:nowrap}
.legal{margin:3pt 7pt 3pt 13pt;font-size:7.1pt;line-height:1.06;text-align:justify}
.summary{font-size:7.5pt;line-height:1.05}
.summary .condition-value{font-weight:bold}
.issue{margin-top:4pt;font-size:7.5pt}
.signature-area{position:relative;height:76pt;margin-top:4pt}
.seal{position:absolute;left:42pt;top:2pt;width:70pt;height:70pt;object-fit:contain}
.signature{position:absolute;right:4pt;top:5pt;width:270pt;text-align:center;font-size:7.2pt;line-height:1.1}
.signature img{display:block;width:130pt;height:38pt;margin:0 auto -2pt;object-fit:contain}
.attachment{padding:40pt 48pt 36pt;font-size:8.2pt}
.attachment .reg{top:25pt;right:48pt}
.attachment-title{margin-top:8pt;text-align:center;font-weight:bold;line-height:1.15;page-break-after:avoid}
.attachment-title .main{font-size:9.5pt}
.conditions{margin:10pt 0 0 20pt;padding:0;line-height:1.18;text-align:justify;page-break-inside:auto}
.conditions li{margin-bottom:3pt;padding-left:4pt;page-break-inside:avoid}
.conditions ul{margin:4pt 0 3pt 17pt;padding:0}
.conditions ul li{margin-bottom:2pt}
.verify{position:absolute;left:45pt;right:45pt;bottom:14pt;text-align:center;color:#444;font-size:5.5pt;word-wrap:break-word}
</style>
</head>
<body>
@php
$fmt=static function($v):string{if(blank($v))return'-';try{$d=$v instanceof \Carbon\CarbonInterface?$v->copy():\Carbon\Carbon::parse($v);$m=[1=>'JANUARI',2=>'FEBRUARI',3=>'MAC',4=>'APRIL',5=>'MEI',6=>'JUN',7=>'JULAI',8=>'OGOS',9=>'SEPTEMBER',10=>'OKTOBER',11=>'NOVEMBER',12=>'DISEMBER'];return $d->format('d').' '.$m[(int)$d->format('n')].' '.$d->format('Y');}catch(\Throwable $e){return'-';}};
$pick=static function(...$vs){foreach($vs as $v){if($v===null)continue;if(is_array($v)||is_object($v)){if(!empty((array)$v))return$v;continue;}if(trim((string)$v)!=='')return$v;}return'-';};
$application=$application??$license->application;
$normalise=static fn($value)=>mb_strtolower(trim((string)($value??'')));
$attribute=static function($model,string $key){
    if($model instanceof \Illuminate\Database\Eloquent\Model){
        $attributes=$model->getAttributes();
        return $attributes[$key]??null;
    }
    return data_get($model,$key);
};
$typeSignals=$normalise(implode(' ',array_filter([
    $attribute($application,'application_type'),
    $attribute($application,'license_type'),
    $attribute($application,'application_category'),
    $attribute($application,'activity_type'),
    $license->license_type??null,
    $license->license_no??null,
    $license->file_no??null,
],static fn($value)=>filled($value))));

/*
 * Do not depend on one relationship to decide the licence module.  Historical
 * records may only have EFL in the licence number or an effluent category.
 */
$isEffluent=str_contains($typeSignals,'effluent')
    ||str_contains($typeSignals,'efluen')
    ||str_contains($typeSignals,'pelepasan')
    ||str_contains($typeSignals,'lsank/efl/')
    ||preg_match('/(?:^|\s)600-(?:2[1-9])(?:\/|\s|$)/',$typeSignals)===1;
$module=$isEffluent?'effluent':'water';
$moduleRelation=$isEffluent?'effluent':'waterBody';
$moduleRecord=null;
if($application instanceof \Illuminate\Database\Eloquent\Model){
    if($application->relationLoaded($moduleRelation)){
        $moduleRecord=$application->getRelation($moduleRelation);
    }elseif(method_exists($application,$moduleRelation)){
        try{$moduleRecord=$application->{$moduleRelation}()->first();}catch(\Throwable $e){$moduleRecord=null;}
    }
}
$categoryRecord=null;
if($application instanceof \Illuminate\Database\Eloquent\Model){
    if($application->relationLoaded('category')){
        $categoryRecord=$application->getRelation('category');
    }elseif(method_exists($application,'category')){
        try{$categoryRecord=$application->category()->first();}catch(\Throwable $e){$categoryRecord=null;}
    }
}
$typeRecord=null;
$typeRelation=$isEffluent?'serviceType':'activityType';
if($moduleRecord instanceof \Illuminate\Database\Eloquent\Model){
    if($moduleRecord->relationLoaded($typeRelation)){
        $typeRecord=$moduleRecord->getRelation($typeRelation);
    }elseif(method_exists($moduleRecord,$typeRelation)){
        try{$typeRecord=$moduleRecord->{$typeRelation}()->first();}catch(\Throwable $e){$typeRecord=null;}
    }
}
$profile=$isEffluent?[
    'regulation_ms'=>$effluentRegulationTitleMalay??'PERATURAN-PERATURAN SUMBER AIR KEDAH (AKTIVITI PELEPASAN EFLUEN)',
    'regulation_en'=>$effluentRegulationTitleEnglish??'KEDAH WATER RESOURCES (EFFLUENT DISCHARGE ACTIVITIES) REGULATIONS',
    'licence_ms'=>'LESEN AKTIVITI PELEPASAN EFLUEN',
    'licence_en'=>'EFFLUENT DISCHARGE ACTIVITIES LICENCE',
    'general_title'=>'SYARAT-SYARAT UMUM AKTIVITI PELEPASAN EFLUEN',
    'special_title'=>'AKTIVITI PELEPASAN EFLUEN',
    'activity_label_ms'=>'Aktiviti / Punca Pelepasan Efluen',
    'activity_label_en'=>'Effluent discharge activity / source',
    'measure_label_ms'=>'Kadar Alir Pelepasan',
    'measure_label_en'=>'Discharge flow rate',
    'location_label_ms'=>'Lokasi Pelepasan Efluen',
    'location_label_en'=>'Effluent discharge location',
    'standard_ms'=>$effluentStandardConditionText??'SYARAT-SYARAT STANDARD LESEN AKTIVITI PELEPASAN EFLUEN YANG BERKUAT KUASA',
    'legal_ms'=>$effluentLegalTextMalay??'Melainkan dibatalkan atau digantung menurut Enakmen Sumber Air Kedah 2008 dan peraturan-peraturan yang berkuat kuasa.',
    'legal_en'=>$effluentLegalTextEnglish??'Unless revoked or suspended pursuant to the Kedah Water Resources Enactment 2008 and the regulations in force.',
]:[
    'regulation_ms'=>'PERATURAN-PERATURAN SUMBER AIR KEDAH (AKTIVITI BADAN PERAIRAN) 2015',
    'regulation_en'=>'KEDAH WATER RESOURCES (WATER BODIES ACTIVITIES) REGULATION 2015',
    'licence_ms'=>'LESEN AKTIVITI BADAN PERAIRAN',
    'licence_en'=>'WATER BODIES ACTIVITIES LICENCE',
    'general_title'=>'SYARAT-SYARAT UMUM AKTIVITI BADAN PERAIRAN',
    'special_title'=>'AKTIVITI BADAN PERAIRAN',
    'activity_label_ms'=>'Aktiviti yang Dilesenkan',
    'activity_label_en'=>'Licensed activity',
    'measure_label_ms'=>'Butiran Aktiviti',
    'measure_label_en'=>'Activity details',
    'location_label_ms'=>'Kawasan Aktiviti',
    'location_label_en'=>'Activity area',
    'standard_ms'=>'PERATURAN 8 PERATURAN-PERATURAN SUMBER AIR KEDAH (AKTIVITI BADAN PERAIRAN) 2015',
    'legal_ms'=>'Melainkan dibatalkan mengikut seksyen 52, Enakmen Sumber Air Kedah 2008, atau ditamatkan mengikut seksyen 53, Enakmen Sumber Air Kedah 2008 dan diperbaharui mengikut peraturan 5, Peraturan-Peraturan Sumber Air Kedah (Aktiviti Badan Perairan) 2015',
    'legal_en'=>'Unless revoked in accordance with section 52, Kedah Water Resources Enactment 2008, or terminated in accordance with section 53, Kedah Water Resources Enactment 2008 and renewed in accordance with regulation 5, Kedah Water Resources (Water Body Activities) Regulations 2015',
];
$holder=$pick($license->holder_name,data_get($application,'company_name'),data_get($application,'applicant_name'));
$address=$pick(data_get($application,'business_address'),data_get($application,'company_address'),data_get($application,'address'));
$moduleValue=static function(array $keys)use($moduleRecord,$application,$pick,$module){
    $values=[];
    foreach($keys as $key){
        $values[]=data_get($moduleRecord,$key);
        $values[]=data_get($application,'submitted_data.'.$key);
        $values[]=data_get($application,'submitted_data.'.$module.'.'.$key);
        $values[]=data_get($application,'draft_data.'.$key);
        $values[]=data_get($application,'draft_data.'.$module.'.'.$key);
        $values[]=data_get($application,$key);
    }
    return $pick(...$values);
};
$activity=$isEffluent
    ?$pick(
        data_get($categoryRecord,'category_name'),
        data_get($typeRecord,'service_name'),
        data_get($typeRecord,'service_type_name'),
        data_get($moduleRecord,'service_type_name'),
        data_get($application,'activity_name'),
        $license->activity_name
    )
    :$pick(
        data_get($categoryRecord,'category_name'),
        data_get($typeRecord,'activity_name'),
        data_get($typeRecord,'activity_type_name'),
        data_get($moduleRecord,'activity_type_name'),
        data_get($application,'activity_name'),
        $license->activity_name
    );
$activitySignal=$normalise(implode(' ',array_filter([
    $activity,
    data_get($categoryRecord,'category_code'),
    data_get($categoryRecord,'category_name'),
    data_get($application,'activity_type'),
    data_get($application,'activity_name'),
    data_get($moduleRecord,'construction_shape'),
],static fn($value)=>filled($value))));
$oneOffRaw=data_get($application,'is_one_off',data_get($moduleRecord,'is_one_off',data_get($application,'submitted_data.is_one_off',false)));
$isOneOff=filter_var($oneOffRaw,FILTER_VALIDATE_BOOLEAN);
$activityKey=match(true){
    $isEffluent=>'effluent',
    str_contains($activitySignal,'vesel')=>'vessel',
    str_contains($activitySignal,'sangkar')=>'cage',
    str_contains($activitySignal,'binaan')=>'construction',
    $isOneOff=>'water_sport_one_off',
    default=>'water_sport',
};
if(!$isEffluent){
    $profile['special_title']=match($activityKey){
        'vessel'=>'AKTIVITI VESEL REKREASI',
        'cage'=>'AKTIVITI SANGKAR',
        'construction'=>'AKTIVITI BINAAN',
        'water_sport_one_off'=>'AKTIVITI REKREASI SUKAN AIR (SEKALI BERI)',
        default=>'AKTIVITI REKREASI SUKAN AIR',
    };
    [$profile['measure_label_ms'],$profile['measure_label_en'],$profile['location_label_ms'],$profile['location_label_en']]=match($activityKey){
        'construction'=>['Keluasan Binaan yang Dilesenkan','Licensed construction area','Kawasan Binaan','Construction area'],
        'cage'=>['Bilangan / Keluasan Sangkar','Number / area of cages','Kawasan Sangkar','Cage area'],
        'vessel'=>['Butiran Vesel yang Dilesenkan','Licensed vessel details','Kawasan Operasi Vesel','Vessel operating area'],
        default=>['Butiran Peralatan / Aktiviti','Equipment / activity details','Kawasan Aktiviti Sukan Air','Water-sports activity area'],
    };
}
$location=$pick(
    $license->activity_location,
    data_get($moduleRecord,'activity_location'),
    data_get($application,'activity_location'),
    data_get($application,'site_address')
);
$measure=$isEffluent
    ?$moduleValue(['flow_rate','discharge_flow_rate'])
    :match($activityKey){
        'construction'=>$moduleValue(['built_up_area','construction_area','area','site_area']),
        'cage'=>$moduleValue(['cage_count','number_of_cages','cage_area','area']),
        'vessel'=>$moduleValue(['vessel_details','vessels','equipment','equipment_details']),
        default=>$moduleValue(['recreation_details','equipment','equipment_details','activity_details']),
    };
$measureUnit=$isEffluent?($effluentFlowRateUnit??'m³/hari')
    :($activityKey==='construction'||$activityKey==='cage'?'m²':'');
$latitude=$pick($license->latitude,data_get($moduleRecord,'latitude'),data_get($application,'latitude'));
$longitude=$pick($license->longitude,data_get($moduleRecord,'longitude'),data_get($application,'longitude'));
$coords=($latitude!=='-'&&$longitude!=='-')?'('.$latitude.'° N, '.$longitude.'° E)':'';
$serial=str_pad((string)($license->license_id??''),5,'0',STR_PAD_LEFT);
$receipt=$receiptNo??null;
if(blank($receipt)&&filled($license->application_id)){
    try{
        $receipt=\Illuminate\Support\Facades\DB::table('lsank_receipts as r')
            ->join('lsank_invoices as i','i.invoice_id','=','r.invoice_id')
            ->where('i.application_id',$license->application_id)
            ->where('r.status','valid')
            ->whereRaw("LOWER(TRIM(COALESCE(i.payment_type, ''))) IN (?, ?)",['fi lesen','license fee'])
            ->latest('r.receipt_id')
            ->value('r.receipt_no');
    }catch(\Throwable $e){$receipt=null;}
}
$receipt=$pick($receipt,data_get($application,'license_fee_receipt.receipt_no'));
$registrationNo=$pick($license->file_no,$registrationNo??null,data_get($application,'application_ref_no'));

$applicationDetails=$isEffluent?[
    ['Komposisi Efluen','Effluent composition',$moduleValue(['composition','effluent_composition'])],
    ['Kekerapan Pelepasan','Discharge frequency',$moduleValue(['frequency','discharge_frequency'])],
    ['Kaedah Persampelan','Sampling method',$moduleValue(['sampling_method'])],
    ['Pelan Kontingensi','Contingency plan',$moduleValue(['contingency_plan'])],
    ['Kaedah Pelupusan','Disposal method',$moduleValue(['disposal_method'])],
]:match($activityKey){
    'construction'=>[
        ['Bentuk Binaan','Construction type',$moduleValue(['construction_shape','construction_type'])],
        ['Butiran Binaan','Construction details',$moduleValue(['construction_details','activity_details'])],
    ],
    'cage'=>[
        ['Jenis Ternakan','Cultured species',$moduleValue(['species','culture_species','activity_details'])],
        ['Bilangan Sangkar','Number of cages',$moduleValue(['cage_count','number_of_cages'])],
    ],
    'vessel'=>[
        ['Hari Operasi','Operating days',$moduleValue(['operating_days'])],
        ['Waktu Operasi','Operating hours',$moduleValue(['operating_time','operating_hours'])],
        ['Senarai Vesel','List of vessels',$moduleValue(['vessels','vessel_details','equipment'])],
    ],
    default=>[
        ['Hari Operasi','Operating days',$moduleValue(['operating_days'])],
        ['Waktu Operasi','Operating hours',$moduleValue(['operating_time','operating_hours'])],
        ['Peralatan Sukan Air','Water-sports equipment',$moduleValue(['recreation_details','equipment','equipment_details'])],
        ['Butiran Aktiviti','Activity details',$moduleValue(['activity_details'])],
    ],
};
$applicationDetails=collect($applicationDetails)
    ->filter(static fn($row)=>filled($row[2])&&$row[2]!=='-')
    ->values();
$displayValue=static function($value):string{
    if($value instanceof \Illuminate\Support\Collection)$value=$value->all();
    if($value instanceof \Illuminate\Contracts\Support\Arrayable)$value=$value->toArray();
    if($value instanceof \JsonSerializable)$value=$value->jsonSerialize();
    if(is_array($value)){
        $flat=collect($value)
            ->flatten()
            ->filter(static fn($item)=>is_scalar($item)&&filled($item))
            ->map(static fn($item)=>is_bool($item)?($item?'Ya':'Tidak'):trim((string)$item))
            ->implode(', ');
        return $flat!==''?$flat:'-';
    }
    if(is_object($value))return method_exists($value,'__toString')?trim((string)$value):'-';
    if(is_bool($value))return $value?'Ya':'Tidak';
    return filled($value)?trim((string)$value):'-';
};
$holderText=$displayValue($holder);
$addressText=$displayValue($address);
$activityText=$displayValue($activity);
$locationText=$displayValue($location);
$measureText=$displayValue($measure);
$receiptText=$displayValue($receipt);
$registrationNoText=$displayValue($registrationNo);
$waterDefaults=[
'Pihak pengusaha hendaklah mematuhi peruntukan sebarang undang-undang bertulis termasuk Akta, Enakmen, Ordinan, Undang-undang Kecil, Peraturan-peraturan dan Kaedah-kaedah dan semua undang-undang yang terpakai ketika ini dan mana-mana undang-undang yang digubal berikutnya.',
'Pemegang lesen hendaklah mematuhi Enakmen Sumber Air Kedah 2008 dan semua peraturan yang digubal di bawahnya.',
'Pemegang lesen adalah tertakluk kepada arahan, perintah, syarat dan syarat-syarat tambahan yang suai manfaat dikeluarkan oleh Lembaga Sumber Air Negeri Kedah dari masa ke semasa atau pada ketika perintah pada musim kemarau serta bagi tujuan perlindungan, pemeliharaan dan pemuliharaan sumber-sumber air.',
'Tempoh sah laku lesen adalah berkuat kuasa mengikut tarikh yang tertera pada lesen.',
'Lembaga Sumber Air Negeri Kedah boleh membatalkan atau menggantungkan lesen dengan serta merta sekiranya pemegang lesen gagal mematuhi mana-mana arahan, perintah, syarat atau syarat tambahan yang ditetapkan dari semasa ke semasa.',
'Sekiranya berlaku pelanggaran syarat / arahan lesen, denda atau penalti akan dikenakan mengikut peruntukan Enakmen Sumber Air Kedah 2008.',
'Pegawai Lembaga Sumber Air Negeri Kedah hendaklah mempunyai akses bebas untuk memasuki premis pemegang lesen pada bila-bila masa bagi memastikan syarat-syarat Lembaga dipatuhi dari semasa ke semasa.',
'Pemegang lesen hendaklah memastikan aktiviti yang dijalankan tidak mencemarkan sumber air yang terlibat. Oleh itu, pihak pemegang lesen hendaklah mengawal dan memastikan tiada bahan-bahan yang boleh mencemarkan sumber air memasuki mana-mana badan perairan.',
'Pemegang lesen hendaklah memaklumkan kepada Lembaga Sumber Air Negeri Kedah sebarang kejadian atau kemalangan yang mungkin akan mengakibatkan kesan-kesan buruk terhadap mana-mana sumber air dan persekitarannya.',
'Lembaga Sumber Air Negeri Kedah hendaklah tidak akan menanggung apa-apa kerugian atau kerosakan disebabkan oleh apa-apa tindakan, tinggalan atau salah laku pemegang lesen.',
'Sebarang kos bagi kerja-kerja pemuliharaan kualiti air dan kerosakan struktur akibat daripada aktiviti yang dijalankan hendaklah ditanggung sepenuhnya oleh pemegang lesen mengikut arahan Lembaga Sumber Air Negeri Kedah.',
'Pemegang lesen hendaklah mematuhi syarat-syarat tambahan yang dikemukakan oleh Lembaga Sumber Air Negeri Kedah dari semasa ke semasa.',
'Pemegang lesen hendaklah mempamerkan lesen ini di premis dan/atau di tempat dan/atau satu sudut terbuka yang mudah dilihat.',
'Lesen ini tidak boleh dipindah milik.'
];
$effluentDefaults=[
'Pihak pengusaha hendaklah mematuhi semua undang-undang bertulis, peraturan, kaedah, standard dan arahan yang berkuat kuasa bagi aktiviti pelepasan efluen.',
'Pemegang lesen hendaklah mematuhi Enakmen Sumber Air Kedah 2008 dan semua peraturan yang digubal di bawahnya.',
'Pelepasan efluen hanya dibenarkan bagi aktiviti, punca, lokasi, kadar alir dan tempoh yang dinyatakan dalam lesen ini.',
'Pemegang lesen hendaklah memastikan kualiti efluen yang dilepaskan mematuhi had dan standard yang ditetapkan oleh pihak berkuasa.',
'Pemegang lesen hendaklah melaksanakan persampelan, pemantauan dan penyimpanan rekod mengikut kaedah serta kekerapan yang diluluskan.',
'Sebarang perubahan pada komposisi, kadar alir, punca atau lokasi pelepasan hendaklah mendapat kelulusan bertulis Lembaga terlebih dahulu.',
'Pelan kontingensi hendaklah dilaksanakan dengan segera sekiranya berlaku tumpahan, kegagalan sistem rawatan atau pelepasan luar biasa.',
'Pemegang lesen hendaklah memaklumkan Lembaga dengan segera mengenai apa-apa kejadian yang boleh menjejaskan sumber air atau alam sekitar.',
'Pegawai Lembaga hendaklah diberi akses munasabah ke premis, titik pelepasan, rekod dan kemudahan berkaitan bagi tujuan pemeriksaan.',
'Semua kos kawalan pencemaran, pemulihan kualiti air dan pembaikan kerosakan akibat pelepasan hendaklah ditanggung oleh pemegang lesen.',
'Lembaga boleh mengenakan syarat tambahan, menggantung atau membatalkan lesen sekiranya syarat lesen tidak dipatuhi.',
'Pemegang lesen hendaklah mempamerkan lesen ini di premis atau di tempat yang mudah dilihat.',
'Lesen ini tidak boleh dipindah milik.'
];
$defaults=$isEffluent?$effluentDefaults:$waterDefaults;
$specialDefaults=match($activityKey){
    'water_sport','water_sport_one_off'=>[
        'Pemegang lesen tidak boleh meninggalkan peralatan-peralatan sukan air di persisiran pantai atau di atas badan perairan setelah tamat waktu operasi.',
        ['text'=>'Pemegang lesen dikehendaki memastikan:','items'=>[
            'Aktiviti sukan air dijalankan di kawasan yang telah dibenarkan sahaja;',
            'Tiada aktiviti menyelenggara peralatan-peralatan sukan air dilakukan di atas atau di dalam badan perairan atau di persisiran pantai.',
        ]],
        'Pemakaian jaket keselamatan adalah diwajibkan sepanjang aktiviti dijalankan.',
        'Pemegang lesen perlu menjelaskan kepada pelanggan berkenaan had kawasan aktiviti yang dibenarkan beroperasi.',
        'Pemegang lesen hendaklah memastikan pelanggan mengisi borang ganti rugi dan pelepasan tanggungan sebelum memulakan aktiviti.',
        'Pemegang lesen hendaklah bertanggungjawab terhadap sebarang kemalangan yang berlaku akibat daripada aktiviti yang dijalankan.',
        'Pelesen diwajibkan untuk mempamerkan pelekat lesen yang dikeluarkan oleh pihak LSANK pada setiap peralatan yang didaftarkan. Kegagalan berbuat demikian diklasifikasikan sebagai melanggar syarat lesen.',
    ],
    'vessel'=>[
        'Pemegang lesen hanya dibenarkan untuk beroperasi dalam kawasan permohonan sahaja dan tidak melebihi kawasan aktiviti yang dinyatakan dalam lesen.',
        'Pemegang lesen hendaklah memastikan semua pemandu bot yang dilantik ialah orang yang kompeten dan diiktiraf melalui lesen pelaut yang dikeluarkan oleh Jabatan Laut Malaysia.',
        'Pemakaian jaket keselamatan adalah diwajibkan sepanjang aktiviti dijalankan.',
        'Pelesen hendaklah memastikan bilangan penumpang tidak melebihi jumlah yang didaftarkan dalam Passenger Certificate.',
        'Pemegang lesen perlu menyediakan rekod daftar nama pelanggan yang menggunakan perkhidmatan yang disediakan.',
        'Pemegang lesen tidak dibenarkan menyimpan petrol atau diesel di persisiran pantai bagi mengelakkan tumpahan minyak berlaku di kawasan tersebut.',
        'Pemegang lesen tidak dibenarkan meninggalkan tong yang berisi diesel atau tong kosong bekas diesel di dalam bot setelah tamat operasi.',
        'Pemegang lesen hendaklah memastikan tiada aktiviti penyelenggaraan peralatan rekreasi vesel dilakukan di atas atau di dalam badan perairan atau di persisiran pantai.',
        'Pelesen diwajibkan untuk mempamerkan pelekat lesen yang dikeluarkan oleh pihak LSANK pada setiap peralatan yang didaftarkan. Kegagalan berbuat demikian diklasifikasikan sebagai melanggar syarat lesen.',
    ],
    'cage'=>[
        'Pemegang lesen perlu memastikan aktiviti yang dijalankan berada dalam kelas atau kategori yang dinyatakan dalam lesen.',
        'Sekiranya berlaku kejadian yang mengakibatkan sangkar hanyut, pelesen dikehendaki bertanggungjawab untuk mengalihkan sangkar hanyut tersebut dengan kos sendiri.',
        'Jika terdapat arahan daripada Kerajaan Negeri untuk membuka atau mengalihkan struktur tersebut pada masa akan datang atas apa-apa alasan, arahan tersebut mestilah dipatuhi dan kos bagi kerja tersebut hendaklah ditanggung sepenuhnya oleh pemegang lesen, jika berkaitan.',
    ],
    'construction'=>[
        'Pemegang lesen hendaklah memastikan aktiviti binaan yang dijalankan adalah seperti pelan yang diluluskan.',
        'Pemegang lesen hendaklah memastikan kaedah penyimpanan bekalan diesel, jika ada, tidak mencemarkan sumber air berhampiran dan dilengkapi takungan berkapasiti 110% daripada simpanan diesel, berbumbung serta terletak sekurang-kurangnya 10 meter dari badan air.',
        ['text'=>'Pemegang lesen dikehendaki memastikan:','items'=>[
            'Penahan enap dan kemudahan pengawalan pemendapan lain diadakan dengan mencukupi serta diselenggarakan dengan sepatutnya;',
            'Struktur penahan diadakan jika perlu;',
            'Cerun dilindungi dengan secukupnya bagi mengelakkan hakisan; dan',
            'Segala langkah yang berkesan diambil untuk mengawal kelodak yang disebabkan oleh aktiviti binaan.',
        ]],
        'Pemegang lesen tidak dibenarkan membina atau meletakkan struktur yang boleh mengganggu atau melencongkan aliran semula jadi sungai kecuali dengan kebenaran Pengarah Sumber Air.',
        'Pemegang lesen tidak dibenarkan membina bangunan atau struktur sementara di dalam rizab sungai, zon banjir, zon penampan atau zon perlindungan kecuali dengan kebenaran Pengarah Sumber Air.',
        'Pemegang lesen dikehendaki menjalankan kerja penyelenggaraan secara berkala terhadap struktur kawalan pencemaran supaya berfungsi dengan lebih berkesan.',
        'Jika terdapat arahan daripada Kerajaan Negeri untuk membuka atau mengalihkan struktur tersebut pada masa akan datang atas apa-apa alasan, arahan tersebut mestilah dipatuhi dan kos bagi kerja tersebut hendaklah ditanggung sepenuhnya oleh pemegang lesen, jika berkaitan.',
    ],
    default=>[],
};
$toConditions=static function($source):array{
    if(blank($source))return[];
    if(is_string($source)){
        $decoded=json_decode($source,true);
        if(json_last_error()===JSON_ERROR_NONE&&is_array($decoded))$source=$decoded;
        else return array_values(array_filter(
            preg_split('/\r\n|\r|\n/',trim($source)),
            static fn($line)=>filled(trim((string)$line))
        ));
    }
    if($source instanceof \Illuminate\Support\Collection)$source=$source->all();
    if(is_object($source))$source=(array)$source;
    if(!is_array($source))return[];
    $isSingle=array_key_exists('text',$source)
        ||array_key_exists('condition',$source)
        ||array_key_exists('description',$source);
    return $isSingle?[$source]:array_values($source);
};
$technicalReportRecord=$technicalReport??null;
if($technicalReportRecord===null&&$application instanceof \Illuminate\Database\Eloquent\Model){
    foreach(['technicalReport','technical_report'] as $reportRelation){
        if($application->relationLoaded($reportRelation)){
            $technicalReportRecord=$application->getRelation($reportRelation);
            break;
        }
        if(method_exists($application,$reportRelation)){
            try{$technicalReportRecord=$application->{$reportRelation}()->latest()->first();}catch(\Throwable $e){$technicalReportRecord=null;}
            if($technicalReportRecord!==null)break;
        }
    }
}
$generalSources=[
    $generalConditions??[],
    data_get($application,'review_data.license.general_conditions',[]),
    data_get($application,'submitted_data.license.general_conditions',[]),
    data_get($technicalReportRecord,'general_conditions',[]),
    data_get($technicalReportRecord,'syarat_umum',[]),
];
$specialSources=[
    $specialConditions??[],
    data_get($application,'review_data.license.license_conditions',[]),
    data_get($application,'review_data.license.special_conditions',[]),
    data_get($application,'submitted_data.license.license_conditions',[]),
    data_get($application,'submitted_data.license.special_conditions',[]),
    data_get($technicalReportRecord,'special_conditions',[]),
    data_get($technicalReportRecord,'syarat_khusus',[]),
    data_get($technicalReportRecord,'license_conditions',[]),
];
$additionalSources=[
    $additionalConditions??[],
    data_get($application,'review_data.license.additional_conditions',[]),
    data_get($application,'review_data.license.additional_license_conditions',[]),
    data_get($application,'submitted_data.license.additional_conditions',[]),
    data_get($application,'submitted_data.license.additional_license_conditions',[]),
    data_get($technicalReportRecord,'additional_conditions',[]),
    data_get($technicalReportRecord,'syarat_tambahan',[]),
];
$conditionText=static function($condition)use($displayValue):string{
    if(is_array($condition)||is_object($condition)){
        $value=data_get($condition,'text');
        if(blank($value))$value=data_get($condition,'condition');
        if(blank($value))$value=data_get($condition,'description');
        if(blank($value))$value=data_get($condition,'title');
        return $displayValue($value);
    }
    return $displayValue($condition);
};
$conditionChildren=static function($condition):array{
    if(!is_array($condition)&&!is_object($condition))return[];
    $children=data_get($condition,'items',data_get($condition,'children',data_get($condition,'subconditions',[])));
    if($children instanceof \Illuminate\Support\Collection)$children=$children->all();
    return is_array($children)?$children:[];
};
$general=collect($generalSources)->flatMap($toConditions)->filter(fn($x)=>filled($conditionText($x)))->unique(fn($x)=>mb_strtolower($conditionText($x)))->values();
$special=collect($specialSources)->flatMap($toConditions)->filter(fn($x)=>filled($conditionText($x)))->unique(fn($x)=>mb_strtolower($conditionText($x)))->values();
$additional=collect($additionalSources)->flatMap($toConditions)->filter(fn($x)=>filled($conditionText($x)))->unique(fn($x)=>mb_strtolower($conditionText($x)))->values();
if($general->isEmpty())$general=collect($defaults);
if($special->isEmpty())$special=collect($specialDefaults);
if($activityKey==='water_sport_one_off'&&($specialConditions??null)===null){
    $special=$special->merge([
        'Pemegang lesen hendaklah mematuhi tempoh operasi yang dibenarkan, iaitu dari 7.00 pagi hingga 7.00 petang.',
        'Pemegang lesen hendaklah menyediakan daftar nama pasukan, nama peserta dan kewarganegaraan peserta yang menyertai acara pertandingan untuk tujuan rekod serta menyerahkannya kepada pihak Lembaga.',
    ])->unique(fn($x)=>mb_strtolower($conditionText($x)))->values();
}
@endphp

<section class="page form-page">
<div class="reg">No. Daftar : {{ $registrationNoText }}</div>
<div class="qr">@if(!empty($qrDataUri))<img src="{{ $qrDataUri }}" alt="Kod QR"><div class="qr-label">IMBAS UNTUK PENGESAHAN</div>@endif</div>
<div class="head center">
@if(!empty($crestDataUri))<img class="crest" src="{{ $crestDataUri }}" alt="Jata Negeri Kedah">@else<div class="crest-space"></div>@endif
<div>JADUAL KETIGA/ <span class="i">THIRD SCHEDULE</span></div><div>[Subperaturan 4(2)/ <span class="i">Subregulation 4(2)</span>]</div>
<div class="title b">BORANG/ <span class="i">FORM B</span></div>
<div class="title">ENAKMEN SUMBER AIR KEDAH 2008<br><span class="i">KEDAH WATER RESOURCES ENACTMENT 2008</span></div>
<div class="title">{{ $profile['regulation_ms'] }}<br><span class="i">{{ $profile['regulation_en'] }}</span></div>
<div class="lic-title">{{ $profile['licence_ms'] }}<br><span class="i">{{ $profile['licence_en'] }}</span></div>
</div>
<table class="identity"><tr><td>ASAL<br><span class="i">Original</span></td><td></td><td></td><td></td></tr><tr><td class="serial-label">No. Siri<br><span class="i">Serial No.</span></td><td class="serial-value b">{{ $serial }}</td><td class="licence-label">No. Lesen<br><span class="i">Licence No.</span></td><td class="licence-value b">{{ $license->license_no??'-' }}</td></tr></table>
<div class="law">MENURUT SEKSYEN 44(1), ENAKMEN SUMBER AIR KEDAH 2008<br><span class="i">PURSUANT TO SECTION 44(1), KEDAH WATER RESOURCES ENACTMENT 2008</span></div>
<table class="details">
<tr><td class="no">1</td><td class="label">Lesen diberikan kepada<span class="sub">License is granted to</span></td><td class="value">{{ mb_strtoupper($holderText) }}</td></tr>
<tr><td></td><td class="label">Beralamat di<span class="sub">Having its address at</span></td><td class="value">{{ mb_strtoupper($addressText) }}</td></tr>
<tr><td></td><td class="label">{{ $profile['activity_label_ms'] }}<span class="sub">{{ $profile['activity_label_en'] }}</span></td><td class="value">{{ mb_strtoupper($activityText) }}</td></tr>
<tr><td></td><td class="label">{{ $profile['measure_label_ms'] }}<span class="sub">{{ $profile['measure_label_en'] }}</span></td><td class="value">{{ $measureText }}@if($measureText!=='-'&&$measureUnit!=='') {{ $measureUnit }}@endif</td></tr>
<tr><td class="no">2</td><td class="label">{{ $profile['location_label_ms'] }}<span class="sub">{{ $profile['location_label_en'] }}</span></td><td class="value">{{ mb_strtoupper($locationText) }}@if($coords)<br>{{ $coords }}@endif</td></tr>
@foreach($applicationDetails as $detail)
<tr><td></td><td class="label">{{ $detail[0] }}<span class="sub">{{ $detail[1] }}</span></td><td class="value">{{ mb_strtoupper($displayValue($detail[2])) }}</td></tr>
@endforeach
<tr><td class="no">3</td><td class="label">Lesen hendaklah berkuat kuasa dari<span class="sub">The Licence shall be effective from</span></td><td class="value">{{ $fmt($license->start_date) }} <span style="display:inline-block;width:45pt;text-align:center;font-weight:normal">Hingga<br><span class="i">Until</span></span> {{ $fmt($license->expiry_date) }}</td></tr>
</table>
<div class="legal">{{ $profile['legal_ms'] }}<br><span class="i">{{ $profile['legal_en'] }}</span></div>
<table class="summary">
<tr><td class="no">4</td><td class="label">Lesen ini hendaklah tertakluk kepada syarat-syarat yang berikut<span class="sub">This licence shall be subject to the following conditions</span></td><td class="condition-value">SEPERTI DI LAMPIRAN</td></tr>
<tr><td></td><td class="label">Syarat-syarat standard lesen<span class="sub">Standard licence conditions</span></td><td class="condition-value">{{ $profile['standard_ms'] }}</td></tr>
<tr><td></td><td class="label">Syarat-syarat khas<span class="sub">Special conditions</span></td><td class="condition-value">SEPERTI DI LAMPIRAN</td></tr>
@if($additional->isNotEmpty())<tr><td></td><td class="label">Syarat-syarat tambahan<span class="sub">Additional conditions</span></td><td class="condition-value">SEPERTI DI LAMPIRAN</td></tr>@endif
</table>
<table class="issue"><tr><td>Bertarikh<br><span class="i">Dated</span></td><td class="b">{{ $fmt($license->generated_at??now()) }}</td><td>No. Resit<br><span class="i">Receipt No.</span></td><td class="b" style="text-align:right">{{ $receiptText }}</td></tr></table>
<div class="signature-area">@if(!empty($sealDataUri))<img class="seal" src="{{ $sealDataUri }}" alt="Meterai">@endif<div class="signature">@if(!empty($signatureDataUri))<img src="{{ $signatureDataUri }}" alt="Tandatangan">@else<br><br><br>@endif Pengarah Sumber Air / <span class="i">Water Resources Director</span><br>Lembaga Sumber Air Negeri Kedah / <span class="i">Kedah Water Resources Board</span></div></div>
@if(!empty($verificationUrl))<div class="verify">{{ $verificationUrl }}</div>@endif
</section>
  
<section class="page attachment"><div class="reg">No. Daftar : {{ $registrationNoText }}</div><div class="attachment-title"><div class="main">SYARAT-SYARAT</div><div class="main">LEMBAGA SUMBER AIR NEGERI KEDAH (LSANK)</div><br><div class="main u">{{ $profile['general_title'] }}</div></div><ol class="conditions">@foreach($general as $condition)@php($children=$conditionChildren($condition))<li>{{ $conditionText($condition) }}@if(count($children))<ul>@foreach($children as $child)<li>{{ $conditionText($child) }}</li>@endforeach</ul>@endif</li>@endforeach</ol></section>

<section class="page attachment"><div class="reg">No. Daftar : {{ $registrationNoText }}</div><div class="attachment-title"><div class="main">SYARAT-SYARAT</div><div class="main">LEMBAGA SUMBER AIR NEGERI KEDAH (LSANK)</div><br><div class="main">SYARAT-SYARAT KHUSUS AKTIVITI</div><br><div class="main u">{{ $profile['special_title'] }} ({{ mb_strtoupper($activityText) }})</div></div>@if($special->isNotEmpty())<ol class="conditions">@foreach($special as $condition)@php($children=$conditionChildren($condition))<li>{{ $conditionText($condition) }}@if(count($children))<ul>@foreach($children as $child)<li>{{ $conditionText($child) }}</li>@endforeach</ul>@endif</li>@endforeach</ol>@else<p style="margin-top:18pt;text-align:center">Tiada syarat khusus direkodkan.</p>@endif</section>

@if($additional->isNotEmpty())
<section class="page attachment"><div class="reg">No. Daftar : {{ $registrationNoText }}</div><div class="attachment-title"><div class="main">SYARAT-SYARAT</div><div class="main">LEMBAGA SUMBER AIR NEGERI KEDAH (LSANK)</div><br><div class="main u">SYARAT-SYARAT TAMBAHAN</div><br><div class="main">{{ $profile['special_title'] }} ({{ mb_strtoupper($activityText) }})</div></div><ol class="conditions">@foreach($additional as $condition)@php($children=$conditionChildren($condition))<li>{{ $conditionText($condition) }}@if(count($children))<ul>@foreach($children as $child)<li>{{ $conditionText($child) }}</li>@endforeach</ul>@endif</li>@endforeach</ol></section>
@endif
</body>
</html>
