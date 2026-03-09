<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Moduli {
  const CPT = 'pb_rf_modulo';

  public static function register() {
    register_post_type(self::CPT, [
      'labels' => ['name' => 'Moduli', 'singular_name' => 'Modulo'],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-feedback',
      'supports' => ['title'],
    ]);
  }

  public static function metaboxes() {
    add_meta_box('pb_rf_mod_tpl', 'Template DOCX e impostazioni', [self::class, 'render_metabox'], self::CPT, 'normal', 'high');
  }

  public static function render_metabox($post) {
    wp_nonce_field('pb_rf_mod_save', 'pb_rf_mod_nonce');
    $tpl = self::template_filename($post->ID);
    $html_tpl = self::html_template_filename($post->ID);
    $header_tpl = self::html_header_filename($post->ID);
    $footer_tpl = self::html_footer_filename($post->ID);
    $schema = get_post_meta($post->ID, '_pb_schema_json', true);
    $mail_sub = get_post_meta($post->ID, '_pb_mail_subject', true);
    $mail_body = get_post_meta($post->ID, '_pb_mail_body', true);
    $auto_send = get_post_meta($post->ID, '_pb_auto_send', true);
    $available_templates = self::available_templates();
    $available_html_templates = PB_RF_Html::available_templates();
    $template_path = PB_RF_DOCX_PATH . '/' . $tpl;
    $html_template_path = $html_tpl ? PB_RF_HTML_PATH . '/' . $html_tpl : '';
    $header_template_path = $header_tpl ? PB_RF_HTML_PATH . '/' . $header_tpl : '';
    $footer_template_path = $footer_tpl ? PB_RF_HTML_PATH . '/' . $footer_tpl : '';
    $template_exists = is_readable($template_path);
    $html_template_exists = $html_template_path && is_readable($html_template_path);
    $header_template_exists = !$header_template_path || is_readable($header_template_path);
    $footer_template_exists = !$footer_template_path || is_readable($footer_template_path);

    if (!$schema) {
      $schema = json_encode(PB_RF_Form::default_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    ?>
    <p><b>Template DOCX:</b> carica un file DOCX in <code><?php echo esc_html(PB_RF_DOCX_PATH); ?></code> e inserisci qui il nome file (es. <code>template.docx</code>) oppure un nome diverso per questo modulo.</p>
    <p><input style="width:100%" type="text" name="pb_template_docx" value="<?php echo esc_attr($tpl); ?>"></p>
    <?php if ($template_exists) : ?>
      <p style="color:#137333"><b>Template attivo:</b> <?php echo esc_html($tpl); ?></p>
    <?php else : ?>
      <p style="color:#b32d2e"><b>Template non trovato:</b> <?php echo esc_html($tpl); ?></p>
    <?php endif; ?>
    <?php if (!empty($available_templates)) : ?>
      <p><b>Template disponibili:</b> <?php echo esc_html(implode(', ', $available_templates)); ?></p>
    <?php else : ?>
      <p style="color:#b32d2e">Nessun file <code>.docx</code> trovato nella cartella template.</p>
    <?php endif; ?>

    <hr>
    <p><b>Template HTML</b> (prioritario rispetto al DOCX): carica i file in <code><?php echo esc_html(PB_RF_HTML_PATH); ?></code>.</p>
    <p>Template principale<br><input style="width:100%" type="text" name="pb_template_html" value="<?php echo esc_attr($html_tpl); ?>" placeholder="template.html"></p>
    <?php if ($html_tpl !== '') : ?>
      <?php if ($html_template_exists) : ?>
        <p style="color:#137333"><b>Template HTML attivo:</b> <?php echo esc_html($html_tpl); ?></p>
      <?php else : ?>
        <p style="color:#b32d2e"><b>Template HTML non trovato:</b> <?php echo esc_html($html_tpl); ?></p>
      <?php endif; ?>
    <?php else : ?>
      <p style="color:#777">Vuoto: il modulo usera il fallback DOCX.</p>
    <?php endif; ?>
    <p>Header HTML opzionale<br><input style="width:100%" type="text" name="pb_header_html" value="<?php echo esc_attr($header_tpl); ?>" placeholder="header.html"></p>
    <?php if ($header_tpl !== '' && !$header_template_exists) : ?>
      <p style="color:#b32d2e"><b>Header HTML non trovato:</b> <?php echo esc_html($header_tpl); ?></p>
    <?php endif; ?>
    <p>Footer HTML opzionale<br><input style="width:100%" type="text" name="pb_footer_html" value="<?php echo esc_attr($footer_tpl); ?>" placeholder="footer.html"></p>
    <?php if ($footer_tpl !== '' && !$footer_template_exists) : ?>
      <p style="color:#b32d2e"><b>Footer HTML non trovato:</b> <?php echo esc_html($footer_tpl); ?></p>
    <?php endif; ?>
    <?php if (!empty($available_html_templates)) : ?>
      <p><b>Template HTML disponibili:</b> <?php echo esc_html(implode(', ', $available_html_templates)); ?></p>
    <?php else : ?>
      <p style="color:#777">Nessun file <code>.html</code> trovato nella cartella HTML.</p>
    <?php endif; ?>

    <hr>
    <p><b>Schema campi (JSON)</b> — editabile. Tipi: text, email, date, textarea, select. Per select: "options": [{"value":"1","label":"..."}].</p>
    <textarea name="pb_schema_json" style="width:100%;min-height:260px;font-family:monospace;"><?php echo esc_textarea($schema); ?></textarea>

    <hr>
    <p><b>Email</b> (puoi usare placeholder tipo ${numero_pratica})</p>
    <p>Oggetto<br><input style="width:100%" type="text" name="pb_mail_subject" value="<?php echo esc_attr($mail_sub); ?>"></p>
    <p>Testo<br><textarea name="pb_mail_body" style="width:100%;min-height:120px;"><?php echo esc_textarea($mail_body); ?></textarea></p>
    <p><label><input type="checkbox" name="pb_auto_send" value="1" <?php checked($auto_send, '1'); ?>> Invia automaticamente il PDF dopo “Genera PDF”</label></p>

    <p><b>Placeholder disponibili (base):</b> ${numero_pratica}, ${data_oggi}, ${genitore_nome}, ${genitore_email}, ${genitore_tel}, ${bambino_nome}, ${bambino_nascita}, ${bambino_cf}, ${sede_nome}, ${sede_indirizzo_completo}, ${note}</p>
    <?php
  }

  public static function save_post($post_id, $post) {
    if ($post->post_type !== self::CPT) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['pb_rf_mod_nonce']) || !wp_verify_nonce($_POST['pb_rf_mod_nonce'], 'pb_rf_mod_save')) return;

    $template = self::normalize_template_filename(isset($_POST['pb_template_docx']) ? $_POST['pb_template_docx'] : '');
    update_post_meta($post_id, '_pb_template_docx', $template ?: 'template.docx');
    update_post_meta($post_id, '_pb_template_html', PB_RF_Html::normalize_template_filename(isset($_POST['pb_template_html']) ? $_POST['pb_template_html'] : ''));
    update_post_meta($post_id, '_pb_header_html', PB_RF_Html::normalize_template_filename(isset($_POST['pb_header_html']) ? $_POST['pb_header_html'] : ''));
    update_post_meta($post_id, '_pb_footer_html', PB_RF_Html::normalize_template_filename(isset($_POST['pb_footer_html']) ? $_POST['pb_footer_html'] : ''));

    $schema = wp_unslash(isset($_POST['pb_schema_json']) ? $_POST['pb_schema_json'] : '');
    // Validate JSON, fallback to default
    $decoded = json_decode($schema, true);
    if (!is_array($decoded)) {
      $decoded = PB_RF_Form::default_schema();
      $schema = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    update_post_meta($post_id, '_pb_schema_json', $schema);

    update_post_meta($post_id, '_pb_mail_subject', sanitize_text_field(isset($_POST['pb_mail_subject']) ? $_POST['pb_mail_subject'] : ''));
    update_post_meta($post_id, '_pb_mail_body', sanitize_textarea_field(isset($_POST['pb_mail_body']) ? $_POST['pb_mail_body'] : ''));
    update_post_meta($post_id, '_pb_auto_send', !empty($_POST['pb_auto_send']) ? '1' : '0');
  }

  public static function schema($modulo_id) {
    $schema = get_post_meta($modulo_id, '_pb_schema_json', true);
    $decoded = json_decode($schema, true);
    return is_array($decoded) ? $decoded : PB_RF_Form::default_schema();
  }

  public static function template_filename($modulo_id) {
    $tpl = get_post_meta($modulo_id, '_pb_template_docx', true);
    $tpl = self::normalize_template_filename($tpl);
    return $tpl ?: 'template.docx';
  }

  public static function html_template_filename($modulo_id) {
    return PB_RF_Html::normalize_template_filename(get_post_meta($modulo_id, '_pb_template_html', true));
  }

  public static function html_header_filename($modulo_id) {
    return PB_RF_Html::normalize_template_filename(get_post_meta($modulo_id, '_pb_header_html', true));
  }

  public static function html_footer_filename($modulo_id) {
    return PB_RF_Html::normalize_template_filename(get_post_meta($modulo_id, '_pb_footer_html', true));
  }

  public static function resolve_template($modulo_id) {
    $configured = self::template_filename($modulo_id);
    $available = self::available_templates();
    $candidates = array_values(array_unique(array_filter([
      $configured,
      'template.docx',
    ])));

    foreach ($candidates as $candidate) {
      $path = PB_RF_DOCX_PATH . '/' . $candidate;
      if (is_readable($path)) {
        return [
          'filename' => $candidate,
          'path' => $path,
          'fallback_used' => $candidate !== $configured,
          'available' => $available,
        ];
      }
    }

    if (count($available) === 1) {
      $fallback = $available[0];
      return [
        'filename' => $fallback,
        'path' => PB_RF_DOCX_PATH . '/' . $fallback,
        'fallback_used' => true,
        'available' => $available,
      ];
    }

    return [
      'filename' => $configured,
      'path' => '',
      'fallback_used' => false,
      'available' => $available,
    ];
  }

  public static function available_templates() {
    if (!is_dir(PB_RF_DOCX_PATH)) {
      return [];
    }

    $files = glob(PB_RF_DOCX_PATH . '/*.docx');
    if (!is_array($files)) {
      return [];
    }

    $templates = array_map('basename', $files);
    natcasesort($templates);
    return array_values(array_unique($templates));
  }

  private static function normalize_template_filename($value) {
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
}
