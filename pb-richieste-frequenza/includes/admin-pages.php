<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Admin_Pages {
  const MENU_SLUG_DIAGNOSTICA = 'pb-rf-diagnostica';
  const MENU_SLUG_TEMPLATES = 'pb-rf-templates';
  const MENU_SLUG_HTML_TEMPLATES = 'pb-rf-html-templates';

  public static function register_menu() {
    add_menu_page(
      'PB Richieste Frequenza',
      'PB Richieste',
      'manage_options',
      self::MENU_SLUG_DIAGNOSTICA,
      [self::class, 'render_diagnostics'],
      'dashicons-clipboard',
      30
    );

    add_submenu_page(
      self::MENU_SLUG_DIAGNOSTICA,
      'Diagnostica',
      'Diagnostica',
      'manage_options',
      self::MENU_SLUG_DIAGNOSTICA,
      [self::class, 'render_diagnostics']
    );

    add_submenu_page(
      self::MENU_SLUG_DIAGNOSTICA,
      'Template DOCX',
      'Template DOCX',
      'manage_options',
      self::MENU_SLUG_TEMPLATES,
      [self::class, 'render_templates']
    );

    add_submenu_page(
      self::MENU_SLUG_DIAGNOSTICA,
      'Template HTML',
      'Template HTML',
      'manage_options',
      self::MENU_SLUG_HTML_TEMPLATES,
      [self::class, 'render_html_templates']
    );
  }

  public static function handle_template_upload() {
    if (!current_user_can('manage_options')) {
      wp_die('Non autorizzato.');
    }
    check_admin_referer('pb_rf_upload_template');

    if (empty($_FILES['pb_rf_template_file']['name'])) {
      wp_safe_redirect(add_query_arg(['pb_rf_error' => 'missing'], menu_page_url(self::MENU_SLUG_TEMPLATES, false)));
      exit;
    }

    PB_RF_Storage::ensure_storage();
    $file = $_FILES['pb_rf_template_file'];
    $filename = sanitize_file_name($file['name']);

    if (!preg_match('/\.docx$/i', $filename)) {
      wp_safe_redirect(add_query_arg(['pb_rf_error' => 'invalid'], menu_page_url(self::MENU_SLUG_TEMPLATES, false)));
      exit;
    }

    $overrides = [
      'test_form' => false,
      'mimes' => ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];
    $uploaded = wp_handle_upload($file, $overrides);

    if (!empty($uploaded['error'])) {
      wp_safe_redirect(add_query_arg(['pb_rf_error' => 'upload'], menu_page_url(self::MENU_SLUG_TEMPLATES, false)));
      exit;
    }

    $target = trailingslashit(PB_RF_DOCX_PATH) . $filename;
    if (!@rename($uploaded['file'], $target)) {
      @unlink($uploaded['file']);
      wp_safe_redirect(add_query_arg(['pb_rf_error' => 'move'], menu_page_url(self::MENU_SLUG_TEMPLATES, false)));
      exit;
    }

    wp_safe_redirect(add_query_arg(['pb_rf_ok' => '1'], menu_page_url(self::MENU_SLUG_TEMPLATES, false)));
    exit;
  }

  public static function render_diagnostics() {
    if (!current_user_can('manage_options')) {
      wp_die('Non autorizzato.');
    }

    $request_id = intval($_GET['request_id'] ?? 0);
    $requests = get_posts([
      'post_type' => PB_RF_Richieste::CPT,
      'post_status' => 'publish',
      'numberposts' => 50,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    $vars = $request_id ? PB_RF_Richieste::build_template_vars($request_id) : [];
    $schema = [];
    if ($request_id) {
      $modulo_id = intval(get_post_meta($request_id, '_pb_modulo_id', true));
      $schema = $modulo_id ? PB_RF_Moduli::schema($modulo_id) : PB_RF_Form::default_schema();
    }

    ?>
    <div class="wrap">
      <h1>Diagnostica Template</h1>
      <p>Seleziona una richiesta per vedere i placeholder disponibili e i valori che verranno inseriti nel DOCX.</p>

      <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG_DIAGNOSTICA); ?>">
        <select name="request_id">
          <option value="">Seleziona richiesta...</option>
          <?php foreach ($requests as $req) : ?>
            <option value="<?php echo intval($req->ID); ?>" <?php selected($request_id, $req->ID); ?>>
              <?php echo esc_html($req->post_title); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="button">Apri</button>
      </form>

      <?php if ($request_id) : ?>
        <h2>Placeholder consigliati</h2>
        <table class="widefat striped">
          <thead>
            <tr>
              <th>Placeholder</th>
              <th>Valore attuale</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vars as $key => $value) : ?>
              <tr>
                <td><code><?php echo esc_html('${' . $key . '}'); ?></code></td>
                <td><?php echo esc_html($value); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!empty($schema)) : ?>
          <h2>Schema del modulo</h2>
          <table class="widefat striped">
            <thead>
              <tr>
                <th>Campo</th>
                <th>Tipo</th>
                <th>Placeholder</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($schema as $field) : ?>
                <?php
                  $name = sanitize_key($field['name'] ?? '');
                  if (!$name) continue;
                ?>
                <tr>
                  <td><?php echo esc_html($field['label'] ?? $name); ?></td>
                  <td><?php echo esc_html($field['type'] ?? 'text'); ?></td>
                  <td><code><?php echo esc_html('${' . $name . '}'); ?></code></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <p><strong>Nota:</strong> i placeholder devono essere testo semplice, senza interruzioni o formattazioni multiple nella stessa parola (Word può spezzarli).</p>
      <?php endif; ?>
    </div>
    <?php
  }

  public static function render_templates() {
    if (!current_user_can('manage_options')) {
      wp_die('Non autorizzato.');
    }

    PB_RF_Storage::ensure_storage();
    $files = [];
    if (is_dir(PB_RF_DOCX_PATH)) {
      $files = array_values(array_filter(scandir(PB_RF_DOCX_PATH), function ($file) {
        return $file !== '.' && $file !== '..' && preg_match('/\.docx$/i', $file);
      }));
    }

    $error = sanitize_text_field($_GET['pb_rf_error'] ?? '');
    $ok = !empty($_GET['pb_rf_ok']);

    ?>
    <div class="wrap">
      <h1>Template DOCX</h1>

      <?php if ($ok) : ?>
        <div class="notice notice-success"><p>Template caricato correttamente.</p></div>
      <?php elseif ($error) : ?>
        <div class="notice notice-error"><p>Errore nel caricamento del template (<?php echo esc_html($error); ?>).</p></div>
      <?php endif; ?>

      <h2>Carica nuovo template</h2>
      <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('pb_rf_upload_template'); ?>
        <input type="hidden" name="action" value="pb_rf_upload_template">
        <input type="file" name="pb_rf_template_file" accept=".docx" required>
        <button class="button button-primary">Carica</button>
      </form>

      <h2>Template disponibili</h2>
      <?php if (empty($files)) : ?>
        <p>Nessun template DOCX presente.</p>
      <?php else : ?>
        <ul>
          <?php foreach ($files as $file) : ?>
            <li><code><?php echo esc_html($file); ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <p>I template vengono salvati in <code><?php echo esc_html(PB_RF_DOCX_PATH); ?></code>.</p>
    </div>
    <?php
  }

  public static function handle_html_template_save() {
    if (!current_user_can('manage_options')) {
      wp_die('Non autorizzato.');
    }
    check_admin_referer('pb_rf_save_html_template');

    PB_RF_Storage::ensure_storage();
    $filename = sanitize_file_name($_POST['pb_rf_html_filename'] ?? '');
    $content = wp_unslash($_POST['pb_rf_html_content'] ?? '');

    if (!$filename) {
      wp_safe_redirect(add_query_arg(['pb_rf_error' => 'missing'], menu_page_url(self::MENU_SLUG_HTML_TEMPLATES, false)));
      exit;
    }

    if (!preg_match('/\.html$/i', $filename)) {
      $filename .= '.html';
    }

    $target = trailingslashit(PB_RF_HTML_PATH) . $filename;
    if (file_put_contents($target, $content) === false) {
      wp_safe_redirect(add_query_arg(['pb_rf_error' => 'write'], menu_page_url(self::MENU_SLUG_HTML_TEMPLATES, false)));
      exit;
    }

    wp_safe_redirect(add_query_arg(['pb_rf_ok' => '1', 'file' => rawurlencode($filename)], menu_page_url(self::MENU_SLUG_HTML_TEMPLATES, false)));
    exit;
  }

  public static function render_html_templates() {
    if (!current_user_can('manage_options')) {
      wp_die('Non autorizzato.');
    }

    PB_RF_Storage::ensure_storage();
    $files = [];
    if (is_dir(PB_RF_HTML_PATH)) {
      $files = array_values(array_filter(scandir(PB_RF_HTML_PATH), function ($file) {
        return $file !== '.' && $file !== '..' && preg_match('/\.html$/i', $file);
      }));
    }

    $selected = sanitize_text_field($_GET['file'] ?? '');
    $content = '';
    if ($selected && in_array($selected, $files, true)) {
      $content = file_get_contents(trailingslashit(PB_RF_HTML_PATH) . $selected);
    }

    $error = sanitize_text_field($_GET['pb_rf_error'] ?? '');
    $ok = !empty($_GET['pb_rf_ok']);
    ?>
    <div class="wrap">
      <h1>Template HTML</h1>

      <?php if ($ok) : ?>
        <div class="notice notice-success"><p>Template salvato correttamente.</p></div>
      <?php elseif ($error) : ?>
        <div class="notice notice-error"><p>Errore nel salvataggio del template (<?php echo esc_html($error); ?>).</p></div>
      <?php endif; ?>

      <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG_HTML_TEMPLATES); ?>">
        <select name="file">
          <option value="">Nuovo template...</option>
          <?php foreach ($files as $file) : ?>
            <option value="<?php echo esc_attr($file); ?>" <?php selected($selected, $file); ?>>
              <?php echo esc_html($file); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="button">Apri</button>
      </form>

      <h2>Editor template</h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('pb_rf_save_html_template'); ?>
        <input type="hidden" name="action" value="pb_rf_save_html_template">
        <p>
          <label>Nome file<br>
            <input type="text" name="pb_rf_html_filename" value="<?php echo esc_attr($selected); ?>" placeholder="template.html" style="width:320px;">
          </label>
        </p>
        <p>
          <label>Contenuto HTML (placeholder: <code>${nome_campo}</code>)</label>
        </p>
        <textarea name="pb_rf_html_content" rows="20" style="width:100%;font-family:monospace;"><?php echo esc_textarea($content); ?></textarea>
        <p>
          <button class="button button-primary">Salva template</button>
        </p>
      </form>

      <p>I template vengono salvati in <code><?php echo esc_html(PB_RF_HTML_PATH); ?></code>.</p>
    </div>
    <?php
  }
}
