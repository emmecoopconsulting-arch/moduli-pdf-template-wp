<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Form {
  public static function default_schema() {
    return [
      ['name'=>'genitore_nome','label'=>'Nome e Cognome','type'=>'text','required'=>true,'group'=>'Dati genitore'],
      ['name'=>'genitore_email','label'=>'Email','type'=>'email','required'=>true,'group'=>'Dati genitore'],
      ['name'=>'genitore_tel','label'=>'Telefono','type'=>'text','required'=>false,'group'=>'Dati genitore'],

      ['name'=>'bambino_nome','label'=>'Nome e Cognome bambino','type'=>'text','required'=>true,'group'=>'Dati bambino'],
      ['name'=>'bambino_nascita','label'=>'Data di nascita','type'=>'date','required'=>true,'group'=>'Dati bambino'],
      ['name'=>'bambino_cf','label'=>'Codice fiscale bambino','type'=>'text','required'=>false,'group'=>'Dati bambino'],

      ['name'=>'sede_id','label'=>'Sede di frequenza','type'=>'select_sede','required'=>true,'group'=>'Frequenza'],

      ['name'=>'note','label'=>'Note','type'=>'textarea','required'=>false,'group'=>'Altro'],
    ];
  }

  public static function shortcode($atts) {
    $atts = shortcode_atts(['modulo' => ''], $atts);
    $modulo_id = intval($atts['modulo']);

    $schema = $modulo_id ? PB_RF_Moduli::schema($modulo_id) : self::default_schema();

    $action = esc_url(admin_url('admin-post.php'));
    $nonce  = wp_create_nonce('pb_rf_submit');

    $msg = '';
    if (!empty($_GET['pb_ok']) && !empty($_GET['ref'])) {
      $msg = '<div style="padding:12px;border:1px solid #ddd;background:#f7fff7;margin-bottom:12px;">
        Richiesta inviata. Numero pratica: <b>' . esc_html($_GET['ref']) . '</b>
      </div>';
    }

    ob_start();
    echo $msg;
    ?>
    <form method="post" action="<?php echo $action; ?>">
      <input type="hidden" name="action" value="pb_rf_submit">
      <input type="hidden" name="pb_nonce" value="<?php echo esc_attr($nonce); ?>">
      <input type="hidden" name="pb_modulo_id" value="<?php echo esc_attr($modulo_id); ?>">

      <?php
        $current_group = '';
        foreach ($schema as $f) {
          $group = $f['group'] ?? '';
          if ($group && $group !== $current_group) {
            if ($current_group !== '') echo '<hr>';
            echo '<h3>' . esc_html($group) . '</h3>';
            $current_group = $group;
          }
          echo self::render_field($f);
        }
      ?>

      <p>
        <label>
          <input required type="checkbox" name="privacy" value="1">
          Confermo di aver letto l’informativa privacy e autorizzo il trattamento dei dati per questa richiesta.
        </label>
      </p>

      <button type="submit">Invia richiesta</button>
    </form>
    <?php
    return ob_get_clean();
  }

  private static function render_field($f) {
    $name = sanitize_key($f['name'] ?? '');
    $label = $f['label'] ?? $name;
    $type = $f['type'] ?? 'text';
    $required = !empty($f['required']);
    $reqAttr = $required ? 'required' : '';
    $out = '<p><label>' . esc_html($label) . '<br>';

    if ($type === 'textarea') {
      $out .= '<textarea name="' . esc_attr($name) . '" rows="4" ' . $reqAttr . '></textarea>';
    } elseif ($type === 'select_sede') {
      $out .= '<select name="sede_id" ' . $reqAttr . '>';
      $out .= '<option value="">Seleziona...</option>';
      $sedi = get_posts(['post_type' => PB_RF_Sedi::CPT, 'post_status'=>'publish', 'numberposts'=>-1, 'orderby'=>'title', 'order'=>'ASC']);
      foreach ($sedi as $s) {
        $out .= '<option value="' . intval($s->ID) . '">' . esc_html($s->post_title) . '</option>';
      }
      $out .= '</select>';
    } elseif ($type === 'select') {
      $out .= '<select name="' . esc_attr($name) . '" ' . $reqAttr . '>';
      $out .= '<option value="">Seleziona...</option>';
      $opts = $f['options'] ?? [];
      if (is_array($opts)) {
        foreach ($opts as $o) {
          $out .= '<option value="' . esc_attr($o['value'] ?? '') . '">' . esc_html($o['label'] ?? '') . '</option>';
        }
      }
      $out .= '</select>';
    } else {
      $htmlType = in_array($type, ['text','email','date']) ? $type : 'text';
      $out .= '<input type="' . esc_attr($htmlType) . '" name="' . esc_attr($name) . '" ' . $reqAttr . '>';
    }

    $out .= '</label></p>';
    return $out;
  }

  public static function handle_submit() {
    if (!isset($_POST['pb_nonce']) || !wp_verify_nonce($_POST['pb_nonce'], 'pb_rf_submit')) {
      wp_die('Richiesta non valida (nonce).');
    }
    if (empty($_POST['privacy'])) wp_die('Devi accettare la privacy.');

    $modulo_id = intval($_POST['pb_modulo_id'] ?? 0);

    // Basic required fields (schema-driven would be next iteration)
    $gen_nome  = sanitize_text_field($_POST['genitore_nome'] ?? '');
    $gen_email = sanitize_email($_POST['genitore_email'] ?? '');
    $b_nome    = sanitize_text_field($_POST['bambino_nome'] ?? '');
    $b_nascita = sanitize_text_field($_POST['bambino_nascita'] ?? '');
    $sede_id   = intval($_POST['sede_id'] ?? 0);

    if (!$gen_nome || !$gen_email || !$b_nome || !$b_nascita || !$sede_id) {
      wp_die('Campi obbligatori mancanti.');
    }

    $ref = PB_RF_Richieste::generate_reference_code();

    $post_id = wp_insert_post([
      'post_type' => PB_RF_Richieste::CPT,
      'post_status' => 'publish',
      'post_title' => $ref,
    ], true);

    if (is_wp_error($post_id)) wp_die('Errore salvataggio richiesta.');

    update_post_meta($post_id, '_pb_ref', $ref);
    update_post_meta($post_id, '_pb_modulo_id', $modulo_id);

    update_post_meta($post_id, '_pb_gen_nome', $gen_nome);
    update_post_meta($post_id, '_pb_gen_email', $gen_email);
    update_post_meta($post_id, '_pb_gen_tel', sanitize_text_field($_POST['genitore_tel'] ?? ''));

    update_post_meta($post_id, '_pb_b_nome', $b_nome);
    update_post_meta($post_id, '_pb_b_nascita', $b_nascita);
    update_post_meta($post_id, '_pb_b_cf', sanitize_text_field($_POST['bambino_cf'] ?? ''));

    update_post_meta($post_id, '_pb_sede_id', $sede_id);
    update_post_meta($post_id, '_pb_note', sanitize_textarea_field($_POST['note'] ?? ''));

    // Email conferma (semplice)
    @wp_mail($gen_email, "Richiesta ricevuta: $ref", "Abbiamo ricevuto la tua richiesta.\nNumero pratica: $ref");

    $redirect = add_query_arg(['pb_ok' => '1', 'ref' => $ref], wp_get_referer() ?: home_url('/'));
    wp_safe_redirect($redirect);
    exit;
  }
}
