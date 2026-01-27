<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Mailer {
  public static function send_pdf_to_parent($request_id, $pdf_path) {
    $to = get_post_meta($request_id, '_pb_gen_email', true);
    if (!$to || !is_email($to)) throw new Exception('Email genitore non valida o mancante.');

    $ref = get_post_meta($request_id, '_pb_ref', true);
    $subject = "Documento richiesta: $ref";
    $body = "In allegato trovi il PDF della tua richiesta.\n\nNumero pratica: $ref";

    // Allow per-modulo overrides
    $modulo_id = intval(get_post_meta($request_id, '_pb_modulo_id', true));
    if ($modulo_id) {
      $s = get_post_meta($modulo_id, '_pb_mail_subject', true);
      $b = get_post_meta($modulo_id, '_pb_mail_body', true);
      if ($s) $subject = self::render_text_vars($s, $request_id);
      if ($b) $body = self::render_text_vars($b, $request_id);
    }

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    $ok = wp_mail($to, $subject, $body, $headers, [$pdf_path]);
    if (!$ok) throw new Exception('wp_mail ha restituito errore (invio non riuscito).');

    update_post_meta($request_id, '_pb_pdf_sent_at', current_time('mysql'));
    update_post_meta($request_id, '_pb_pdf_sent_to', $to);
    return true;
  }

  private static function render_text_vars($text, $request_id) {
    $vars = PB_RF_Richieste::build_template_vars($request_id);
    foreach ($vars as $k => $v) {
      $text = str_replace('${' . $k . '}', $v, $text);
    }
    return $text;
  }
}
