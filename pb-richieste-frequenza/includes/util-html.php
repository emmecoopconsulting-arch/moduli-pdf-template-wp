<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Html {
  public static function render_html($template_path, $out_path, $vars) {
    if (!file_exists($template_path)) {
      throw new Exception('Template HTML non trovato: ' . $template_path);
    }
    $html = file_get_contents($template_path);
    foreach ($vars as $key => $value) {
      $html = str_replace('${' . $key . '}', esc_html($value), $html);
    }
    file_put_contents($out_path, $html);
    return $out_path;
  }

  public static function convert_to_pdf($html_path, $out_path, $options = []) {
    if (!file_exists($html_path)) {
      throw new Exception('HTML non trovato per conversione PDF.');
    }

    $command = trim(shell_exec('command -v chromium || command -v chromium-browser || command -v google-chrome'));
    if (!$command) {
      throw new Exception('Chromium non disponibile sul server.');
    }

    $header = $options['header'] ?? '';
    $footer = $options['footer'] ?? '';
    $header_html = $header && file_exists($header) ? file_get_contents($header) : '';
    $footer_html = $footer && file_exists($footer) ? file_get_contents($footer) : '';
    $header_footer_css = '';
    if ($header_html || $footer_html) {
      $header_footer_css = '<style>@page { margin: 24mm 12mm; }' .
        'header { position: fixed; top: -18mm; left: 0; right: 0; height: 15mm; }' .
        'footer { position: fixed; bottom: -18mm; left: 0; right: 0; height: 15mm; }' .
        'body { margin: 0; }</style>';
    }
    $header_footer_markup = $header_footer_css;
    if ($header_html) {
      $header_footer_markup .= '<header>' . $header_html . '</header>';
    }
    if ($footer_html) {
      $header_footer_markup .= '<footer>' . $footer_html . '</footer>';
    }

    $html = file_get_contents($html_path);
    if ($header_footer_markup) {
      $html = preg_replace('/<body[^>]*>/', '$0' . $header_footer_markup, $html, 1);
      file_put_contents($html_path, $html);
    }

    $cmd = sprintf(
      '%s --headless --disable-gpu --no-sandbox --print-to-pdf=%s %s 2>&1',
      escapeshellcmd($command),
      escapeshellarg($out_path),
      escapeshellarg($html_path)
    );
    exec($cmd, $output, $ret);
    if ($ret !== 0) {
      throw new Exception("Errore conversione PDF:\n" . implode("\n", $output));
    }
    if (!file_exists($out_path)) {
      throw new Exception('PDF non generato (file output mancante).');
    }
    return $out_path;
  }
}
