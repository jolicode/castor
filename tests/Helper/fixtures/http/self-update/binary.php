<?php

// Serves the "new" binary prepared by SelfUpdateCommandTest

$file = sys_get_temp_dir() . '/castor-test-self-update/castor.new';

if (!is_file($file)) {
    http_response_code(404);

    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($file));
readfile($file);
