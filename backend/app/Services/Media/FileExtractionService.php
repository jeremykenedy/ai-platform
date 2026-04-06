<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;

class FileExtractionService
{
    /**
     * Map of mime type prefix/exact to language label for code files.
     *
     * @var array<string, string>
     */
    private const CODE_MIME_LANGUAGES = [
        'text/x-php'                => 'php',
        'application/x-php'         => 'php',
        'text/javascript'           => 'javascript',
        'application/javascript'    => 'javascript',
        'application/x-javascript'  => 'javascript',
        'text/x-python'             => 'python',
        'application/x-python-code' => 'python',
        'text/x-ruby'               => 'ruby',
        'text/x-java-source'        => 'java',
        'text/x-csrc'               => 'c',
        'text/x-c++src'             => 'cpp',
        'text/x-csharp'             => 'csharp',
        'text/x-go'                 => 'go',
        'text/x-rust'               => 'rust',
        'text/x-swift'              => 'swift',
        'text/x-kotlin'             => 'kotlin',
        'text/x-typescript'         => 'typescript',
        'text/x-vue'                => 'vue',
        'text/x-sh'                 => 'bash',
        'application/x-sh'          => 'bash',
        'text/x-shellscript'        => 'bash',
        'text/x-yaml'               => 'yaml',
        'application/x-yaml'        => 'yaml',
        'text/x-toml'               => 'toml',
        'text/x-sql'                => 'sql',
        'application/sql'           => 'sql',
    ];

    /**
     * Extract text content from a file for AI processing.
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    public function extract(string $filePath, string $mimeType): array
    {
        return match (true) {
            $mimeType === 'application/pdf' => $this->extractPdf($filePath),

            $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $this->extractDocx($filePath),

            $mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => $this->extractSpreadsheet($filePath),

            $mimeType === 'text/csv' => $this->extractCsv($filePath),

            $mimeType === 'application/json' => $this->extractJson($filePath),

            $mimeType === 'application/xml' || $mimeType === 'text/xml' => $this->extractXml($filePath),

            isset(self::CODE_MIME_LANGUAGES[$mimeType]) => $this->extractCode($filePath, self::CODE_MIME_LANGUAGES[$mimeType]),

            str_starts_with($mimeType, 'text/') => $this->extractText($filePath),

            str_starts_with($mimeType, 'image/') => $this->extractImageMetadata($filePath, $mimeType),

            default => throw new \InvalidArgumentException(
                "Unsupported MIME type: {$mimeType}"
            ),
        };
    }

    /**
     * Extract text from a PDF file.
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    public function extractPdf(string $filePath): array
    {
        try {
            $raw = file_get_contents($filePath);

            if ($raw === false) {
                throw new \RuntimeException("Failed to read file: {$filePath}");
            }

            // Extract raw text streams from PDF binary.
            // In production, smalot/pdfparser would handle this properly.
            $text = '';
            $pageCount = null;

            // Count pages via /Type /Page entries.
            $pageCount = substr_count($raw, '/Type /Page') ?: null;

            // Extract text between BT (begin text) and ET (end text) markers.
            if (preg_match_all('/BT\s*(.*?)\s*ET/s', $raw, $matches)) {
                foreach ($matches[1] as $block) {
                    // Extract strings inside parentheses: (text)
                    if (preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', $block, $strings)) {
                        foreach ($strings[1] as $string) {
                            $decoded = stripcslashes($string);
                            $text .= $decoded.' ';
                        }
                    }

                    // Extract hex strings: <hex>
                    if (preg_match_all('/<([0-9A-Fa-f]+)>/', $block, $hexStrings)) {
                        foreach ($hexStrings[1] as $hex) {
                            if (strlen($hex) % 2 === 0) {
                                $decoded = hex2bin($hex);

                                if ($decoded !== false) {
                                    $cleaned = preg_replace('/[^\x20-\x7E\s]/', '', $decoded) ?? '';
                                    $text .= $cleaned.' ';
                                }
                            }
                        }
                    }
                }
            }

            $text = $this->normalizeWhitespace($text);

            return [
                'text'           => $text ?: '[PDF content could not be extracted — binary or encrypted]',
                'token_estimate' => $this->estimateTokens($text),
                'pages'          => $pageCount,
            ];
        } catch (\Throwable $e) {
            Log::error('[FileExtractionService] extractPdf failed', [
                'file'  => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'text'           => '[PDF extraction failed: '.$e->getMessage().']',
                'token_estimate' => 0,
                'pages'          => null,
            ];
        }
    }

    /**
     * Extract text from a DOCX file using ZipArchive.
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    public function extractDocx(string $filePath): array
    {
        try {
            $zip = new \ZipArchive();
            $result = $zip->open($filePath);

            if ($result !== true) {
                throw new \RuntimeException("Failed to open DOCX ZIP archive (error code: {$result})");
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xml === false) {
                throw new \RuntimeException('word/document.xml not found in DOCX archive');
            }

            // Insert spaces before closing tags so words don't merge.
            $xml = str_replace(['</w:t>', '</w:p>'], [' </w:t>', "\n</w:p>"], $xml);

            $text = strip_tags($xml);
            $text = $this->normalizeWhitespace($text);

            return [
                'text'           => $text,
                'token_estimate' => $this->estimateTokens($text),
                'pages'          => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[FileExtractionService] extractDocx failed', [
                'file'  => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'text'           => '[DOCX extraction failed: '.$e->getMessage().']',
                'token_estimate' => 0,
                'pages'          => null,
            ];
        }
    }

    /**
     * Extract text from an XLSX file (reads shared strings and sheet data).
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    public function extractSpreadsheet(string $filePath): array
    {
        try {
            $zip = new \ZipArchive();
            $result = $zip->open($filePath);

            if ($result !== true) {
                throw new \RuntimeException("Failed to open XLSX ZIP archive (error code: {$result})");
            }

            // Load shared strings table.
            $sharedStrings = [];
            $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

            if ($sharedStringsXml !== false) {
                if (preg_match_all('/<si>(.*?)<\/si>/s', $sharedStringsXml, $matches)) {
                    foreach ($matches[1] as $si) {
                        $sharedStrings[] = strip_tags(
                            str_replace(['</t>', '</r>'], [' </t>', ''], $si)
                        );
                    }
                }
            }

            // Find all sheet XML files.
            $lines = [];
            $sheetIndex = 1;

            while (true) {
                $sheetXml = $zip->getFromName("xl/worksheets/sheet{$sheetIndex}.xml");

                if ($sheetXml === false) {
                    break;
                }

                // Parse rows.
                if (preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches)) {
                    foreach ($rowMatches[1] as $row) {
                        $cells = [];

                        if (preg_match_all('/<c\s[^>]*t="s"[^>]*>.*?<v>(\d+)<\/v>.*?<\/c>/s', $row, $cellMatches)) {
                            foreach ($cellMatches[1] as $idx) {
                                $cells[] = trim($sharedStrings[(int) $idx] ?? '');
                            }
                        }

                        // Inline strings.
                        if (preg_match_all('/<c[^>]*>.*?<is>.*?<t>(.*?)<\/t>.*?<\/is>.*?<\/c>/s', $row, $inlineMatches)) {
                            foreach ($inlineMatches[1] as $val) {
                                $cells[] = trim($val);
                            }
                        }

                        // Numeric values.
                        if (preg_match_all('/<c(?![^>]*t=)[^>]*>.*?<v>(.*?)<\/v>.*?<\/c>/s', $row, $numMatches)) {
                            foreach ($numMatches[1] as $val) {
                                $cells[] = trim($val);
                            }
                        }

                        if ($cells !== []) {
                            $lines[] = implode("\t", $cells);
                        }
                    }
                }

                $sheetIndex++;
            }

            $zip->close();

            $text = implode("\n", $lines);
            $text = $this->normalizeWhitespace($text);

            return [
                'text'           => $text ?: '[Spreadsheet appears to be empty]',
                'token_estimate' => $this->estimateTokens($text),
                'pages'          => $sheetIndex > 1 ? $sheetIndex - 1 : null,
            ];
        } catch (\Throwable $e) {
            Log::error('[FileExtractionService] extractSpreadsheet failed', [
                'file'  => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'text'           => '[Spreadsheet extraction failed: '.$e->getMessage().']',
                'token_estimate' => 0,
                'pages'          => null,
            ];
        }
    }

    /**
     * Extract CSV content and format as a markdown table.
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    public function extractCsv(string $filePath): array
    {
        try {
            $handle = fopen($filePath, 'r');

            if ($handle === false) {
                throw new \RuntimeException("Failed to open CSV file: {$filePath}");
            }

            $rows = [];

            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }

            fclose($handle);

            if ($rows === []) {
                return [
                    'text'           => '[CSV file is empty]',
                    'token_estimate' => 0,
                    'pages'          => null,
                ];
            }

            // Build markdown table.
            $lines = [];
            $headers = array_shift($rows);

            if ($headers !== null) {
                $lines[] = '| '.implode(' | ', array_map('strval', $headers)).' |';
                $lines[] = '| '.implode(' | ', array_fill(0, count($headers), '---')).' |';
            }

            foreach ($rows as $row) {
                $lines[] = '| '.implode(' | ', array_map('strval', $row)).' |';
            }

            $text = implode("\n", $lines);

            return [
                'text'           => $text,
                'token_estimate' => $this->estimateTokens($text),
                'pages'          => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[FileExtractionService] extractCsv failed', [
                'file'  => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'text'           => '[CSV extraction failed: '.$e->getMessage().']',
                'token_estimate' => 0,
                'pages'          => null,
            ];
        }
    }

    /**
     * Extract plain text files directly.
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    public function extractText(string $filePath): array
    {
        try {
            $text = file_get_contents($filePath);

            if ($text === false) {
                throw new \RuntimeException("Failed to read file: {$filePath}");
            }

            $text = $this->normalizeWhitespace($text);

            return [
                'text'           => $text,
                'token_estimate' => $this->estimateTokens($text),
                'pages'          => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[FileExtractionService] extractText failed', [
                'file'  => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'text'           => '[Text extraction failed: '.$e->getMessage().']',
                'token_estimate' => 0,
                'pages'          => null,
            ];
        }
    }

    /**
     * Extract source code files, wrapping content in a fenced code block.
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    public function extractCode(string $filePath, string $language): array
    {
        try {
            $content = file_get_contents($filePath);

            if ($content === false) {
                throw new \RuntimeException("Failed to read file: {$filePath}");
            }

            $filename = basename($filePath);
            $text = "File: {$filename}\n\n```{$language}\n{$content}\n```";

            return [
                'text'           => $text,
                'token_estimate' => $this->estimateTokens($text),
                'pages'          => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[FileExtractionService] extractCode failed', [
                'file'  => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'text'           => '[Code extraction failed: '.$e->getMessage().']',
                'token_estimate' => 0,
                'pages'          => null,
            ];
        }
    }

    /**
     * Extract and pretty-print JSON content.
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    public function extractJson(string $filePath): array
    {
        try {
            $raw = file_get_contents($filePath);

            if ($raw === false) {
                throw new \RuntimeException("Failed to read file: {$filePath}");
            }

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($pretty === false) {
                throw new \RuntimeException('Failed to re-encode JSON');
            }

            $text = "```json\n{$pretty}\n```";

            return [
                'text'           => $text,
                'token_estimate' => $this->estimateTokens($text),
                'pages'          => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[FileExtractionService] extractJson failed', [
                'file'  => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'text'           => '[JSON extraction failed: '.$e->getMessage().']',
                'token_estimate' => 0,
                'pages'          => null,
            ];
        }
    }

    /**
     * Extract and format XML content.
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    private function extractXml(string $filePath): array
    {
        try {
            $raw = file_get_contents($filePath);

            if ($raw === false) {
                throw new \RuntimeException("Failed to read file: {$filePath}");
            }

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if (!@$dom->loadXML($raw)) {
                // Fall back to stripping tags if parsing fails.
                $text = strip_tags($raw);
                $text = $this->normalizeWhitespace($text);
            } else {
                $formatted = $dom->saveXML();
                $text = "```xml\n".($formatted !== false ? $formatted : $raw)."\n```";
            }

            return [
                'text'           => $text,
                'token_estimate' => $this->estimateTokens($text),
                'pages'          => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[FileExtractionService] extractXml failed', [
                'file'  => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'text'           => '[XML extraction failed: '.$e->getMessage().']',
                'token_estimate' => 0,
                'pages'          => null,
            ];
        }
    }

    /**
     * Return metadata only for image files (images go direct to vision models as base64).
     *
     * @return array{text: string, token_estimate: int, pages: int|null}
     */
    private function extractImageMetadata(string $filePath, string $mimeType): array
    {
        $filename = basename($filePath);
        $size = file_exists($filePath) ? filesize($filePath) : 0;
        $sizeKb = $size !== false ? round($size / 1024, 1) : 0;

        $text = "[Image file: {$filename}, type: {$mimeType}, size: {$sizeKb} KB — send as base64 to vision model]";

        return [
            'text'           => $text,
            'token_estimate' => $this->estimateTokens($text),
            'pages'          => null,
        ];
    }

    /**
     * Estimate token count using a word-count heuristic.
     */
    public function estimateTokens(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $wordCount = str_word_count($text);

        return (int) round($wordCount * 1.3);
    }

    /**
     * Return all supported MIME types.
     *
     * @return string[]
     */
    public function getSupportedMimeTypes(): array
    {
        return [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'text/markdown',
            'text/html',
            'text/xml',
            'application/xml',
            'application/json',
            'text/javascript',
            'application/javascript',
            'application/x-javascript',
            'text/x-php',
            'application/x-php',
            'text/x-python',
            'application/x-python-code',
            'text/x-ruby',
            'text/x-java-source',
            'text/x-csrc',
            'text/x-c++src',
            'text/x-csharp',
            'text/x-go',
            'text/x-rust',
            'text/x-swift',
            'text/x-kotlin',
            'text/x-typescript',
            'text/x-vue',
            'text/x-sh',
            'application/x-sh',
            'text/x-shellscript',
            'text/x-yaml',
            'application/x-yaml',
            'text/x-toml',
            'text/x-sql',
            'application/sql',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ];
    }

    /**
     * Check whether a given MIME type is supported for extraction.
     */
    public function isSupported(string $mimeType): bool
    {
        return in_array($mimeType, $this->getSupportedMimeTypes(), true)
            || str_starts_with($mimeType, 'text/')
            || str_starts_with($mimeType, 'image/')
            || isset(self::CODE_MIME_LANGUAGES[$mimeType]);
    }

    /**
     * Collapse runs of whitespace into single spaces and trim.
     */
    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/(\s*\n\s*){3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
