<?php

/**
 * Script module type filter for WP Vite
 *
 * @package WpVite
 */

declare(strict_types=1);

namespace WpVite;

/**
 * Adds `type="module"` to Vite script tags.
 *
 * Uses WP_HTML_Tag_Processor (WP 6.2+) when available,
 * with a safe regex fallback for older WordPress versions.
 */
class ScriptModuleFilter
{
    /**
     * Tracks which handles have already been hooked to avoid duplicate filters.
     *
     * @var string[]
     */
    private static array $registered = [];

    /**
     * Register the `script_loader_tag` filter for the given handle.
     */
    public static function apply(string $handle): void
    {
        if (in_array($handle, self::$registered, true)) {
            return;
        }

        self::$registered[] = $handle;

        add_filter(
            'script_loader_tag',
            static fn (...$args) => self::addModuleAttribute($handle, ...$args),
            10,
            3,
        );
    }

    /**
     * Add `type="module"` to the script tag matching the target handle.
     *
     * @param string $targetHandle The handle we are targeting.
     * @param string $tag          The full HTML script tag string.
     * @param string $handle       The handle of the script currently being filtered.
     * @param string $src          The script src URL.
     *
     * @return string Modified or unchanged script tag.
     */
    private static function addModuleAttribute(
        string $targetHandle,
        string $tag,
        string $handle,
        string $src,
    ): string {
        if ($targetHandle !== $handle) {
            return $tag;
        }

        // Preferred: WP_HTML_Tag_Processor (WordPress 6.2+)
        if (class_exists('WP_HTML_Tag_Processor')) {
            return self::applyViaTagProcessor($tag, $src);
        }

        // Fallback: regex replacement for WordPress < 6.2
        return self::applyViaRegex($tag, $src);
    }

    /**
     * Use WP_HTML_Tag_Processor to add the attribute.
     */
    private static function applyViaTagProcessor(string $tag, string $src): string
    {
        $processor = new \WP_HTML_Tag_Processor($tag);

        while ($processor->next_tag('script')) {
            if ($processor->get_attribute('src') === $src) {
                $processor->set_attribute('type', 'module');
                break;
            }
        }

        return $processor->get_updated_html();
    }

    /**
     * Regex fallback to add the attribute.
     */
    private static function applyViaRegex(string $tag, string $src): string
    {
        $escaped = preg_quote($src, '/');

        $modified = preg_replace(
            '/(<script\b(?![^>]*\btype=["\']module["\'])[^>]*\bsrc=["\']' . $escaped . '["\'][^>]*)>/i',
            '$1 type="module">',
            $tag,
            1,
        );

        return $modified ?? $tag;
    }

    /**
     * Reset registered handles list (useful for testing).
     */
    public static function reset(): void
    {
        self::$registered = [];
    }
}
