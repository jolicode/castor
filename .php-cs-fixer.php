<?php

$finder = PhpCsFixer\Finder::create()
    ->ignoreVCSIgnored(true)
    ->ignoreDotFiles(false)
    ->in(__DIR__)
    ->append([
        __FILE__,
    ])
    ->notPath([
        '.castor.stub.php',
        'tests/Stub/fixtures',
    ])
;

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP82Migration' => true,
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'php_unit_internal_class' => false, // From @PhpCsFixer but we don't want it
        'php_unit_test_class_requires_covers' => false, // From @PhpCsFixer but we don't want it
        'phpdoc_add_missing_param_annotation' => false, // From @PhpCsFixer but we don't want it
        'ordered_class_elements' => true, // Symfony(PSR12) override the default value, but we don't want it
        'blank_line_before_statement' => true, // Symfony(PSR12) override the default value, but we don't want it
        'method_chaining_indentation' => false, // Does not work with tree builder
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder)
;
