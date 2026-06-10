<?php
$o = [CURLOPT_SSL_VERIFYPEER => false];
$b = [
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_ENCODING => '',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
];
$merged = array_merge($o, $b);
echo "keys: ".implode(',', array_keys($merged)).PHP_EOL;
var_export($merged);
