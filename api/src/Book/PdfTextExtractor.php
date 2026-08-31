<?php

namespace App\Book;

use Symfony\Component\Process\Process;

final class PdfTextExtractor
{
    public function extract(string $pdfPath): array
    {
        $target = tempnam(sys_get_temp_dir(), 'ssk-book-');
        if (false === $target) {
            throw new \RuntimeException('Impossible de préparer le fichier temporaire d’extraction.');
        }

        try {
            $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $pdfPath, $target]);
            $process->setTimeout(180);
            $process->mustRun();
            $content = file_get_contents($target);
            if (false === $content || '' === trim($content)) {
                throw new \RuntimeException('Le PDF ne contient aucun texte exploitable.');
            }

            $pages = [];
            foreach (explode("\f", rtrim($content, "\f\n")) as $index => $rawText) {
                $rawText = str_replace("\r", '', $rawText);
                $normalized = preg_replace('/[ \t]+/u', ' ', $rawText) ?? $rawText;
                $normalized = preg_replace('/\n{3,}/u', "\n\n", $normalized) ?? $normalized;
                $normalized = trim($normalized);

                $pages[] = [
                    'pageNumber' => $index + 1,
                    'rawText' => trim($rawText),
                    'normalizedText' => $normalized,
                ];
            }

            return $pages;
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }
}
