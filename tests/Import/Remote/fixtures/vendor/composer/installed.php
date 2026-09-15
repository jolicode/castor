<?php return array(
    'root' => array(
        'name' => 'acme/root',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'install_path' => __DIR__ . '/../../',
        'type' => 'project',
        'dev' => true,
    ),
    'versions' => array(
        'acme/root' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'install_path' => __DIR__ . '/../../',
            'type' => 'project',
            'dev_requirement' => false,
        ),
        'foo/implementation' => array(
            'dev_requirement' => false,
            'provided' => array(
                0 => '1.0|2.0',
                1 => '3.0.0',
            ),
        ),
        'foo/left-out' => array(
            'pretty_version' => 'v2.0.0',
            'version' => '2.0.0.0',
            'install_path' => __DIR__ . '/../foo/left-out',
            'type' => 'library',
            'dev_requirement' => false,
        ),
        'foo/replaced' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'foo/shipped' => array(
            'pretty_version' => 'v1.2.3',
            'version' => '1.2.3.0',
            'install_path' => __DIR__ . '/../foo/shipped',
            'type' => 'library',
            'dev_requirement' => false,
        ),
    ),
);
