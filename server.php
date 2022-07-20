<?php

require 'chat.php';

$host = getHostName();
$ip   = getHostByName($host);
$port = $argv[1] ?? 3000;
$data = ['ip' => $ip, 'port' => (int)$port];
$json = json_encode($data, JSON_PRETTY_PRINT);

file_put_contents('ip.json', $json);

$chat = new chat();
$chat->connect($ip, $port);
$chat->run();