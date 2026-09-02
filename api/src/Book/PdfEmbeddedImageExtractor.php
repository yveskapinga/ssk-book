<?php

namespace App\Book;

use Symfony\Component\Process\Process;

final class PdfEmbeddedImageExtractor
{
    private const MIN_WIDTH_PX = 80;

    public function extract(string $pdfPath, string $directory): array
    {
        if (!is_file($pdfPath)) {
            throw new \RuntimeException('Le PDF source est introuvable pour extraire les photos.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le répertoire des photos.');
        }
        foreach (glob($directory.'/fig-*') ?: [] as $stale) {
            unlink($stale);
        }

        $listed = $this->listImages($pdfPath);
        if ([] === $listed) {
            return [];
        }

        $extract = new Process(['pdfimages', '-j', '-p', $pdfPath, $directory.'/fig']);
        $extract->setTimeout(180);
        $extract->mustRun();

        $layout = $this->imageTops($pdfPath);
        $keptByPage = [];
        foreach ($listed as $row) {
            if ($row['width'] < self::MIN_WIDTH_PX) {
                continue;
            }
            $path = sprintf('%s/fig-%03d-%03d.jpg', $directory, $row['page'], $row['num']);
            if (!is_file($path)) {
                $matches = glob(sprintf('%s/fig-%03d-%03d.*', $directory, $row['page'], $row['num'])) ?: [];
                $path = $matches[0] ?? '';
            }
            if ('' === $path || !is_file($path)) {
                continue;
            }
            $info = getimagesize($path);
            if (false === $info) {
                continue;
            }
            $keptByPage[$row['page']][] = [
                'pageNumber' => $row['page'],
                'path' => $path,
                'mime' => $info['mime'] ?? 'image/jpeg',
                'width' => (int) $info[0],
                'height' => (int) $info[1],
                'byteSize' => (int) filesize($path),
            ];
        }

        $figures = [];
        foreach ($keptByPage as $pageNumber => $images) {
            $tops = $layout[$pageNumber] ?? [];
            foreach (array_values($images) as $index => $image) {
                $ratio = isset($tops[$index]) ? (float) $tops[$index] : 0.4;
                $figures[] = $image + [
                    'sortIndex' => $index,
                    'yRatio' => max(0.0, min(1.0, $ratio)),
                ];
            }
        }

        foreach (glob($directory.'/fig-*') ?: [] as $file) {
            $used = false;
            foreach ($figures as $figure) {
                if ($figure['path'] === $file) {
                    $used = true;
                    break;
                }
            }
            if (!$used) {
                unlink($file);
            }
        }

        return $figures;
    }

    private function listImages(string $pdfPath): array
    {
        $process = new Process(['pdfimages', '-list', $pdfPath]);
        $process->setTimeout(120);
        $process->mustRun();
        $rows = [];
        foreach (preg_split('/\R/', $process->getOutput()) ?: [] as $line) {
            if (!preg_match('/^\s*(\d+)\s+(\d+)\s+\S+\s+(\d+)\s+(\d+)\s+/', $line, $match)) {
                continue;
            }
            $rows[] = [
                'page' => (int) $match[1],
                'num' => (int) $match[2],
                'width' => (int) $match[3],
                'height' => (int) $match[4],
            ];
        }

        return $rows;
    }

    /** @return array<int, list<float>> */
    private function imageTops(string $pdfPath): array
    {
        $temp = sys_get_temp_dir().'/ssk-book-layout-'.bin2hex(random_bytes(6));
        if (!mkdir($temp, 0700) && !is_dir($temp)) {
            return [];
        }
        $xmlPath = $temp.'/layout.xml';
        try {
            $process = new Process(['pdftohtml', '-xml', '-q', $pdfPath, $xmlPath]);
            $process->setTimeout(180);
            $process->run();
            if (!$process->isSuccessful() || !is_file($xmlPath)) {
                return [];
            }
            $xml = simplexml_load_file($xmlPath);
            if (false === $xml) {
                return [];
            }
            $layout = [];
            foreach ($xml->page as $page) {
                $number = (int) $page['number'];
                $height = max(1, (int) $page['height']);
                $layout[$number] = [];
                foreach ($page->image as $image) {
                    $layout[$number][] = max(0.0, min(1.0, (float) $image['top'] / $height));
                }
            }

            return $layout;
        } catch (\Throwable) {
            return [];
        } finally {
            foreach (glob($temp.'/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($temp);
        }
    }
}
