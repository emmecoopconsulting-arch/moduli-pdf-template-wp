<?php
/**
 * Plugin Name: PB Richieste Frequenza
 * Description: Richieste frequenza con moduli multipli, template DOCX, conversione PDF (LibreOffice) e invio email.
 * Version: 0.7.0
 * Author: EMME COOP Consulting
 */

if (!defined('ABSPATH')) exit;

// Storage directory inside uploads (protected by .htaccess)
define('PB_RF_UPLOAD_SUBDIR', 'richieste-frequenza');
define('PB_RF_BASE_PATH', trailingslashit(WP_CONTENT_DIR) . 'uploads/' . PB_RF_UPLOAD_SUBDIR);
define('PB_RF_DOCX_PATH', PB_RF_BASE_PATH . '/docx');
define('PB_RF_PDF_PATH',  PB_RF_BASE_PATH . '/pdf');
define('PB_RF_TMP_PATH',  PB_RF_BASE_PATH . '/tmp');

require_once __DIR__ . '/includes/bootstrap.php';

PB_RF_Bootstrap::init();
