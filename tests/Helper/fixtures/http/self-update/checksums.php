<?php

// Serves the SHA256SUMS file of the fake release: every asset is the binary
// served by binary.php. When SelfUpdateCommandTest creates the
// "corrupt-checksums" file, the listed checksums are wrong.

$dir = sys_get_temp_dir() . '/castor-test-self-update';
$file = $dir . '/castor.new';

if (!is_file($file)) {
    http_response_code(404);

    exit;
}

$checksum = file_exists($dir . '/corrupt-checksums') ? str_repeat('0', 64) : hash_file('sha256', $file);

$names = [];
foreach (['linux-amd64', 'linux-arm64', 'darwin-amd64', 'darwin-arm64'] as $platform) {
    $names[] = "castor.{$platform}";
    $names[] = "castor.{$platform}.phar";
}
$names[] = 'castor.windows-amd64.phar';

header('Content-Type: text/plain');
foreach ($names as $name) {
    echo "{$checksum}  {$name}\n";
}
