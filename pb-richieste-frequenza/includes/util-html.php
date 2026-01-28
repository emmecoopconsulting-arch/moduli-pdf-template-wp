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
}
