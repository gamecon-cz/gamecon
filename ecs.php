<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSummaryFixer;
use PhpCsFixer\Fixer\Whitespace\ArrayIndentationFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/symfony/src',
        __DIR__ . '/symfony/config',
        __DIR__ . '/tests/Symfony',
    ])
    ->withSkip([
        __DIR__ . '/symfony/var',
        PhpdocSummaryFixer::class, // Disable adding dots to PHPDoc - breaks @see FQCN linking
    ])
    ->withPreparedSets(
        psr12:             true,
        arrays:            true,
        comments:          true,
        docblocks:         true,
        spaces:            true,
        namespaces:        true,
        controlStructures: true,
        strict:            true,
        cleanCode:         true,
    )
    ->withPhpCsFixerSets(symfony: true)
    ->withConfiguredRule(ArraySyntaxFixer::class, [
        'syntax' => 'short',
    ])
    ->withRules([
        ArrayIndentationFixer::class,
    ])
    ->withConfiguredRule(BinaryOperatorSpacesFixer::class, [
        'operators' => [
            '=>' => 'align_single_space_minimal',
        ],
    ])
    ->withCache(__DIR__ . '/symfony/var/ecs');
