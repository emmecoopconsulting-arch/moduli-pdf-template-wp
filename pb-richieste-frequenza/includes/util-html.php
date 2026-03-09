<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Html {
  public static function available_templates() {
    if (!is_dir(PB_RF_HTML_PATH)) {
      return [];
    }

    $files = glob(PB_RF_HTML_PATH . '/*.html');
    if (!is_array($files)) {
      return [];
    }

    $templates = array_map('basename', $files);
    natcasesort($templates);
    return array_values(array_unique($templates));
  }

  public static function normalize_template_filename($value) {
    $value = trim((string) $value);
    if ($value === '') {
      return '';
    }

    $path = wp_parse_url($value, PHP_URL_PATH);
    if (is_string($path) && $path !== '') {
      $value = $path;
    }

    $value = wp_basename(str_replace('\\', '/', $value));
    return sanitize_file_name($value);
  }

  public static function resolve_template($filename) {
    $configured = self::normalize_template_filename($filename);
    $available = self::available_templates();
    $candidates = array_values(array_unique(array_filter([
      $configured,
      'template.html',
    ])));

    foreach ($candidates as $candidate) {
      $path = PB_RF_HTML_PATH . '/' . $candidate;
      if (is_readable($path)) {
        return [
          'filename' => $candidate,
          'path' => $path,
          'available' => $available,
        ];
      }
    }

    if (count($available) === 1) {
      return [
        'filename' => $available[0],
        'path' => PB_RF_HTML_PATH . '/' . $available[0],
        'available' => $available,
      ];
    }

    return [
      'filename' => $configured ? $configured : 'template.html',
      'path' => '',
      'available' => $available,
    ];
  }

  public static function render_template($template_path, $out_path, $vars, $header_path, $footer_path) {
    if (!file_exists($template_path)) {
      throw new Exception('Template HTML non trovato: ' . $template_path);
    }

    $html = file_get_contents($template_path);
    if ($html === false) {
      throw new Exception('Impossibile leggere il template HTML.');
    }

    $rendered = self::replace_vars($html, $vars);
    $header_html = self::load_partial($header_path, $vars);
    $footer_html = self::load_partial($footer_path, $vars);

    if ($header_html !== '' || $footer_html !== '') {
      $rendered = self::inject_layout($rendered, $header_html, $footer_html);
    }

    if (file_put_contents($out_path, $rendered) === false) {
      throw new Exception('Impossibile creare HTML output.');
    }

    return $out_path;
  }

  public static function convert_to_pdf($html_path, $pdf_path) {
    if (!file_exists($html_path)) {
      throw new Exception('HTML non trovato per conversione PDF.');
    }

    $browser = self::find_browser();
    if ($browser === '') {
      throw new Exception('Chromium/Chrome non trovato nel PATH del server.');
    }

    $user_data_dir = PB_RF_TMP_PATH . '/chrome-' . bin2hex(random_bytes(6));
    if (!mkdir($user_data_dir, 0770, true)) {
      throw new Exception('Impossibile creare cartella temporanea Chromium.');
    }

    $cmd = sprintf(
      '%s --headless --disable-gpu --no-sandbox --allow-file-access-from-files --user-data-dir=%s --print-to-pdf=%s %s 2>&1',
      escapeshellarg($browser),
      escapeshellarg($user_data_dir),
      escapeshellarg($pdf_path),
      escapeshellarg('file://' . $html_path)
    );

    exec($cmd, $output, $ret);
    self::rrmdir($user_data_dir);

    if ($ret !== 0 || !file_exists($pdf_path)) {
      throw new Exception("Errore conversione HTML->PDF:\n" . implode("\n", $output));
    }

    return $pdf_path;
  }

  private static function load_partial($path, $vars) {
    if (!$path) {
      return '';
    }
    if (!file_exists($path)) {
      throw new Exception('Template HTML secondario non trovato: ' . basename($path));
    }

    $html = file_get_contents($path);
    if ($html === false) {
      throw new Exception('Impossibile leggere template HTML secondario: ' . basename($path));
    }

    return self::replace_vars($html, $vars);
  }

  private static function replace_vars($html, $vars) {
    foreach ($vars as $key => $value) {
      $html = str_replace('${' . $key . '}', (string) $value, $html);
    }
    return $html;
  }

  private static function inject_layout($html, $header_html, $footer_html) {
    $layout_css = '<style>'
      . '.pb-rf-page{width:100%;}'
      . '.pb-rf-header,.pb-rf-footer{width:100%;}'
      . '.pb-rf-body{width:100%;}'
      . '</style>';

    $body_open = stripos($html, '<body');
    if ($body_open === false) {
      return $layout_css
        . '<div class="pb-rf-page">'
        . ($header_html !== '' ? '<div class="pb-rf-header">' . $header_html . '</div>' : '')
        . '<div class="pb-rf-body">' . $html . '</div>'
        . ($footer_html !== '' ? '<div class="pb-rf-footer">' . $footer_html . '</div>' : '')
        . '</div>';
    }

    $body_start = strpos($html, '>', $body_open);
    if ($body_start === false) {
      return $html;
    }

    $insert = $layout_css . '<div class="pb-rf-page">';
    if ($header_html !== '') {
      $insert .= '<div class="pb-rf-header">' . $header_html . '</div>';
    }
    $insert .= '<div class="pb-rf-body">';

    $html = substr($html, 0, $body_start + 1) . $insert . substr($html, $body_start + 1);

    $body_close = strripos($html, '</body>');
    if ($body_close === false) {
      $html .= '</div>';
      if ($footer_html !== '') {
        $html .= '<div class="pb-rf-footer">' . $footer_html . '</div>';
      }
      $html .= '</div>';
      return $html;
    }

    $closing = '</div>';
    if ($footer_html !== '') {
      $closing .= '<div class="pb-rf-footer">' . $footer_html . '</div>';
    }
    $closing .= '</div>';

    return substr($html, 0, $body_close) . $closing . substr($html, $body_close);
  }

  private static function find_browser() {
    $browsers = ['chromium', 'chromium-browser', 'google-chrome'];
    foreach ($browsers as $browser) {
      $path = trim((string) shell_exec('command -v ' . escapeshellarg($browser) . ' 2>/dev/null'));
      if ($path !== '') {
        return $path;
      }
    }
    return '';
  }

  private static function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
      if ($file->isDir()) rmdir($file->getRealPath());
      else unlink($file->getRealPath());
    }
    rmdir($dir);
  }
}
