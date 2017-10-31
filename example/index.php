<?php

require '../vendor/autoload.php';

use Pixelarbeit\Webware\Api;
use Pixelarbeit\Webware\Classes\ServicePass;

$api = new Api('https://meine-webware.de');
// $api->debug = true;

// Register a ServicePass
$pass = $api->register('147c80aded58629cdf3a9bbcea2ab75n', '0b72c040260c6f6dbf05ab16191fc01k', 1);

// Or add an existing pass
$pass = new ServicePass('92120605adb0dbca535e930a39f36e0a', '712cdfbf031196024d810bd9ce18ecba');
$api->setServicePass($pass);

// Get data
$resp = $api->get('ADRESSE', [
    'FELDER' => 'ADR_2_8,ADR_667_45',
]);

echo '<pre>';
echo var_dump($resp->ADRESSLISTE->ADRESSE);
echo '</pre>';
