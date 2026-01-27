<?php
if (!defined('ABSPATH')) exit;

class PB_RF_Storage {
  public static function ensure_storage() {
    $paths = [PB_RF_BASE_PATH, PB_RF_DOCX_PATH, PB_RF_PDF_PATH, PB_RF_TMP_PATH];
    foreach ($paths as $p) {
      if (!file_exists($p)) {
        wp_mkdir_p($p);
      }
    }

    // Protect directory from direct web access (Apache .htaccess). Safe if ignored on Nginx.
    $ht = PB_RF_BASE_PATH . '/.htaccess';
    if (!file_exists($ht)) {
      @file_put_contents($ht, "Deny from all\n");
    }

    // Create an empty index.php too
    $idx = PB_RF_BASE_PATH . '/index.php';
    if (!file_exists($idx)) {
      @file_put_contents($idx, "<?php // Silence is golden.\n");
    }
  }

  public static function path_is_inside_base($path) {
    $base = realpath(PB_RF_BASE_PATH);
    $rp = realpath($path);
    if (!$base || !$rp) return false;
    return (strpos($rp, $base) === 0);
  }
}
