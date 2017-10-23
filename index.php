<?php

require '../vendor/autoload.php';

use Webware\Api;

$api = new Api('https://webware-host');
$api->debug = true;

// Register a ServicePass
$pass = $api->register('147c80aded58629cdf3a9bbcea2ab75n', '0b72c040260c6f6dbf05ab16191fc01k', 1);

// Or add an existing pass
$pass = new Webware\Classes\ServicePass('92120605adb0dbca535e930a39f36e0a', '712cdfbf031196024d810bd9ce18ecba');
$api->setServicePass($pass);

// Get data
$resp = $api->get('ADRESSE', [[
    'PCONTENT' => 'ADR_2_8,ADR_667_45',
    'PNAME' => 'FELDER',
    'POSITION' => 1
]]);
