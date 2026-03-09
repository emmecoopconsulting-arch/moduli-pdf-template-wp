<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Admin_Actions {

  public static function generate_pdf() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
    check_admin_referer('pb_rf_generate_pdf');

    $post_id = intval(isset($_GET['post_id']) ? $_GET['post_id'] : 0);
    self::assert_request_post($post_id);

    try {
      PB_RF_Storage::ensure_storage();

      $modulo_id = intval(get_post_meta($post_id, '_pb_modulo_id', true));
      $ref = get_post_meta($post_id, '_pb_ref', true);
      if (!$ref) {
        throw new Exception('Numero pratica mancante nella richiesta.');
      }
      $vars = PB_RF_Richieste::build_template_vars($post_id);

      $html_template_name = $modulo_id ? PB_RF_Moduli::html_template_filename($modulo_id) : '';
      if ($html_template_name !== '') {
        $html_template = PB_RF_Html::resolve_template($html_template_name);
        if (!$html_template['path'] || !is_readable($html_template['path'])) {
          $message = 'Template HTML non trovato o non leggibile: ' . $html_template['filename'];
          if (!empty($html_template['available'])) {
            $message .= "\nTemplate HTML disponibili: " . implode(', ', $html_template['available']);
          } else {
            $message .= "\nNessun template HTML presente in " . PB_RF_HTML_PATH;
          }
          throw new Exception($message);
        }

        $header_name = $modulo_id ? PB_RF_Moduli::html_header_filename($modulo_id) : '';
        $footer_name = $modulo_id ? PB_RF_Moduli::html_footer_filename($modulo_id) : '';
        $header_path = $header_name !== '' ? PB_RF_HTML_PATH . '/' . $header_name : '';
        $footer_path = $footer_name !== '' ? PB_RF_HTML_PATH . '/' . $footer_name : '';
        $html_out = PB_RF_HTML_PATH . '/' . $ref . '.html';
        $pdf = PB_RF_PDF_PATH . '/' . $ref . '.pdf';

        PB_RF_Html::render_template($html_template['path'], $html_out, $vars, $header_path, $footer_path);
        PB_RF_Html::convert_to_pdf($html_out, $pdf);
        update_post_meta($post_id, '_pb_pdf_path', $pdf);

        if ($modulo_id && get_post_meta($modulo_id, '_pb_auto_send', true) === '1') {
          PB_RF_Mailer::send_pdf_to_parent($post_id, $pdf);
        }

        wp_safe_redirect(get_edit_post_link($post_id, ''));
        exit;
      }

      $template = $modulo_id
        ? PB_RF_Moduli::resolve_template($modulo_id)
        : [
            'filename' => 'template.docx',
            'path' => PB_RF_DOCX_PATH . '/template.docx',
            'fallback_used' => false,
            'available' => PB_RF_Moduli::available_templates(),
          ];

      if (!$template['path'] || !is_readable($template['path'])) {
        $message = 'Template DOCX non trovato o non leggibile: ' . $template['filename'];
        if (!empty($template['available'])) {
          $message .= "\nTemplate disponibili: " . implode(', ', $template['available']);
        } else {
          $message .= "\nNessun template DOCX presente in " . PB_RF_DOCX_PATH;
        }
        throw new Exception($message);
      }

      $docx_out = PB_RF_DOCX_PATH . '/' . $ref . '.docx';

      PB_RF_Docx::render_docx($template['path'], $docx_out, $vars);
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

    $post_id = intval(isset($_GET['post_id']) ? $_GET['post_id'] : 0);
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

    $post_id = intval(isset($_GET['post_id']) ? $_GET['post_id'] : 0);
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
