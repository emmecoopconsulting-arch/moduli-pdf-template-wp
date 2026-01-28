<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Richieste {
  const CPT = 'pb_richiesta_freq';

  public static function register() {
    register_post_type(self::CPT, [
      'labels' => ['name' => 'Richieste Frequenza', 'singular_name' => 'Richiesta Frequenza'],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-media-document',
      'supports' => ['title'],
    ]);
  }

  public static function metaboxes() {
    add_meta_box('pb_rf_req_data', 'Dati richiesta', [self::class, 'render_data_metabox'], self::CPT, 'normal', 'high');
    add_meta_box('pb_rf_req_actions', 'Azioni (DOCX/PDF)', [self::class, 'render_actions_metabox'], self::CPT, 'side', 'high');
  }

  public static function render_data_metabox($post) {
    $fields = self::get_all_fields($post->ID);
    echo '<table class="widefat striped">';
    foreach ($fields as $k => $v) {
      echo '<tr><th style="width:240px;">' . esc_html($k) . '</th><td>' . esc_html($v) . '</td></tr>';
    }
    echo '</table>';
  }

  public static function render_actions_metabox($post) {
    $ref = get_post_meta($post->ID, '_pb_ref', true);
    $pdf = get_post_meta($post->ID, '_pb_pdf_path', true);
    $sent_at = get_post_meta($post->ID, '_pb_pdf_sent_at', true);

    $gen_url = wp_nonce_url(admin_url('admin-post.php?action=pb_rf_generate_pdf&post_id=' . intval($post->ID)), 'pb_rf_generate_pdf');
    echo '<p><b>Pratica:</b> ' . esc_html($ref) . '</p>';
    echo '<p><a class="button button-primary" href="' . esc_url($gen_url) . '">Genera PDF</a></p>';

    if ($pdf && file_exists($pdf) && PB_RF_Storage::path_is_inside_base($pdf)) {
      $dl_url = wp_nonce_url(admin_url('admin-post.php?action=pb_rf_download_pdf&post_id=' . intval($post->ID)), 'pb_rf_download_pdf');
      echo '<p><a class="button" href="' . esc_url($dl_url) . '">Scarica PDF</a></p>';

      $send_url = wp_nonce_url(admin_url('admin-post.php?action=pb_rf_send_pdf&post_id=' . intval($post->ID)), 'pb_rf_send_pdf');
      echo '<p><a class="button" href="' . esc_url($send_url) . '">Invia PDF via email</a></p>';

      if ($sent_at) echo '<p style="color:#2271b1">Inviato: ' . esc_html($sent_at) . '</p>';
      else echo '<p style="color:#777">Non ancora inviato</p>';
    } else {
      echo '<p style="color:#777">Nessun PDF generato</p>';
    }
  }

  public static function save_post($post_id, $post) {
    // no manual edits for now
  }

  public static function generate_reference_code(): string {
    $year = date('Y');
    $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($i = 0; $i < 30; $i++) {
      $rand = '';
      for ($j = 0; $j < 6; $j++) $rand .= $charset[random_int(0, strlen($charset)-1)];
      $code = "PB-$year-$rand";
      $existing = get_posts([
        'post_type' => self::CPT,
        'post_status' => 'any',
        'meta_key' => '_pb_ref',
        'meta_value' => $code,
        'fields' => 'ids',
        'posts_per_page' => 1
      ]);
      if (empty($existing)) return $code;
    }
    return "PB-$year-" . bin2hex(random_bytes(3));
  }

  public static function get_all_fields($request_id) {
    $modulo_id = intval(get_post_meta($request_id, '_pb_modulo_id', true));
    $schema = $modulo_id ? PB_RF_Moduli::schema($modulo_id) : PB_RF_Form::default_schema();
    $stored_fields = get_post_meta($request_id, '_pb_fields', true);
    if (!is_array($stored_fields)) {
      $stored_fields = [];
    }

    $out = [];
    $ref = get_post_meta($request_id, '_pb_ref', true);
    $out['Numero pratica'] = $ref;

    if ($modulo_id) {
      $out['Modulo'] = get_the_title($modulo_id);
    }

    foreach ($schema as $field) {
      $raw_name = $field['name'] ?? '';
      $name = sanitize_key($raw_name);
      if (!$name) continue;
      $label = $field['label'] ?? $name;
      $val = $stored_fields[$name] ?? ($raw_name ? ($stored_fields[$raw_name] ?? '') : '');

      if (($field['type'] ?? '') === 'select') {
        $val = self::select_label_from_schema($field, $val);
      }
      if (($field['type'] ?? '') === 'select_sede' && $val) {
        $val = $val . ' - ' . get_the_title(intval($val));
      }

      $out[$label] = $val;
    }
    return $out;
  }

  public static function build_template_vars($request_id) {
    $ref = get_post_meta($request_id, '_pb_ref', true);
    $stored_fields = get_post_meta($request_id, '_pb_fields', true);
    if (!is_array($stored_fields)) {
      $stored_fields = [];
    }

    $sede_id = intval(get_post_meta($request_id, '_pb_sede_id', true));
    if (!$sede_id) {
      $sede_id = intval(self::stored_field_value($stored_fields, 'sede_id'));
    }
    $sede_nome = $sede_id ? get_the_title($sede_id) : '';
    $sede_addr = $sede_id ? PB_RF_Sedi::full_address($sede_id) : '';

    $gen_nome = get_post_meta($request_id, '_pb_gen_nome', true);
    if ($gen_nome === '') {
      $gen_nome = self::stored_field_value($stored_fields, 'genitore_nome');
    }
    $gen_email = get_post_meta($request_id, '_pb_gen_email', true);
    if ($gen_email === '') {
      $gen_email = self::stored_field_value($stored_fields, 'genitore_email');
    }
    $gen_tel = get_post_meta($request_id, '_pb_gen_tel', true);
    if ($gen_tel === '') {
      $gen_tel = self::stored_field_value($stored_fields, 'genitore_tel');
    }
    $b_nome = get_post_meta($request_id, '_pb_b_nome', true);
    if ($b_nome === '') {
      $b_nome = self::stored_field_value($stored_fields, 'bambino_nome');
    }
    $b_nascita = get_post_meta($request_id, '_pb_b_nascita', true);
    if ($b_nascita === '') {
      $b_nascita = self::stored_field_value($stored_fields, 'bambino_nascita');
    }
    $b_cf = get_post_meta($request_id, '_pb_b_cf', true);
    if ($b_cf === '') {
      $b_cf = self::stored_field_value($stored_fields, 'bambino_cf');
    }
    $note = get_post_meta($request_id, '_pb_note', true);
    if ($note === '') {
      $note = self::stored_field_value($stored_fields, 'note');
    }

    $vars = [
      'numero_pratica' => $ref,
      'data_oggi' => date_i18n('d/m/Y'),
      'genitore_nome' => $gen_nome,
      'genitore_email' => $gen_email,
      'genitore_tel' => $gen_tel,
      'bambino_nome' => $b_nome,
      'bambino_nascita' => $b_nascita,
      'bambino_cf' => $b_cf,
      'sede_nome' => $sede_nome,
      'sede_indirizzo_completo' => $sede_addr,
      'note' => $note,
    ];

    $modulo_id = intval(get_post_meta($request_id, '_pb_modulo_id', true));
    $schema = $modulo_id ? PB_RF_Moduli::schema($modulo_id) : PB_RF_Form::default_schema();

    foreach ($schema as $field) {
      $raw_name = $field['name'] ?? '';
      $name = sanitize_key($raw_name);
      if (!$name) continue;
      $val = $stored_fields[$name] ?? ($raw_name ? ($stored_fields[$raw_name] ?? '') : '');
      if (($field['type'] ?? '') === 'select') {
        $val = self::select_label_from_schema($field, $val);
      }
      if (($field['type'] ?? '') === 'select_sede' && $val) {
        $val = get_the_title(intval($val));
      }
      $vars[$name] = $val;
      if ($raw_name && $raw_name !== $name) {
        $vars[$raw_name] = $val;
      }
    }

    return $vars;
  }

  private static function stored_field_value($stored_fields, $key) {
    if (!is_array($stored_fields)) {
      return '';
    }
    $sanitized = sanitize_key($key);
    if (array_key_exists($key, $stored_fields)) {
      return $stored_fields[$key];
    }
    if ($sanitized && array_key_exists($sanitized, $stored_fields)) {
      return $stored_fields[$sanitized];
    }
    return '';
  }

  private static function select_label_from_schema($field, $value) {
    if (!is_array($field) || empty($field['options'])) {
      return $value;
    }
    foreach ($field['options'] as $option) {
      if ((string)($option['value'] ?? '') === (string)$value) {
        return $option['label'] ?? $value;
      }
    }
    return $value;
  }
}
