<?php
$paths = [
    'D:\php\extras\ssl\cacert.pem',
    'D:\php\cacert.pem',
    'D:\cacert.pem',
    'C:\cacert.pem',
    'D:\php\php-8.4.21\extras\ssl\cacert.pem',
    __DIR__.'/cacert.pem',
];
foreach ($paths as $p) {
    if (file_exists($p)) {
        echo "FOUND: $p\n";
    } else {
        echo "no: $p\n";
    }
}
echo "curl.cainfo: ".(ini_get('curl.cainfo') ?: 'not set')."\n";
echo "openssl.cafile: ".(ini_get('openssl.cafile') ?: 'not set')."\n";
echo "php.ini: ".php_ini_loaded_file()."\n";
