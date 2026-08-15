<?php

/**
 * PHPUnit bootstrap for WP Vite.
 *
 * Order matters:
 *   1. Composer autoloader — registers PSR-4 classes.
 *   2. WordPress stubs     — defines WP functions/constants before any class is loaded.
 *
 * @package WpVite\Tests
 */

declare(strict_types=1);

$autoloader = __DIR__ . '/../vendor/autoload.php';

if (! file_exists($autoloader)) {
    echo "\033[31mError:\033[0m vendor/autoload.php not found. Run \033[33mcomposer install\033[0m first.\n";
    exit(1);
}

require_once $autoloader;
require_once __DIR__ . '/Stubs/wordpress.php';
require_once __DIR__ . '/WpViteTestCase.php';
