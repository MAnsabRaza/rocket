<?php

namespace App\Services;

/**
 * M7 theme token layer.
 *
 * Reads the host platform's active theme (themes / theme_colors_fonts) and the
 * general settings identity, and exposes named tokens for headings, forms,
 * tables, status indicators and generated documents. Read-only: it never
 * writes theme data, and it has no dependency on ISO business logic.
 */
class ThemeTokenService
{
    public const DEFAULTS = [
        'primary'   => '#0170ff',
        'secondary' => '#0e2145',
        'accent'    => '#fe6257',
        'success'   => '#3fcd82',
        'info'      => '#67a9ff',
        'warning'   => '#ffa200',
        'danger'    => '#f63c3c',
        'dark'      => '#121f3e',
        'white'     => '#ffffff',
        'gray_200'  => '#f0f4f9',
        'gray_400'  => '#cdd5e2',
    ];

    private const HEX = '/^#[0-9a-fA-F]{3,8}$/';

    public function colors(?string $mode = null): array
    {
        return $this->resolve($this->activeThemeContents(), $mode ?: $this->currentMode());
    }

    public function resolve(array $contents, string $mode): array
    {
        $set = $contents[$mode] ?? [];
        if (empty($set)) {
            $set = $contents['light'] ?? ($contents['dark'] ?? []);
        }
        $valid = [];
        foreach ((array) $set as $key => $value) {
            if (is_string($key) && is_string($value) && preg_match(self::HEX, $value)) {
                $valid[$key] = $value;
            }
        }
        return array_merge(self::DEFAULTS, $valid);
    }

    public function tokens(?string $mode = null): array
    {
        $c = $this->colors($mode);
        return [
            'color.primary'     => $c['primary'],
            'color.secondary'   => $c['secondary'],
            'color.accent'      => $c['accent'],
            'heading.color'     => $c['secondary'],
            'heading.accent'    => $c['primary'],
            'form.border'       => $c['gray_400'],
            'form.focus'        => $c['primary'],
            'form.background'   => $c['white'],
            'table.header_bg'   => $c['secondary'],
            'table.header_text' => $c['white'],
            'table.stripe_bg'   => $c['gray_200'],
            'table.border'      => $c['gray_400'],
            'status.success'    => $c['success'],
            'status.info'       => $c['info'],
            'status.warning'    => $c['warning'],
            'status.danger'     => $c['danger'],
            'document.text'     => $c['dark'],
            'document.accent'   => $c['primary'],
            'document.muted'    => $c['gray_400'],
        ];
    }

    public function toCss(?string $mode = null): string
    {
        $css = ':root{';
        foreach ($this->tokens($mode) as $key => $value) {
            $css .= '--flinkiso-' . str_replace(['.', '_'], '-', $key) . ':' . $value . ';';
        }
        return $css . '}';
    }

    public function identity(): array
    {
        $g = [];
        try {
            $g = function_exists('getGeneralSettings') ? (array) getGeneralSettings() : [];
        } catch (\Throwable $e) {
            $g = [];
        }
        return [
            'name' => !empty($g['site_name']) ? (string) $g['site_name'] : (string) config('app.name'),
            'logo' => !empty($g['logo']) ? (string) $g['logo'] : null,
        ];
    }

    private function currentMode(): string
    {
        try {
            $mode = function_exists('getUserThemeColorMode') ? getUserThemeColorMode() : 'light';
        } catch (\Throwable $e) {
            $mode = 'light';
        }
        return $mode === 'dark' ? 'dark' : 'light';
    }

    private function activeThemeContents(): array
    {
        try {
            $theme = function_exists('getActiveTheme') ? getActiveTheme() : null;
            if (!empty($theme) && !empty($theme->color) && !empty($theme->color->content)) {
                $decoded = json_decode($theme->color->content, true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return [];
    }
}
