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
    $tpl = get_post_meta($post->ID, '_pb_template_docx', true);
    $schema = get_post_meta($post->ID, '_pb_schema_json', true);
    $mail_sub = get_post_meta($post->ID, '_pb_mail_subject', true);
    $mail_body = get_post_meta($post->ID, '_pb_mail_body', true);
    $auto_send = get_post_meta($post->ID, '_pb_auto_send', true);

    if (!$schema) {
      $schema = json_encode(PB_RF_Form::default_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    ?>
    <p><b>Template DOCX:</b> carica un file DOCX in <code><?php echo esc_html(PB_RF_DOCX_PATH); ?></code> e inserisci qui il nome file (es. <code>template.docx</code>) oppure un nome diverso per questo modulo.</p>
    <p><input style="width:100%" type="text" name="pb_template_docx" value="<?php echo esc_attr($tpl ?: 'template.docx'); ?>"></p>

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

    update_post_meta($post_id, '_pb_template_docx', sanitize_text_field($_POST['pb_template_docx'] ?? 'template.docx'));

    $schema = wp_unslash($_POST['pb_schema_json'] ?? '');
    // Validate JSON, fallback to default
    $decoded = json_decode($schema, true);
    if (!is_array($decoded)) {
      $decoded = PB_RF_Form::default_schema();
      $schema = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    update_post_meta($post_id, '_pb_schema_json', $schema);

    update_post_meta($post_id, '_pb_mail_subject', sanitize_text_field($_POST['pb_mail_subject'] ?? ''));
    update_post_meta($post_id, '_pb_mail_body', sanitize_textarea_field($_POST['pb_mail_body'] ?? ''));
    update_post_meta($post_id, '_pb_auto_send', !empty($_POST['pb_auto_send']) ? '1' : '0');
  }

  public static function schema($modulo_id) {
    $schema = get_post_meta($modulo_id, '_pb_schema_json', true);
    $decoded = json_decode($schema, true);
    return is_array($decoded) ? $decoded : PB_RF_Form::default_schema();
  }

  public static function template_filename($modulo_id) {
    $tpl = get_post_meta($modulo_id, '_pb_template_docx', true);
    return $tpl ? $tpl : 'template.docx';
  }
}
