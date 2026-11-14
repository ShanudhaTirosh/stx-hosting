<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Converts PDF payment slips into high-resolution PNG raster images
 * for perceptual hashing, mobile/browser display, and AI vision verification.
 */
class PdfRasterizerService
{
    /**
     * Convert the first page of a PDF file to PNG binary bytes.
     *
     * @param string $pdfPath Absolute path to PDF file
     * @param int $dpi Resolution for rasterization (default: 200 DPI)
     * @return string|null PNG binary bytes or null if rasterization failed
     */
    public function rasterize(string $pdfPath, int $dpi = 200): ?string
    {
        if (! file_exists($pdfPath) || ! is_readable($pdfPath)) {
            return null;
        }

        // Method 1: PHP Imagick extension if installed
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->setResolution($dpi, $dpi);
                $imagick->readImage($pdfPath.'[0]'); // First page
                $imagick->setImageFormat('png');
                $imagick->setImageBackgroundColor('white');
                $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $pngBytes = $imagick->getImageBlob();
                $imagick->clear();
                $imagick->destroy();

                if (! empty($pngBytes)) {
                    return $pngBytes;
                }
            } catch (\Throwable $e) {
                Log::warning("Imagick PDF rasterization failed: {$e->getMessage()}");
            }
        }

        // Method 2: Python with PyMuPDF (pymupdf / fitz)
        $pythonScript = <<<'PYTHON'
import sys
try:
    import fitz  # PyMuPDF
    doc = fitz.open(sys.argv[1])
    if len(doc) > 0:
        pix = doc[0].get_pixmap(dpi=int(sys.argv[2]))
        sys.stdout.buffer.write(pix.tobytes("png"))
        sys.exit(0)
    sys.exit(1)
except Exception as e:
    sys.stderr.write(str(e))
    sys.exit(1)
PYTHON;

        $pythonBin = 'C:\\Users\\tiros\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';
        if (! file_exists($pythonBin)) {
            $pythonBin = 'python';
        }

        try {
            $result = Process::input($pythonScript)->run([
                $pythonBin,
                '-c',
                'import sys; exec(sys.stdin.read())',
                $pdfPath,
                (string) $dpi,
            ]);

            if ($result->successful() && ! empty($result->output())) {
                return $result->output();
            }
        } catch (\Throwable $e) {
            Log::warning("Python PyMuPDF rasterization failed: {$e->getMessage()}");
        }

        // Method 3: pdftoppm CLI tool if installed
        try {
            $tempPrefix = tempnam(sys_get_temp_dir(), 'pdf_rast_');
            $command = ['pdftoppm', '-png', '-f', '1', '-l', '1', '-r', (string) $dpi, $pdfPath, $tempPrefix];
            $result = Process::run($command);
            $generatedPng = $tempPrefix.'-1.png';

            if (file_exists($generatedPng)) {
                $pngBytes = file_get_contents($generatedPng);
                @unlink($generatedPng);
                @unlink($tempPrefix);

                if (! empty($pngBytes)) {
                    return $pngBytes;
                }
            }
            @unlink($tempPrefix);
        } catch (\Throwable $e) {
            // Not installed or error
        }

        return null;
    }
}

