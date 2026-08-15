<?php

/**
 * Asset options value object for WP Vite
 *
 * @package WpVite
 */

declare(strict_types=1);

namespace WpVite;

/**
 * Typed value object representing the options passed to register/enqueue calls.
 *
 * Default values mirror the reference implementation for easy migration.
 */
class AssetOptions
{
    /**
     * WordPress script/style handle.
     */
    public readonly string $handle;

    /**
     * Script dependencies (registered handles).
     *
     * @var string[]
     */
    public readonly array $dependencies;

    /**
     * Style dependencies (registered style handles).
     *
     * @var string[]
     */
    public readonly array $cssDependencies;

    /**
     * CSS media attribute.
     */
    public readonly string $cssMedia;

    /**
     * When true, only CSS assets are enqueued (useful for CSS-only components).
     */
    public readonly bool $cssOnly;

    /**
     * Whether to load the script in the footer.
     */
    public readonly bool $inFooter;

    /**
     * @param array{
     *     handle?:           string,
     *     dependencies?:     string[],
     *     css-dependencies?: string[],
     *     css-media?:        string,
     *     css-only?:         bool,
     *     in-footer?:        bool,
     * } $options
     */
    public function __construct(array $options)
    {
        $parsed = wp_parse_args($options, [
            'handle'           => '',
            'dependencies'     => [],
            'css-dependencies' => [],
            'css-media'        => 'all',
            'css-only'         => false,
            'in-footer'        => false,
        ]);

        $this->handle          = (string) $parsed['handle'];
        $this->dependencies    = (array) $parsed['dependencies'];
        $this->cssDependencies = (array) $parsed['css-dependencies'];
        $this->cssMedia        = (string) $parsed['css-media'];
        $this->cssOnly         = (bool) $parsed['css-only'];
        $this->inFooter        = (bool) $parsed['in-footer'];
    }
}
