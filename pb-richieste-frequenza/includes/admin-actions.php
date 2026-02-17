<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Admin_Actions {

  public static function generate_pdf() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
    check_admin_referer('pb_rf_generate_pdf');

    $post_id = intval($_GET['post_id'] ?? 0);
    self::assert_request_post($post_id);

    try {
      PB_RF_Storage::ensure_storage();

      $modulo_id = intval(get_post_meta($post_id, '_pb_modulo_id', true));
      $tpl_name = $modulo_id ? PB_RF_Moduli::template_filename($modulo_id) : 'template.docx';
      $template_path = PB_RF_DOCX_PATH . '/' . $tpl_name;
      if (!file_exists($template_path) || !is_readable($template_path)) {
        throw new Exception('Template DOCX non trovato o non leggibile: ' . $tpl_name);
      }

      $ref = get_post_meta($post_id, '_pb_ref', true);
      if (!$ref) {
        throw new Exception('Numero pratica mancante nella richiesta.');
      }
      $docx_out = PB_RF_DOCX_PATH . '/' . $ref . '.docx';
      $vars = PB_RF_Richieste::build_template_vars($post_id);

      PB_RF_Docx::render_docx($template_path, $docx_out, $vars);
      $pdf = PB_RF_Docx::convert_to_pdf($docx_out, PB_RF_PDF_PATH);

      update_post_meta($post_id, '_pb_pdf_path', $pdf);

      // Auto-send if enabled in module
      if ($modulo_id && get_post_meta($modulo_id, '_pb_auto_send', true) === '1') {
        PB_RF_Mailer::send_pdf_to_parent($post_id, $pdf);
      }

      wp_safe_redirect(get_edit_post_link($post_id, ''));
      exit;
    } catch (Exception $e) {
      wp_die(nl2br(esc_html($e->getMessage())));
    }
  }

  public static function download_pdf() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
    check_admin_referer('pb_rf_download_pdf');

    $post_id = intval($_GET['post_id'] ?? 0);
    self::assert_request_post($post_id);
    $pdf = $post_id ? get_post_meta($post_id, '_pb_pdf_path', true) : '';
    if (!$pdf || !file_exists($pdf) || !PB_RF_Storage::path_is_inside_base($pdf)) wp_die('PDF non disponibile.');

    $filename = basename($pdf);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($pdf));
    readfile($pdf);
    exit;
  }

  public static function send_pdf() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
    check_admin_referer('pb_rf_send_pdf');

    $post_id = intval($_GET['post_id'] ?? 0);
    self::assert_request_post($post_id);
    $pdf = $post_id ? get_post_meta($post_id, '_pb_pdf_path', true) : '';
    if (!$pdf || !file_exists($pdf) || !PB_RF_Storage::path_is_inside_base($pdf)) wp_die('PDF non disponibile.');

    try {
      PB_RF_Mailer::send_pdf_to_parent($post_id, $pdf);
      wp_safe_redirect(get_edit_post_link($post_id, ''));
      exit;
    } catch (Exception $e) {
      wp_die(nl2br(esc_html($e->getMessage())));
    }
  }

  private static function assert_request_post($post_id) {
    if (!$post_id) wp_die('post_id mancante');
    if (get_post_type($post_id) !== PB_RF_Richieste::CPT) {
      wp_die('Richiesta non valida.');
    }
  }
}
