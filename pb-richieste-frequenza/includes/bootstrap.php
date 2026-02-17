<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/util-storage.php';
require_once __DIR__ . '/util-docx.php';
require_once __DIR__ . '/util-mailer.php';

require_once __DIR__ . '/cpt-sedi.php';
require_once __DIR__ . '/cpt-moduli.php';
require_once __DIR__ . '/cpt-richieste.php';
require_once __DIR__ . '/shortcode-form.php';
require_once __DIR__ . '/admin-actions.php';

class PB_RF_Bootstrap {
  public static function init() {
    add_action('init', ['PB_RF_Sedi', 'register']);
    add_action('init', ['PB_RF_Moduli', 'register']);
    add_action('init', ['PB_RF_Richieste', 'register']);

    add_action('admin_init', ['PB_RF_Storage', 'ensure_storage']);
    add_action('init', ['PB_RF_Storage', 'ensure_storage']); // also for frontend submit

    add_action('add_meta_boxes', ['PB_RF_Sedi', 'metaboxes']);
    add_action('save_post', ['PB_RF_Sedi', 'save_post'], 10, 2);

    add_action('add_meta_boxes', ['PB_RF_Moduli', 'metaboxes']);
    add_action('save_post', ['PB_RF_Moduli', 'save_post'], 10, 2);

    add_action('add_meta_boxes', ['PB_RF_Richieste', 'metaboxes']);
    add_action('save_post', ['PB_RF_Richieste', 'save_post'], 10, 2);
    add_filter('manage_edit-' . PB_RF_Richieste::CPT . '_columns', ['PB_RF_Richieste', 'list_table_columns']);
    add_action('manage_' . PB_RF_Richieste::CPT . '_posts_custom_column', ['PB_RF_Richieste', 'render_list_table_column'], 10, 2);
    add_filter('views_edit-' . PB_RF_Richieste::CPT, ['PB_RF_Richieste', 'list_table_views']);
    add_action('pre_get_posts', ['PB_RF_Richieste', 'filter_requests_query']);

    add_shortcode('pb_richiesta_frequenza', ['PB_RF_Form', 'shortcode']);
    add_action('admin_post_nopriv_pb_rf_submit', ['PB_RF_Form', 'handle_submit']);
    add_action('admin_post_pb_rf_submit', ['PB_RF_Form', 'handle_submit']);

    add_action('admin_post_pb_rf_generate_pdf', ['PB_RF_Admin_Actions', 'generate_pdf']);
    add_action('admin_post_pb_rf_download_pdf', ['PB_RF_Admin_Actions', 'download_pdf']);
    add_action('admin_post_pb_rf_send_pdf', ['PB_RF_Admin_Actions', 'send_pdf']);
  }
}
