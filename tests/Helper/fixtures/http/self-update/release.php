<?php

// Fake "latest release" payload used by SelfUpdateCommandTest: every asset
// points to the same binary, served by binary.php

$binaryUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/self-update/binary.php';

$assets = [];
foreach (['linux-amd64', 'linux-arm64', 'darwin-amd64', 'darwin-arm64'] as $platform) {
    $assets[] = ['name' => "castor.{$platform}", 'browser_download_url' => $binaryUrl];
    $assets[] = ['name' => "castor.{$platform}.phar", 'browser_download_url' => $binaryUrl];
}
$assets[] = ['name' => 'castor.windows-amd64.phar', 'browser_download_url' => $binaryUrl];

header('Content-Type: application/json');
echo json_encode(['tag_name' => 'v99.0.0', 'assets' => $assets]);
