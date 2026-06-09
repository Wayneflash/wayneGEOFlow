<?php
$cacert = __DIR__.'/cacert.pem';
$ch = curl_init('https://api.minimax.chat/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CAINFO, $cacert);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
$r = curl_exec($ch);
$e = curl_error($ch);
$c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo 'http_code='.$c.' err='.($e ?: 'none').' body_len='.strlen((string) $r).PHP_EOL;
