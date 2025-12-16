<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PdfConverter extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // We don't need TCPDF libraries anymore.
        // We only need standard PHP error handling.
    }

    public function index() {
        // Load your view (same as before)
        $this->load->view('pdf_upload');
    }

    public function process() {
        $filesToClean = [];

        try {
            // 1. Validation
            if (empty($_FILES['pdf_files']['name'][0])) {
                throw new Exception("No files uploaded.");
            }

            // Get Compression Level (default to ebook/medium)
            $compression = $this->input->post('compression') ?: '/ebook';
            
            // 2. Prepare Input Files for Command
            $inputFilesCommandString = "";
            $count = count($_FILES['pdf_files']['name']);

            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['pdf_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpPath = $_FILES['pdf_files']['tmp_name'][$i];
                    
                    // Sanitize path for shell command
                    $inputFilesCommandString .= " " . escapeshellarg($tmpPath);
                }
            }

            if (empty($inputFilesCommandString)) {
                throw new Exception("No valid files were uploaded.");
            }

            // 3. Define Output File
            $outputFile = tempnam(sys_get_temp_dir(), 'merged_gs_');
            $filesToClean[] = $outputFile;

            // 4. Construct Ghostscript Command
            // This SINGLE command cleans, compresses, and merges all files at once.
            // -dNOPAUSE -dBATCH : Exit when done
            // -sDEVICE=pdfwrite : Write to PDF
            // -sOutputFile=...  : Destination
            // ... followed by list of input files
            $cmd = sprintf(
                "gs -sDEVICE=pdfwrite -dPDFSETTINGS=%s -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s",
                escapeshellarg($compression),
                escapeshellarg($outputFile),
                $inputFilesCommandString
            );

            // 5. Execute Command
            $output = [];
            $return_var = 0;
            exec($cmd, $output, $return_var);

            // 6. Check Success and Output
            if ($return_var === 0 && file_exists($outputFile) && filesize($outputFile) > 0) {
                
                // Clear any previous output buffering
                if (ob_get_length()) ob_clean();

                // Set headers for PDF download/display
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="compressed_merged.pdf"');
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: ' . filesize($outputFile));
                header('Accept-Ranges: bytes');

                // Stream the file
                readfile($outputFile);
                
            } else {
                // Log the actual error from Ghostscript for debugging
                log_message('error', 'Ghostscript Merge Failed. Output: ' . implode(" ", $output));
                throw new Exception("Failed to merge PDF files. The files might be password protected or corrupted.");
            }

            // Cleanup
            $this->cleanup($filesToClean);

        } catch (Exception $e) {
            $this->cleanup($filesToClean);
            show_error($e->getMessage());
        }
    }

    private function cleanup($files) {
        foreach ($files as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }
}
