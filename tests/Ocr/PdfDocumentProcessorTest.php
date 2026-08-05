<?php

declare(strict_types=1);

namespace App\Tests\Ocr;

use App\Ocr\Service\PdfDocumentProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdfDocumentProcessorTest extends TestCase
{
    /** @return iterable<string, array{int, list<array{int, int}>}> */
    public static function pageCases(): iterable
    {
        yield 'one page' => [1, [[1, 1]]];
        yield 'five pages' => [5, [[1, 5]]];
        yield 'six pages' => [6, [[1, 5], [6, 6]]];
        yield 'one hundred pages' => [100, array_map(static fn (int $first): array => [$first, min(100, $first + 4)], range(1, 100, 5))];
    }

    /** @param list<array{int, int}> $expectedRanges */
    #[DataProvider('pageCases')]
    public function testInspectsAndSplitsPdfInFivePageBatches(int $pages, array $expectedRanges): void
    {
        $path = $this->createPdf($pages);
        $processor = new PdfDocumentProcessor();
        $batches = [];
        try {
            self::assertSame($pages, $processor->inspect($path));
            $batches = $processor->batches($path, $pages);
            self::assertSame($expectedRanges, array_map(static fn ($batch): array => [$batch->firstPage, $batch->lastPage], $batches));
            foreach ($batches as $batch) {
                self::assertSame($batch->lastPage - $batch->firstPage + 1, $processor->inspect($batch->path));
            }
        } finally {
            $processor->cleanup($batches);
            @unlink($path);
        }
    }

    public function testRejectsPdfOverOneHundredPages(): void
    {
        $path = $this->createPdf(101);
        try {
            $this->expectException(\DomainException::class);
            $this->expectExceptionMessage('entre 1 et 100 pages');
            (new PdfDocumentProcessor())->inspect($path);
        } finally {
            @unlink($path);
        }
    }

    public function testRejectsFakePdf(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr-fake-pdf-');
        self::assertIsString($path);
        file_put_contents($path, '%PDF-not-a-real-document');
        try {
            $this->expectException(\DomainException::class);
            (new PdfDocumentProcessor())->inspect($path);
        } finally {
            @unlink($path);
        }
    }

    private function createPdf(int $pages): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $kids = [];
        for ($page = 0; $page < $pages; ++$page) {
            $pageObject = 3 + 2 * $page;
            $contentObject = $pageObject + 1;
            $kids[] = $pageObject.' 0 R';
            $objects[$pageObject] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << >> /Contents %d 0 R >>', $contentObject);
            $objects[$contentObject] = "<< /Length 0 >>\nstream\n\nendstream";
        }
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), $pages);
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_keys($objects) as $number) { $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]); }
        $pdf .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", count($objects) + 1, $xref);

        $path = tempnam(sys_get_temp_dir(), 'ocr-pdf-');
        self::assertIsString($path);
        file_put_contents($path, $pdf);
        return $path;
    }
}
