<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payslip;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;
use thiagoalessio\TesseractOCR\TesseractOCR;

class DebugOcrText extends Command
{
    protected $signature = 'debug:ocr';
    protected $description = 'Debug OCR text extraction from the latest payslip';

    public function handle()
    {
        $payslip = Payslip::latest()->first();
        
        if (!$payslip) {
            $this->error('No payslips found');
            return 1;
        }

        $this->info("Payslip ID: {$payslip->id}");
        $this->info("File: {$payslip->file_path}");
        
        $path = Storage::path($payslip->file_path);
        $mime = Storage::mimeType($payslip->file_path);
        
        $this->info("MIME type: {$mime}");
        
        try {
            if ($mime === 'application/pdf') {
                $text = (new Pdf(env('PDFTOTEXT_PATH')))->setPdf($path)->text();
                $this->info("Using PDF extraction");
            } else {
                $text = (new TesseractOCR($path))
                    ->lang('eng+msa')
                    ->configFile('bazaar')
                    ->run();
                $this->info("Using Tesseract OCR");
            }

            $this->info("Text length: " . strlen($text));
            $this->info("=================== OCR TEXT ===================");
            $this->line($text);
            $this->info("================= END OCR TEXT =================");
            
            // Test our patterns on the actual text
            $this->info("");
            $this->info("Testing percentage extraction patterns:");
            
            // Test inline patterns first
            $inlinePatterns = [
                '/%\s*Peratus\s+Gaji\s+Bersih\s*:\s*([\d,]+\.?\d*)/i',
                '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
                '/peratus\s+gaji\s+bersih.*?:\s*([\d,]+\.?\d*)/i',
                '/peratus\s+gaji\s+bersih.*?([\d,]+\.?\d*)/i',
            ];

            $found = false;
            foreach ($inlinePatterns as $index => $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $value = (float) str_replace(',', '', $matches[1]);
                    if ($value >= 10 && $value <= 100) {
                        $this->info("✓ Inline Pattern " . ($index + 1) . " MATCHED: " . $value);
                        $this->info("  Full match: " . $matches[0]);
                        $found = true;
                    }
                }
            }
            
            // Test multiline pattern if inline failed
            if (!$found) {
                $this->info("Inline patterns failed, testing multiline extraction...");
                
                if (preg_match('/%\s*Peratus\s+Gaji\s+Bersih/i', $text)) {
                    $this->info("Found '% Peratus Gaji Bersih' label, analyzing structure...");
                    
                    $lines = preg_split('/\r\n|\r|\n/', $text);
                    $foundPeratusLine = false;
                    $colonLineIndex = -1;
                    
                    // Find the line with "% Peratus Gaji Bersih"
                    foreach ($lines as $index => $line) {
                        if (preg_match('/%\s*Peratus\s+Gaji\s+Bersih/i', $line)) {
                            $foundPeratusLine = true;
                            $this->info("Found percentage label at line " . ($index + 1) . ": " . trim($line));
                            
                                                         // Look for the pattern where we have colons (could be on separate lines or together)
                             for ($i = $index + 1; $i < count($lines) && $i < $index + 8; $i++) {
                                 $this->info("Checking line " . ($i + 1) . ": '" . trim($lines[$i]) . "'");
                                 // Check if this line contains colons (either together or separate)
                                 if (preg_match('/^\s*:\s*:\s*:\s*$/', $lines[$i]) || 
                                     preg_match('/^\s*:\s*$/', $lines[$i])) {
                                     $colonLineIndex = $i;
                                     $this->info("Found colon line at index " . ($i + 1));
                                     // If it's a single colon, we need to find where the numbers start
                                     // Skip any additional colon lines
                                     while ($colonLineIndex < count($lines) - 1 && 
                                            (preg_match('/^\s*:\s*$/', $lines[$colonLineIndex + 1]) || 
                                             trim($lines[$colonLineIndex + 1]) === '')) {
                                         $colonLineIndex++;
                                         $this->info("Skipping to line " . ($colonLineIndex + 1) . ": '" . trim($lines[$colonLineIndex]) . "'");
                                     }
                                     break;
                                 }
                             }
                            break;
                        }
                    }
                    
                    // If we found the structure, look for numbers after the colon line
                    if ($foundPeratusLine && $colonLineIndex > -1) {
                        $this->info("Looking for numbers after colon line...");
                        $numberLines = [];
                        for ($j = $colonLineIndex + 1; $j < count($lines) && $j < $colonLineIndex + 10; $j++) {
                            if (isset($lines[$j]) && preg_match('/^\s*([\d,]+\.?\d*)\s*$/', trim($lines[$j]), $matches)) {
                                $numberLines[] = (float) str_replace(',', '', $matches[1]);
                                $this->info("Found number at line " . ($j + 1) . ": " . $numberLines[count($numberLines) - 1]);
                            }
                        }
                        
                        $this->info("Found " . count($numberLines) . " numbers total");
                        
                        // The percentage should be the third number (index 2)
                        if (count($numberLines) >= 3) {
                            $percentageValue = $numberLines[2];
                            if ($percentageValue >= 10 && $percentageValue <= 100) {
                                $this->info("✓ MULTILINE PATTERN MATCHED: " . $percentageValue);
                                $found = true;
                            } else {
                                $this->warn("Third number out of range: " . $percentageValue);
                            }
                        } else {
                            $this->warn("Not enough numbers found (need at least 3)");
                        }
                    }
                } else {
                    $this->error("Could not find '% Peratus Gaji Bersih' label");
                }
            }
            
            // Test fallback pattern for parentheses
            if (!$found) {
                $this->info("Testing fallback pattern for parentheses...");
                if (preg_match('/\(\s*([\d,]+\.?\d*)\s*\)/', $text, $matches)) {
                    $value = (float) str_replace(',', '', $matches[1]);
                    if ($value >= 10 && $value <= 100) {
                        $this->info("✓ Parentheses pattern MATCHED: " . $value);
                        $this->info("  Full match: " . $matches[0]);
                        $found = true;
                    }
                }
            }
            
            if (!$found) {
                $this->error("No patterns matched the percentage extraction");
            }

        } catch (\Exception $e) {
            $this->error("Error extracting text: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 