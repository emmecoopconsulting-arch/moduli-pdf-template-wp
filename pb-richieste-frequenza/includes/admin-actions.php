<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Admin_Actions {

  public static function generate_pdf() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
    check_admin_referer('pb_rf_generate_pdf');

    $post_id = intval($_GET['post_id'] ?? 0);
    if (!$post_id) wp_die('post_id mancante');

    try {
      PB_RF_Storage::ensure_storage();

      $modulo_id = intval(get_post_meta($post_id, '_pb_modulo_id', true));
      $tpl_html = $modulo_id ? PB_RF_Moduli::html_template_filename($modulo_id) : '';
      $tpl_header = $modulo_id ? PB_RF_Moduli::html_header_filename($modulo_id) : '';
      $tpl_footer = $modulo_id ? PB_RF_Moduli::html_footer_filename($modulo_id) : '';
      $tpl_name = $modulo_id ? PB_RF_Moduli::template_filename($modulo_id) : 'template.docx';

      $ref = get_post_meta($post_id, '_pb_ref', true);
      $vars = PB_RF_Richieste::build_template_vars($post_id);

      if ($tpl_html) {
        $template_path = PB_RF_HTML_PATH . '/' . $tpl_html;
        $html_out = PB_RF_HTML_PATH . '/' . $ref . '.html';
        $header_out = $tpl_header ? PB_RF_TMP_PATH . '/' . $ref . '-header.html' : '';
        $footer_out = $tpl_footer ? PB_RF_TMP_PATH . '/' . $ref . '-footer.html' : '';

        PB_RF_Html::render_html($template_path, $html_out, $vars);
        if ($tpl_header) {
          PB_RF_Html::render_html(PB_RF_HTML_PATH . '/' . $tpl_header, $header_out, $vars);
        }
        if ($tpl_footer) {
          PB_RF_Html::render_html(PB_RF_HTML_PATH . '/' . $tpl_footer, $footer_out, $vars);
        }

        $pdf_out = PB_RF_PDF_PATH . '/' . $ref . '.pdf';
        $pdf = PB_RF_Html::convert_to_pdf($html_out, $pdf_out, [
          'header' => $header_out,
          'footer' => $footer_out,
        ]);

        update_post_meta($post_id, '_pb_html_path', $html_out);
        update_post_meta($post_id, '_pb_pdf_path', $pdf);
      } else {
        $template_path = PB_RF_DOCX_PATH . '/' . $tpl_name;
        $docx_out = PB_RF_DOCX_PATH . '/' . $ref . '.docx';
        PB_RF_Docx::render_docx($template_path, $docx_out, $vars);
        $pdf = PB_RF_Docx::convert_to_pdf($docx_out, PB_RF_PDF_PATH);
        update_post_meta($post_id, '_pb_pdf_path', $pdf);
        update_post_meta($post_id, '_pb_html_path', '');
      }

      // Auto-send if enabled in module
      if ($pdf && $modulo_id && get_post_meta($modulo_id, '_pb_auto_send', true) === '1') {
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
    $pdf = $post_id ? get_post_meta($post_id, '_pb_pdf_path', true) : '';
    if (!$pdf || !file_exists($pdf) || !PB_RF_Storage::path_is_inside_base($pdf)) wp_die('PDF non disponibile.');

    $filename = basename($pdf);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($pdf));
    readfile($pdf);
    exit;
  }

  public static function download_html() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
    check_admin_referer('pb_rf_download_html');

    $post_id = intval($_GET['post_id'] ?? 0);
    $html = $post_id ? get_post_meta($post_id, '_pb_html_path', true) : '';
    if (!$html || !file_exists($html) || !PB_RF_Storage::path_is_inside_base($html)) wp_die('HTML non disponibile.');

    $filename = basename($html);
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($html));
    readfile($html);
    exit;
  }

  public static function send_pdf() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
    check_admin_referer('pb_rf_send_pdf');

    $post_id = intval($_GET['post_id'] ?? 0);
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
}
