<?php

namespace App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone;

enum AcceptedTypeEnum: string
{
    case IMAGES = 'images';
    case VIDEOS = 'videos';
    case DOCUMENTS = 'documents';
    case ALL = 'all';

    /**
     * @return array<string>
     */
    public function getExtensions(): array
    {
        return match ($this) {
            self::IMAGES => ['.jpeg', '.jpg', '.png'],
            self::VIDEOS => ['.webm', '.mpeg', '.mpg', '.mp4', '.mkv', '.mov', '.avi', '.wmv', '.ogv', '.m4v'],
            self::DOCUMENTS => ['.pdf'],
            self::ALL => '',
        };
    }

    /**
     * @return array<string>
     */
    public function getMimeTypes(): array
    {
        return match ($this) {
            self::IMAGES => ['image/jpeg', 'image/png'],
            self::VIDEOS => ['video/webm', 'video/mpeg', 'video/mp4', 'video/x-matroska', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv', 'video/ogg', 'video/x-m4v', 'video/avi', 'application/vnd.ms-asf'],
            self::DOCUMENTS => ['application/pdf'],
            self::ALL => [],
        };
    }
}
