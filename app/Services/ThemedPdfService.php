<?php

namespace App\Services;

use Mpdf\Mpdf;

/**
 * M7 themed document generation.
 *
 * Builds PDF documents with mPDF (technical decision 7, decision 42), styled
 * from ThemeTokenService and set right-to-left for any locale listed in the
 * platform's rtl_languages setting. The caller supplies the body HTML and is
 * responsible for escaping any user content in it; the title is escaped here.
 * No dependency on ISO business logic.
 */
class ThemedPdfService
{
    private ThemeTokenService $theme;

    public function __construct(?ThemeTokenService $theme = null)
    {
        $this->theme = $theme ?: new ThemeTokenService();
    }

    public function isRtl(?string $locale = null): bool
    {
        $locale = mb_strtoupper($locale ?: app()->getLocale());
        $rtl = ['AR'];
        try {
            $g = function_exists('getGeneralSettings') ? (array) getGeneralSettings() : [];
            if (!empty($g['rtl_languages']) && is_array($g['rtl_languages'])) {
                $rtl = array_map('mb_strtoupper', $g['rtl_languages']);
            }
        } catch (\Throwable $e) {
            // keep the default
        }
        return in_array($locale, $rtl, true);
    }

    public function html(string $title, string $bodyHtml, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $dir = $this->isRtl($locale) ? 'rtl' : 'ltr';
        $t = $this->theme->tokens('light');
        $identity = $this->theme->identity();

        $css = 'body{color:' . $t['document.text'] . ';font-size:11pt;}'
             . 'h1,h2,h3{color:' . $t['heading.color'] . ';}'
             . 'h1{border-bottom:2px solid ' . $t['heading.accent'] . ';padding-bottom:4pt;}'
             . '.org{color:' . $t['document.text'] . ';font-size:9pt;}'
             . 'table{border-collapse:collapse;width:100%;}'
             . 'th{background:' . $t['table.header_bg'] . ';color:' . $t['table.header_text'] . ';padding:5pt;border:1px solid ' . $t['table.border'] . ';}'
             . 'td{padding:5pt;border:1px solid ' . $t['table.border'] . ';}'
             . 'tr.stripe td{background:' . $t['table.stripe_bg'] . ';}'
             . '.status-success{color:' . $t['status.success'] . ';}'
             . '.status-info{color:' . $t['status.info'] . ';}'
             . '.status-warning{color:' . $t['status.warning'] . ';}'
             . '.status-danger{color:' . $t['status.danger'] . ';}';

        $logo = '';
        if (!empty($identity['logo'])) {
            $path = public_path(ltrim(parse_url($identity['logo'], PHP_URL_PATH) ?: '', '/'));
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (is_file($path) && in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)) {
                $logo = '<img src="' . e($path) . '" style="height:28pt;" /> ';
            }
        }

        return '<html lang="' . e($locale) . '" dir="' . $dir . '"><head><style>' . $css . '</style></head>'
             . '<body dir="' . $dir . '"><div class="org">' . $logo . e($identity['name']) . '</div>'
             . '<h1>' . e($title) . '</h1>' . $bodyHtml . '</body></html>';
    }

    /**
     * Wrap a left-to-right run (numbers, unit symbols, codes) so it keeps its
     * reading order inside right-to-left text. Escapes its input.
     */
    public function ltr(string $text): string
    {
        return '<bdo dir="ltr">' . e($text) . '</bdo>';
    }

    public function render(string $title, string $bodyHtml, ?string $locale = null): string
    {
        $tmp = storage_path('app/mpdf_tmp');
        if (!is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmp,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetDirectionality($this->isRtl($locale) ? 'rtl' : 'ltr');
        $mpdf->WriteHTML($this->html($title, $bodyHtml, $locale));
        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }
}
