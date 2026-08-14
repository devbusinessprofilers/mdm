<?php

namespace App\Pim\Model\ProviderPortal\Form\OptionalPrice;

class InfosLine
{
    /** @var PictoInfo[] */
    public array $pictoInfos;

    public function addPictoInfo(PictoInfo $pictoInfo): static
    {
        $this->pictoInfos[] = $pictoInfo;

        return $this;
    }
}
