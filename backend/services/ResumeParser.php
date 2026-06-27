<?php
namespace Respawn\Services;

class ResumeParser {
    
    /**
     * Extracts text from a given resume file.
     * Currently supports PDF files via smalot/pdfparser.
     * 
     * @param string $filePath Absolute path to the file
     * @param string $mimeType MIME type of the file
     * @return string Extracted text or empty string on failure
     */
    public static function parseText(string $filePath, string $mimeType): string {
        if (!file_exists($filePath)) {
            return '';
        }

        try {
            if ($mimeType === 'application/pdf') {
                if (class_exists('\Smalot\PdfParser\Parser')) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    return $pdf->getText();
                } else {
                    error_log("ResumeParser: smalot/pdfparser is not installed.");
                    return '';
                }
            }
            
            // DOC/DOCX parsing could be added here in the future
            return '';
        } catch (\Exception $e) {
            error_log("ResumeParser failed to parse file $filePath: " . $e->getMessage());
            return '';
        }
    }
}
