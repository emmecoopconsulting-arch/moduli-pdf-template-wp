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

    $command = trim(shell_exec('command -v wkhtmltopdf'));
    if (!$command) {
      throw new Exception('wkhtmltopdf non disponibile sul server.');
    }

    $header = $options['header'] ?? '';
    $footer = $options['footer'] ?? '';
    $extra_args = [];
    if ($header && file_exists($header)) {
      $extra_args[] = '--header-html ' . escapeshellarg($header);
    }
    if ($footer && file_exists($footer)) {
      $extra_args[] = '--footer-html ' . escapeshellarg($footer);
    }

    $cmd = sprintf(
      '%s --quiet %s %s %s 2>&1',
      escapeshellcmd($command),
      implode(' ', $extra_args),
      escapeshellarg($html_path),
      escapeshellarg($out_path)
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
