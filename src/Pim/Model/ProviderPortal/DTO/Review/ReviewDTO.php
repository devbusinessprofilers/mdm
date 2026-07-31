<?php

namespace App\Pim\Model\ProviderPortal\DTO\Review;

class ReviewDTO
{
    public ?string $companyName = null;

    public ?string $eventName = null;

    public ?string $eventReference = null;

    public ?string $placeName = null;

    public ?\DateTimeInterface $date = null;

    public ?float $scorePercentage = null;

    public ?string $content = null;

    public ?string $logoUrl = null;

    public static function mock(int $index = 1): self
    {
        $data = new self();

        $data->companyName = \sprintf('Company %d', $index);
        $data->eventName = \sprintf('Event %d', $index);
        $data->eventReference = \sprintf('EVT-PLX-%d', $index);
        $data->date = new \DateTimeImmutable('2025-12-24');
        $data->scorePercentage = random_int(0, 100);
        $data->content = 'Lorem ipsum dolor sit amet consectetur. Aliquet sed ultricies vitae augue orci nunc eu pellentesque. Condimentum tellus habitasse nullam pellentesque. Vitae nibh auctor sit ornare amet. Nam turpis semper dictumst non vitae in. Ac in rutrum at turpis amet mattis. Sem proin senectus lectus sem mi elementum orci. Sem orci pulvinar urna ante amet sit mi ultrices. Nisl morbi arcu euismod consequat nam et posuere tortor. Dolor gravida amet facilisi consectetur eget. Bibendum sed nibh dolor morbi in leo id. Justo enim gravida nisl venenatis platea lectus. Faucibus netus feugiat non eget in nec. Sed volutpat urna nibh mattis cras donec tempus scelerisque. Malesuada congue ut mi urna. Amet a lacus tempor urna amet malesuada at at. In amet amet tempus nibh egestas blandit senectus massa pellentesque.';
        $data->placeName = \sprintf('Fiche %d', $index);

        if (rand(1, 5) > 1) {
            $data->logoUrl = 'provider_portal/img/mock/company-logo.png';
        }

        return $data;
    }
}
