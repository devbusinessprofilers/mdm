<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Service;

use App\Pim\Model\ProviderPortal\Form\Dropzone\Document;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ServiceInformationDTO
{
    public ?string $name = null;

    public ?string $description = null;

    /**
     * @var array<UploadedFile>
     */
    public array $pictureFiles = [];

    /**
     * @var array<Document>
     */
    public array $pictureDocuments = [];

    /**
     * @var array<UploadedFile>
     */
    public array $videoFiles = [];

    /**
     * @var array<Document>
     */
    public array $videoDocuments = [];

    public ?bool $isEsat = null;

    public ?bool $isRse = null;

    public ?bool $pregnantSupport = null;

    public ?bool $deafSupport = null;

    public ?bool $blindSupport = null;

    public ?bool $withEquipment = null;

    public ?bool $userEquipmentRequired = null;

    public ?bool $receptionEquipmentRequired = null;

    public ?bool $withConstraint = null;

    public int $minCapacity = 0;

    public int $maxCapacity = 0;

    public int $duration = 0;

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Mauris rutrum';
        $data->description = 'Donec id quam sed risus gravida faucibus non eu velit.';
        $data->pictureDocuments = [Document::fromPath('/provider_portal/img/mock/picture.jpg')];
        $data->videoDocuments = [Document::fromPath('/provider_portal/img/mock/video_presentation.mkv')];
        $data->isEsat = true;
        $data->pregnantSupport = false;
        $data->deafSupport = true;
        $data->withEquipment = true;
        $data->userEquipmentRequired = false;
        $data->receptionEquipmentRequired = false;
        $data->minCapacity = 5;
        $data->maxCapacity = 50;

        return $data;
    }
}
