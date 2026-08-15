<?php

/**
 * Tests for WpVite\AssetOptions
 *
 * @package WpVite\Tests\Unit
 */

declare(strict_types=1);

namespace WpVite\Tests\Unit;

use WpVite\AssetOptions;
use WpVite\Tests\WpViteTestCase;

/**
 * @covers \WpVite\AssetOptions
 */
class AssetOptionsTest extends WpViteTestCase
{
    public function test_defaults(): void
    {
        $opts = new AssetOptions([]);

        $this->assertSame('', $opts->handle);
        $this->assertSame([], $opts->dependencies);
        $this->assertSame([], $opts->cssDependencies);
        $this->assertSame('all', $opts->cssMedia);
        $this->assertFalse($opts->cssOnly);
        $this->assertFalse($opts->inFooter);
    }

    public function test_all_options_applied(): void
    {
        $opts = new AssetOptions([
            'handle'           => 'my-handle',
            'dependencies'     => ['wp-element', 'react'],
            'css-dependencies' => ['wp-components'],
            'css-media'        => 'print',
            'css-only'         => true,
            'in-footer'        => true,
        ]);

        $this->assertSame('my-handle', $opts->handle);
        $this->assertSame(['wp-element', 'react'], $opts->dependencies);
        $this->assertSame(['wp-components'], $opts->cssDependencies);
        $this->assertSame('print', $opts->cssMedia);
        $this->assertTrue($opts->cssOnly);
        $this->assertTrue($opts->inFooter);
    }

    public function test_partial_options_use_defaults_for_the_rest(): void
    {
        $opts = new AssetOptions(['handle' => 'my-handle', 'in-footer' => true]);

        $this->assertSame('my-handle', $opts->handle);
        $this->assertTrue($opts->inFooter);
        $this->assertSame([], $opts->dependencies);   // default
        $this->assertSame('all', $opts->cssMedia);     // default
    }

    public function test_options_are_readonly(): void
    {
        $opts = new AssetOptions(['handle' => 'my-handle']);

        $this->expectException(\Error::class);

        // @phpstan-ignore-next-line
        $opts->handle = 'changed'; // Should throw because readonly
    }
}
