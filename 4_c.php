<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PdfConverter extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // Standard PHP error reporting
        ini_set('display_errors', 0); 
    }

    public function index() {
        // 1. List of File URLs to Download & Merge
        $fileUrls = [
            'https://storage.hrylabour.gov.in/uploads/factory_building_plan/factory_building_plan1761982577_1.pdf',
            'https://storage.hrylabour.gov.in/uploads/factory_building_plan/factory_building_plan1761982577_1.pdf'
            // Add more URLs here...
        ];

        $this->process_urls($fileUrls);
    }

    private function process_urls($urls) {
        $tempFiles = [];
        
        try {
            if (empty($urls)) {
                throw new Exception("No URLs provided.");
            }

            // 2. Download Files to Temp Directory
            $inputFilesCmd = "";
            
            foreach ($urls as $url) {
                // Create a temp file
                $tempPath = tempnam(sys_get_temp_dir(), 'pdf_dl_');
                $tempFiles[] = $tempPath;

                // Download content
                // Note: For production, consider using cURL for better timeout handling
                $content = file_get_contents($url);
                
                if ($content === false) {
                    throw new Exception("Failed to download file: " . $url);
                }

                file_put_contents($tempPath, $content);

                // Add to Ghostscript command string
                $inputFilesCmd .= " " . escapeshellarg($tempPath);
            }

            // 3. Define Output File
            $outputFile = tempnam(sys_get_temp_dir(), 'merged_final_');
            $tempFiles[] = $outputFile;

            // 4. Ghostscript Command
            // -dPDFSETTINGS=/ebook : Compresses images to 150dpi
            // -dCompatibilityLevel=1.4 : Ensures max compatibility
            $cmd = sprintf(
                "gs -sDEVICE=pdfwrite -dPDFSETTINGS=/ebook -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s",
                escapeshellarg($outputFile),
                $inputFilesCmd
            );

            // 5. Execute
            $output = [];
            $returnVar = 0;
            exec($cmd, $output, $returnVar);

            // 6. Output to Browser
            if ($returnVar === 0 && file_exists($outputFile) && filesize($outputFile) > 0) {
                
                // Clean output buffer to ensure binary safety
                if (ob_get_length()) ob_clean();

                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="merged_downloaded.pdf"');
                header('Content-Length: ' . filesize($outputFile));
                header('Cache-Control: private, max-age=0, must-revalidate');
                
                readfile($outputFile);
            } else {
                log_message('error', 'Ghostscript Error: ' . implode(" ", $output));
                throw new Exception("Error creating PDF. The source files might be corrupted or password protected.");
            }

            // 7. Cleanup
            $this->cleanup($tempFiles);

        } catch (Exception $e) {
            $this->cleanup($tempFiles);
            show_error($e->getMessage());
        }
    }

    private function cleanup($files) {
        foreach ($files as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }
}
