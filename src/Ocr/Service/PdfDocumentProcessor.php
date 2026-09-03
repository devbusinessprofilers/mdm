<?php

declare(strict_types=1);

namespace App\Ocr\Service;

use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Process\Process;

final class PdfDocumentProcessor
{
    public const BATCH_SIZE = 5;

    public function __construct(private readonly ParametreProviderInterface $parametres)
    {
    }

    public function inspect(string $path): int
    {
        if (!is_file($path)) {
            throw new \DomainException('Le PDF est introuvable.');
        }
        $maxMo = $this->parametres->int('ocr.pdf_poids_max_mo');
        $size = filesize($path);
        if (false === $size || $size > $maxMo * 1024 * 1024) {
            throw new \DomainException(sprintf('Le PDF ne peut pas dépasser %d Mo.', $maxMo));
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ('application/pdf' !== $mime || '%PDF-' !== file_get_contents($path, false, null, 0, 5)) {
            throw new \DomainException('Un PDF réel est requis.');
        }
        $process = new Process(['pdfinfo', $path]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \DomainException('Le PDF est illisible ou protégé.');
        }
        if (1 === preg_match('/^Encrypted:\s+yes/im', $process->getOutput())) {
            throw new \DomainException('Les PDF chiffrés ne sont pas acceptés.');
        }
        if (1 !== preg_match('/^Pages:\s+(\d+)/im', $process->getOutput(), $matches)) {
            throw new \DomainException('Le nombre de pages du PDF est introuvable.');
        }
        $pages = (int) $matches[1];
        $maxPages = $this->parametres->int('ocr.pdf_pages_max');
        if ($pages < 1 || $pages > $maxPages) {
            throw new \DomainException(sprintf('Le PDF doit contenir entre 1 et %d pages.', $maxPages));
        }

        return $pages;
    }

    /** @return list<PdfBatch> */
    public function batches(string $source, int $pages): array
    {
        if ($pages < 1 || $pages > $this->parametres->int('ocr.pdf_pages_max')) {
            throw new \InvalidArgumentException('Nombre de pages invalide.');
        }
        $directory = sys_get_temp_dir().'/mdm-ocr-'.bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le répertoire OCR temporaire.');
        }
        $batches = [];
        try {
            for ($first = 1; $first <= $pages; $first += self::BATCH_SIZE) {
                $last = min($pages, $first + self::BATCH_SIZE - 1);
                $output = $directory.'/batch-'.$first.'-'.$last.'.pdf';
                if (1 === $first && $last === $pages) {
                    if (!copy($source, $output)) {
                        throw new \RuntimeException('Impossible de préparer le PDF temporaire.');
                    }
                } else {
                    $pattern = $directory.'/page-%03d.pdf';
                    $split = new Process(['pdfseparate', '-f', (string) $first, '-l', (string) $last, $source, $pattern]);
                    $split->mustRun();
                    $parts = [];
                    for ($page = $first; $page <= $last; ++$page) {
                        $parts[] = sprintf($pattern, $page);
                    }
                    (new Process(['pdfunite', ...$parts, $output]))->mustRun();
                    foreach ($parts as $part) {
                        @unlink($part);
                    }
                }
                $batches[] = new PdfBatch($output, $first, $last);
            }
        } catch (\Throwable $error) {
            $this->cleanup($batches, $directory);
            throw $error;
        }

        return $batches;
    }

    /** @param list<PdfBatch> $batches */
    public function cleanup(array $batches, ?string $directory = null): void
    {
        foreach ($batches as $batch) {
            if (is_file($batch->path)) {
                @unlink($batch->path);
            }
        }
        $directory ??= [] === $batches ? null : dirname($batches[0]->path);
        if (null !== $directory && is_dir($directory)) {
            // Un échec de pdfseparate/pdfunite peut laisser des page-XXX.pdf
            // intermédiaires : les purger, sinon rmdir échoue et le répertoire
            // s'accumule dans /tmp à chaque retry.
            foreach (glob($directory.'/*') ?: [] as $leftover) {
                if (is_file($leftover)) {
                    @unlink($leftover);
                }
            }
            @rmdir($directory);
        }
    }
}
