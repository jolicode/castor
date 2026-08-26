<?php

// Fake GitHub releases API used by SelfUpdateCommandTest: "/latest" is the
// latest release, "/tags/snapshot" the snapshot pre-release. Every asset points
// to the same binary, served by binary.php

$binaryUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/self-update/binary.php';

$assets = [];
foreach (['linux-amd64', 'linux-arm64', 'darwin-amd64', 'darwin-arm64'] as $platform) {
    $assets[] = ['name' => "castor.{$platform}", 'url' => $binaryUrl, 'browser_download_url' => $binaryUrl];
    $assets[] = ['name' => "castor.{$platform}.phar", 'url' => $binaryUrl, 'browser_download_url' => $binaryUrl];
}
$assets[] = ['name' => 'castor.windows-amd64.phar', 'url' => $binaryUrl, 'browser_download_url' => $binaryUrl];

$release = '/tags/snapshot' === ($_SERVER['PATH_INFO'] ?? '/latest')
    ? ['tag_name' => 'snapshot', 'name' => 'v99.0.0-3-gabcdef0']
    : ['tag_name' => 'v99.0.0', 'name' => 'v99.0.0'];

header('Content-Type: application/json');
echo json_encode($release + ['assets' => $assets]);
