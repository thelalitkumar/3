<?php
defined('BASEPATH') OR exit('No direct script access allowed');

define('K_PATH_FONTS', APPPATH . 'third_party/PDFMerger/tcpdf/fonts/');
require_once(APPPATH.'third_party/PDFMerger/tcpdf/tcpdf.php');
require_once(APPPATH.'third_party/PDFMerger/tcpdf/tcpdi.php');

class PdfConverter extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // Silence output to prevent PDF corruption
        ini_set('display_errors', 0);
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    }

    public function index() {
        $files = [
            'https://raw.githubusercontent.com/mozilla/pdf.js/master/test/pdfs/160F-2019.pdf',
            'https://storage.hrylabour.gov.in/uploads/factory_building_plan/factory_building_plan1761982577_1.pdf'
        ];

        // Temp files array to ensure cleanup
        $tempFilesToClean = [];

        try {
            if (!is_dir(K_PATH_FONTS)) {
                throw new Exception("Font directory not found.");
            }

            $pdf = new TCPDI();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            foreach ($files as $url) {
                // 1. Download File
                $original_temp = tempnam(sys_get_temp_dir(), 'pdf_orig_');
                $tempFilesToClean[] = $original_temp;
                
                $content = file_get_contents($url);
                if ($content === false) throw new Exception("Could not download: $url");
                file_put_contents($original_temp, $content);

                // 2. THE FIX: Convert to PDF 1.4 using Ghostscript
                // This forces the PDF into a format TCPDI can understand (removes Object Streams)
                $clean_temp = tempnam(sys_get_temp_dir(), 'pdf_clean_');
                $tempFilesToClean[] = $clean_temp;

                // Build Ghostscript command
                // -sDEVICE=pdfwrite      : Output as PDF
                // -dCompatibilityLevel=1.4 : Force Version 1.4 (Crucial for TCPDI)
                // -dPDFSETTINGS=/screen  : Optional: Optimization level (screen, ebook, printer, prepress)
                $cmd = sprintf(
    "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s",
    escapeshellarg($clean_temp),
    escapeshellarg($original_temp)
);
exec($cmd, $output, $return_var);

if ($return_var === 0 && filesize($clean_temp) > 0) {
    // SUCCESS: Use the clean file
    $source_file = $clean_temp;
} else {
    // FAILURE: Log it, and try to fall back to the original (risky but better than nothing)
    log_message('error', 'Ghostscript conversion failed for: ' . $url);
    $source_file = $original_temp;
}

                // 3. Import the (now cleaned) file
                try {
                    $pageCount = $pdf->setSourceFile($source_file);
                    for ($i = 1; $i <= $pageCount; $i++) {
                        $tplIdx = $pdf->importPage($i);
                        $pdf->AddPage();
                        $pdf->useTemplate($tplIdx);
                    }
                } catch (Exception $e) {
                    throw new Exception("Import failed for $url: " . $e->getMessage());
                }
            }

            // Cleanup Output Buffer
            if (ob_get_length()) ob_end_clean();

            $pdf->Output('merged_result.pdf', 'I'); 
            
            // Cleanup temp files
            $this->cleanup($tempFilesToClean);

        } catch (Exception $e) {
            $this->cleanup($tempFilesToClean);
            if (ob_get_length()) ob_end_clean();
            echo '<h3>Error:</h3> ' . $e->getMessage();
        }
    }

    private function cleanup($files) {
        foreach ($files as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }
}
