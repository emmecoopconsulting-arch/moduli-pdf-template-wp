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
    <form method="post" action="<?php echo $action; ?>" class="pb-rf-form">
      <input type="hidden" name="action" value="pb_rf_submit">
      <input type="hidden" name="pb_nonce" value="<?php echo esc_attr($nonce); ?>">
      <input type="hidden" name="pb_modulo_id" value="<?php echo esc_attr($modulo_id); ?>">

      <?php
        $current_group = '';
        foreach ($schema as $f) {
          $group = $f['group'] ?? '';
          if ($group && $group !== $current_group) {
            if ($current_group !== '') echo '</div></fieldset>';
            echo '<fieldset class="pb-rf-group"><legend>' . esc_html($group) . '</legend><div class="pb-rf-group-fields">';
            $current_group = $group;
          }
          echo self::render_field($f);
        }
        if ($current_group !== '') echo '</div></fieldset>';
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
    $placeholder = $f['placeholder'] ?? '';
    $help = $f['help'] ?? '';
    $reqAttr = $required ? 'required' : '';
    $out = '<p class="pb-rf-field"><label>' . esc_html($label) . '<br>';

    if ($type === 'textarea') {
      $out .= '<textarea name="' . esc_attr($name) . '" rows="4" ' . $reqAttr . ' placeholder="' . esc_attr($placeholder) . '"></textarea>';
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
      $htmlType = in_array($type, ['text','email','date','tel']) ? $type : 'text';
      $out .= '<input type="' . esc_attr($htmlType) . '" name="' . esc_attr($name) . '" ' . $reqAttr . ' placeholder="' . esc_attr($placeholder) . '">';
    }

    if ($help) {
      $out .= '<small class="pb-rf-help">' . esc_html($help) . '</small>';
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

    $schema = $modulo_id ? PB_RF_Moduli::schema($modulo_id) : self::default_schema();
    $values = [];
    $errors = [];

    foreach ($schema as $field) {
      $name = sanitize_key($field['name'] ?? '');
      if (!$name) continue;
      $type = $field['type'] ?? 'text';
      $required = !empty($field['required']);
      $raw = $_POST[$name] ?? '';

      if ($type === 'select_sede') {
        $value = intval($raw);
      } elseif ($type === 'email') {
        $value = sanitize_email(wp_unslash($raw));
      } elseif ($type === 'textarea') {
        $value = sanitize_textarea_field(wp_unslash($raw));
      } else {
        $value = sanitize_text_field(wp_unslash($raw));
      }

      if ($required) {
        $missing = ($type === 'select_sede') ? ($value === 0) : (trim((string)$value) === '');
        if ($missing) {
          $errors[] = $name;
        }
      }

      $values[$name] = $value;
    }

    if (!empty($errors)) {
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

    $meta_map = [
      'genitore_nome' => '_pb_gen_nome',
      'genitore_email' => '_pb_gen_email',
      'genitore_tel' => '_pb_gen_tel',
      'bambino_nome' => '_pb_b_nome',
      'bambino_nascita' => '_pb_b_nascita',
      'bambino_cf' => '_pb_b_cf',
      'sede_id' => '_pb_sede_id',
      'note' => '_pb_note',
    ];

    foreach ($meta_map as $field_name => $meta_key) {
      if (array_key_exists($field_name, $values)) {
        update_post_meta($post_id, $meta_key, $values[$field_name]);
      }
    }

    update_post_meta($post_id, '_pb_fields', $values);

    // Email conferma (semplice)
    $gen_email = $values['genitore_email'] ?? '';
    if ($gen_email) {
      @wp_mail($gen_email, "Richiesta ricevuta: $ref", "Abbiamo ricevuto la tua richiesta.\nNumero pratica: $ref");
    }

    $redirect = add_query_arg(['pb_ok' => '1', 'ref' => $ref], wp_get_referer() ?: home_url('/'));
    wp_safe_redirect($redirect);
    exit;
  }
}
