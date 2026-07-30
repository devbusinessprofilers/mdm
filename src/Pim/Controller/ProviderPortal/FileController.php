<?php

namespace App\Pim\Controller\ProviderPortal;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AbstractController
{
    #[Route('/portal/upload-media', name: 'provider_portal_upload_media', methods: ['POST'])]
    public function uploadMedia(Request $request): Response
    {
        $fileToUpload = $request->files->get('file');

        if (!$fileToUpload instanceof UploadedFile) {
            throw new \LogicException('invalid file');
        }

        // @todo: process file upload!
        $uniqueId = uniqid('media_');

        return new JsonResponse(['id' => $uniqueId]);
    }

    #[Route('/portal/download-media/{uniqueId}', name: 'provider_portal_download_media', methods: ['GET'])]
    public function downloadMedia(Request $request, string $racine, string $uniqueId): Response
    {
        $type = $request->query->get('type', 'picture');

        $filePath = sprintf('%s/public/downloads/%s', $racine, $uniqueId);
        if (!file_exists($filePath)) {
            $filePath = match ($type) {
                'document' => $racine.'/assets/provider_portal/img/mock/document.pdf',
                default => $racine.'/assets/provider_portal/img/mock/picture.jpg',
            };
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $uniqueId);

        return $response;
    }
}
