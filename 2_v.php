<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compress & Merge PDFs</title>
</head>
<body>
    <h2>Upload 2 PDFs to Compress & Merge</h2>
    <form action="<?php echo site_url('PdfConverter/process'); ?>" method="post" enctype="multipart/form-data">
        
        <label>PDF 1:</label>
        <input type="file" name="pdf_files[]" required accept="application/pdf"><br><br>

        <label>PDF 2:</label>
        <input type="file" name="pdf_files[]" required accept="application/pdf"><br><br>

        <label>Compression Level:</label>
        <select name="compression">
            <option value="/screen">High Compression (Low Quality - 72dpi)</option>
            <option value="/ebook" selected>Medium Compression (Good Quality - 150dpi)</option>
            <option value="/printer">Low Compression (Print Quality - 300dpi)</option>
        </select><br><br>

        <button type="submit">Merge & Compress</button>
    </form>
</body>
</html>
