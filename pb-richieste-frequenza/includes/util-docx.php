<?php
if (!defined('ABSPATH')) exit;

/**
 * Minimal DOCX templating without external libraries.
 * Replaces placeholders like ${field} in common XML parts.
 *
 * NOTE: In Word, placeholders should be in normal text/table cells (not in shapes/textboxes).
 */
class PB_RF_Docx {
  public static function render_docx($template_path, $out_path, $vars) {
    if (!file_exists($template_path)) {
      throw new Exception('Template DOCX non trovato: ' . $template_path);
    }

    $tmpDir = self::mktempdir(PB_RF_TMP_PATH . '/docx-');
    $zip = new ZipArchive();
    if ($zip->open($template_path) !== true) {
      throw new Exception('Impossibile aprire il template DOCX.');
    }
    $zip->extractTo($tmpDir);
    $zip->close();

    $targets = [
      $tmpDir . '/word/document.xml',
      $tmpDir . '/word/header1.xml',
      $tmpDir . '/word/header2.xml',
      $tmpDir . '/word/footer1.xml',
      $tmpDir . '/word/footer2.xml',
    ];

    foreach ($targets as $file) {
      if (file_exists($file)) {
        $xml = file_get_contents($file);
        foreach ($vars as $k => $v) {
          $xml = str_replace('${' . $k . '}', self::xml_escape($v), $xml);
        }
        file_put_contents($file, $xml);
      }
    }

    // Re-zip as docx
    $outZip = new ZipArchive();
    if ($outZip->open($out_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
      self::rrmdir($tmpDir);
      throw new Exception('Impossibile creare DOCX output.');
    }
    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($files as $file) {
      $filePath = $file->getRealPath();
      $relPath = substr($filePath, strlen($tmpDir) + 1);
      if ($file->isDir()) continue;
      $outZip->addFile($filePath, $relPath);
    }
    $outZip->close();

    self::rrmdir($tmpDir);
    return $out_path;
  }

  public static function convert_to_pdf($docx_path, $outdir) {
    if (!file_exists($docx_path)) throw new Exception('DOCX non trovato per conversione PDF.');

    $cmd = sprintf(
      'cd /tmp && HOME=%s USER=www-data soffice --headless --nologo --nofirststartwizard --convert-to pdf --outdir %s %s 2>&1',
      escapeshellarg(PB_RF_TMP_PATH),
      escapeshellarg($outdir),
      escapeshellarg($docx_path)
    );
    exec($cmd, $output, $ret);
    if ($ret !== 0) {
      throw new Exception("Errore conversione PDF:\n" . implode("\n", $output));
    }

    $pdf_path = trailingslashit($outdir) . basename(preg_replace('/\.docx$/i', '.pdf', $docx_path));
    if (!file_exists($pdf_path)) throw new Exception('PDF non generato (file output mancante).');
    return $pdf_path;
  }

  private static function mktempdir($prefix) {
    $tempfile = tempnam(PB_RF_TMP_PATH, 'pb');
    if (file_exists($tempfile)) unlink($tempfile);
    $dir = $prefix . bin2hex(random_bytes(6));
    if (!mkdir($dir, 0770, true)) throw new Exception('Impossibile creare cartella temporanea.');
    return $dir;
  }

  private static function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
      if ($file->isDir()) rmdir($file->getRealPath());
      else unlink($file->getRealPath());
    }
    rmdir($dir);
  }

  private static function xml_escape($s) {
    $s = (string)$s;
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
  }
}
