public function html_to_pdf_tcpdf() {
    require_once(APPPATH.'third_party/PDFMerger/tcpdf/tcpdf.php');
    $pdf = new TCPDF();
    $pdf->AddPage();

    // 1. Define Image Path
    $imagePath = FCPATH . 'assets/images/logo.png'; // Local path is safest

    // 2. Create HTML with the image
    // TCPDF recognizes the img tag and reads the local file
    $html = '
        <h1>Report</h1>
        <p>Here is the image:</p>
        <img src="'.$imagePath.'" border="0" height="50" width="50" />
    ';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('image_test.pdf', 'I');
}
