<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Richieste {
  const CPT = 'pb_richiesta_freq';
  const QUERY_VAR_STATUS = 'pb_rf_stato';
  const STATUS_PENDING = 'da_evadere';
  const STATUS_DONE = 'evase';

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

  public static function list_table_columns($columns) {
    $new_columns = [];
    foreach ($columns as $key => $label) {
      $new_columns[$key] = $label;
      if ($key === 'title') {
        $new_columns['pb_rf_stato'] = 'Stato';
      }
    }
    return $new_columns;
  }

  public static function render_list_table_column($column, $post_id) {
    if ($column !== 'pb_rf_stato') return;

    if (self::has_generated_pdf($post_id)) {
      echo '<span style="color:#137333;font-weight:600;">Evasa</span>';
      return;
    }
    echo '<span style="color:#b32d2e;font-weight:600;">Da evadere</span>';
  }

  public static function list_table_views($views) {
    global $typenow;
    if ($typenow !== self::CPT) return $views;

    $current = sanitize_key($_GET[self::QUERY_VAR_STATUS] ?? '');
    $pending_count = self::count_by_status(self::STATUS_PENDING);
    $done_count = self::count_by_status(self::STATUS_DONE);

    $pending_url = add_query_arg([self::QUERY_VAR_STATUS => self::STATUS_PENDING], admin_url('edit.php?post_type=' . self::CPT));
    $done_url = add_query_arg([self::QUERY_VAR_STATUS => self::STATUS_DONE], admin_url('edit.php?post_type=' . self::CPT));

    $views[self::STATUS_PENDING] = sprintf(
      '<a href="%s"%s>Da evadere <span class="count">(%d)</span></a>',
      esc_url($pending_url),
      $current === self::STATUS_PENDING ? ' class="current"' : '',
      $pending_count
    );
    $views[self::STATUS_DONE] = sprintf(
      '<a href="%s"%s>Evase <span class="count">(%d)</span></a>',
      esc_url($done_url),
      $current === self::STATUS_DONE ? ' class="current"' : '',
      $done_count
    );

    return $views;
  }

  public static function filter_requests_query($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    $post_type = $query->get('post_type');
    if ($post_type !== self::CPT) return;

    $status = sanitize_key($_GET[self::QUERY_VAR_STATUS] ?? '');
    if ($status === self::STATUS_DONE) {
      $query->set('meta_query', [[
        'key' => '_pb_pdf_path',
        'value' => '',
        'compare' => '!=',
      ]]);
      return;
    }
    if ($status === self::STATUS_PENDING) {
      $query->set('meta_query', [
        'relation' => 'OR',
        [
          'key' => '_pb_pdf_path',
          'compare' => 'NOT EXISTS',
        ],
        [
          'key' => '_pb_pdf_path',
          'value' => '',
          'compare' => '=',
        ],
      ]);
    }
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
    $keys = [
      'numero_pratica' => '_pb_ref',
      'modulo_id' => '_pb_modulo_id',
      'genitore_nome' => '_pb_gen_nome',
      'genitore_email' => '_pb_gen_email',
      'genitore_tel' => '_pb_gen_tel',
      'bambino_nome' => '_pb_b_nome',
      'bambino_nascita' => '_pb_b_nascita',
      'bambino_cf' => '_pb_b_cf',
      'sede_id' => '_pb_sede_id',
      'note' => '_pb_note',
    ];
    $out = [];
    foreach ($keys as $label => $meta) {
      $val = get_post_meta($request_id, $meta, true);
      if ($label === 'sede_id' && $val) $val = $val . ' - ' . get_the_title(intval($val));
      $out[$label] = $val;
    }
    return $out;
  }

  public static function build_template_vars($request_id) {
    $ref = get_post_meta($request_id, '_pb_ref', true);
    $sede_id = intval(get_post_meta($request_id, '_pb_sede_id', true));
    $sede_nome = $sede_id ? get_the_title($sede_id) : '';
    $sede_addr = $sede_id ? PB_RF_Sedi::full_address($sede_id) : '';

    return [
      'numero_pratica' => $ref,
      'data_oggi' => date_i18n('d/m/Y'),
      'genitore_nome' => get_post_meta($request_id, '_pb_gen_nome', true),
      'genitore_email' => get_post_meta($request_id, '_pb_gen_email', true),
      'genitore_tel' => get_post_meta($request_id, '_pb_gen_tel', true),
      'bambino_nome' => get_post_meta($request_id, '_pb_b_nome', true),
      'bambino_nascita' => get_post_meta($request_id, '_pb_b_nascita', true),
      'bambino_cf' => get_post_meta($request_id, '_pb_b_cf', true),
      'sede_nome' => $sede_nome,
      'sede_indirizzo_completo' => $sede_addr,
      'note' => get_post_meta($request_id, '_pb_note', true),
    ];
  }

  private static function has_generated_pdf($request_id) {
    $pdf_path = get_post_meta($request_id, '_pb_pdf_path', true);
    return !empty($pdf_path) && file_exists($pdf_path) && PB_RF_Storage::path_is_inside_base($pdf_path);
  }

  private static function count_by_status($status) {
    $args = [
      'post_type' => self::CPT,
      'post_status' => 'publish',
      'fields' => 'ids',
      'posts_per_page' => -1,
    ];

    if ($status === self::STATUS_DONE) {
      $args['meta_query'] = [[
        'key' => '_pb_pdf_path',
        'value' => '',
        'compare' => '!=',
      ]];
    } elseif ($status === self::STATUS_PENDING) {
      $args['meta_query'] = [
        'relation' => 'OR',
        [
          'key' => '_pb_pdf_path',
          'compare' => 'NOT EXISTS',
        ],
        [
          'key' => '_pb_pdf_path',
          'value' => '',
          'compare' => '=',
        ],
      ];
    }

    $query = new WP_Query($args);
    return intval($query->found_posts);
  }
}
