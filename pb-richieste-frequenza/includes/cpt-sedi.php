<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Sedi {
  const CPT = 'pb_rf_sede';

  public static function register() {
    register_post_type(self::CPT, [
      'labels' => ['name' => 'Sedi', 'singular_name' => 'Sede'],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-location',
      'supports' => ['title'],
    ]);
  }

  public static function metaboxes() {
    add_meta_box('pb_rf_sede_addr', 'Indirizzo sede', [self::class, 'render_metabox'], self::CPT, 'normal', 'high');
  }

  public static function render_metabox($post) {
    wp_nonce_field('pb_rf_sede_save', 'pb_rf_sede_nonce');
    $via = get_post_meta($post->ID, '_pb_via', true);
    $cap = get_post_meta($post->ID, '_pb_cap', true);
    $comune = get_post_meta($post->ID, '_pb_comune', true);
    $prov = get_post_meta($post->ID, '_pb_prov', true);
    ?>
    <table class="form-table">
      <tr><th><label>Via</label></th><td><input style="width:100%" type="text" name="pb_via" value="<?php echo esc_attr($via); ?>"></td></tr>
      <tr><th><label>CAP</label></th><td><input type="text" name="pb_cap" value="<?php echo esc_attr($cap); ?>"></td></tr>
      <tr><th><label>Comune</label></th><td><input type="text" name="pb_comune" value="<?php echo esc_attr($comune); ?>"></td></tr>
      <tr><th><label>Provincia</label></th><td><input type="text" name="pb_prov" value="<?php echo esc_attr($prov); ?>" maxlength="2"></td></tr>
    </table>
    <p><b>Placeholder template:</b> ${sede_nome}, ${sede_indirizzo_completo}</p>
    <?php
  }

  public static function save_post($post_id, $post) {
    if ($post->post_type !== self::CPT) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['pb_rf_sede_nonce']) || !wp_verify_nonce($_POST['pb_rf_sede_nonce'], 'pb_rf_sede_save')) return;

    update_post_meta($post_id, '_pb_via', sanitize_text_field($_POST['pb_via'] ?? ''));
    update_post_meta($post_id, '_pb_cap', sanitize_text_field($_POST['pb_cap'] ?? ''));
    update_post_meta($post_id, '_pb_comune', sanitize_text_field($_POST['pb_comune'] ?? ''));
    update_post_meta($post_id, '_pb_prov', sanitize_text_field($_POST['pb_prov'] ?? ''));
  }

  public static function full_address($sede_id) {
    $via = get_post_meta($sede_id, '_pb_via', true);
    $cap = get_post_meta($sede_id, '_pb_cap', true);
    $comune = get_post_meta($sede_id, '_pb_comune', true);
    $prov = get_post_meta($sede_id, '_pb_prov', true);
    $parts = array_filter([$via, trim($cap . ' ' . $comune . ' ' . $prov)]);
    return implode(', ', $parts);
  }
}
