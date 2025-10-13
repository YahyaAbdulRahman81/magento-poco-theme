<?php
$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app/code/Magebees',
        __DIR__ . '/app/code/Clearsale',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRules([
        'nullable_type_declaration_for_default_null_value' => true,
    ]);
