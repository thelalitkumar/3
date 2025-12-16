<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Define TCPDF paths
define('K_PATH_FONTS', APPPATH . 'third_party/PDFMerger/tcpdf/fonts/');
require_once(APPPATH.'third_party/PDFMerger/tcpdf/tcpdf.php');
require_once(APPPATH.'third_party/PDFMerger/tcpdf/tcpdi.php');

class PdfConverter extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // Prevent PHP warnings from corrupting the PDF output
        ini_set('display_errors', 0);
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    }

    public function index() {
        $this->load->view('pdf_upload');
    }

    public function process() {
        $tempFilesToClean = [];

        try {
            // 1. Basic Validation
            if (empty($_FILES['pdf_files']['name'][0])) {
                throw new Exception("Please upload files.");
            }

            // Get selected compression level
            // /screen = 72 dpi (smallest size)
            // /ebook  = 150 dpi (average size, good readable quality)
            // /printer = 300 dpi (larger size)
            $compressionLevel = $this->input->post('compression') ?: '/ebook';

            // Initialize TCPDI
            $pdf = new TCPDI();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // 2. Loop through uploaded files
            $count = count($_FILES['pdf_files']['name']);
            
            for ($i = 0; $i < $count; $i++) {
                
                // Check for upload errors
                if ($_FILES['pdf_files']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue; // Skip failed uploads
                }

                $uploadedFilePath = $_FILES['pdf_files']['tmp_name'][$i];
                
                // Define path for the compressed/fixed file
                $compressedFile = tempnam(sys_get_temp_dir(), 'pdf_compressed_');
                $tempFilesToClean[] = $compressedFile;

                // ---------------------------------------------------------
                // 3. GHOSTSCRIPT: Compress & Fix Version
                // ---------------------------------------------------------
                $cmd = sprintf(
                    "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=%s -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s",
                    escapeshellarg($compressionLevel),  // e.g. /ebook
                    escapeshellarg($compressedFile),    // Output
                    escapeshellarg($uploadedFilePath)   // Input
                );

                exec($cmd, $output, $return_var);

                // Check if Ghostscript succeeded
                if ($return_var === 0 && filesize($compressedFile) > 0) {
                    $sourceFile = $compressedFile;
                } else {
                    // Fallback to original if GS fails (though compression won't happen)
                    $sourceFile = $uploadedFilePath;
                    log_message('error', 'Ghostscript compression failed for file index: ' . $i);
                }

                // ---------------------------------------------------------
                // 4. TCPDI: Merge the File
                // ---------------------------------------------------------
                try {
                    $pageCount = $pdf->setSourceFile($sourceFile);
                    for ($p = 1; $p <= $pageCount; $p++) {
                        $tplIdx = $pdf->importPage($p);
                        $pdf->AddPage();
                        $pdf->useTemplate($tplIdx);
                    }
                } catch (Exception $e) {
                    // Skip this specific file if it's unreadable
                    log_message('error', 'TCPDI Import Error: ' . $e->getMessage());
                    continue;
                }
            }

            // 5. Output Final PDF
            if (ob_get_length()) ob_end_clean();
            
            // cleanup temp files before exit (CodeIgniter handles $_FILES cleanup automatically)
            $this->cleanup($tempFilesToClean);

            $pdf->Output('compressed_merged.pdf', 'D'); // 'I' for Inline browser view, 'D' for Download

        } catch (Exception $e) {
            $this->cleanup($tempFilesToClean);
            if (ob_get_length()) ob_end_clean();
            show_error($e->getMessage());
        }
    }

    private function cleanup($files) {
        foreach ($files as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }
}
