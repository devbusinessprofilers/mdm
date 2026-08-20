<?php

declare(strict_types=1);

namespace App\Vision\Service;

use App\Pim\Message\CalculateFicheCompleteness;
use App\Pim\Message\IndexFiche;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Entity\ImageRecognitionSuggestion;
use App\Vision\Enum\SuggestionStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applique une revue de suggestions de reconnaissance sur la ressource :
 * la légende remplace, les mots-clés et les informations de contexte
 * acceptées fusionnent avec les mots-clés existants.
 */
final readonly class ImageRecognitionApplier
{
    public function __construct(
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<string, array{value: ?string, accept: bool}> $decisions décisions par id de suggestion */
    public function apply(ImageRecognition $recognition, array $decisions, string $actor): void
    {
        $resource = $recognition->resource();
        $acceptedKeywords = [];
        $accepted = 0;
        foreach ($recognition->suggestions() as $suggestion) {
            if (!$suggestion->isPending() || !array_key_exists($suggestion->id(), $decisions)) {
                continue;
            }
            $input = $decisions[$suggestion->id()];
            $text = trim((string) $input['value']);
            $value = ImageRecognitionSuggestion::PATH_KEYWORDS === $suggestion->fieldPath() ? self::keywordList($text) : $text;
            $suggestion->correct($value);
            $suggestion->decide($input['accept'] ? SuggestionStatus::Accepted : SuggestionStatus::Rejected, $actor);
            if (!$input['accept']) {
                continue;
            }
            ++$accepted;
            if (ImageRecognitionSuggestion::PATH_LEGENDE === $suggestion->fieldPath()) {
                $resource->changeLegende(mb_substr($text, 0, 255));
            } elseif (is_array($value)) {
                $acceptedKeywords = [...$acceptedKeywords, ...$value];
            } elseif ('' !== $value) {
                // Information de contexte (type de vue, intérieur/extérieur) :
                // pas de champ PIM dédié, elle enrichit les mots-clés.
                $acceptedKeywords[] = $value;
            }
        }
        if ([] !== $acceptedKeywords) {
            $merged = array_values(array_unique([...self::keywordList((string) $resource->keywords()), ...$acceptedKeywords]));
            $resource->changeKeywords(implode(', ', $merged));
        }
        $recognition->refreshReviewStatus();
        if (0 < $accepted) {
            $fiche = $recognition->fiche();
            $fiche->markChanged();
            $this->outbox->enqueue(new IndexFiche($fiche->idString()));
            $this->outbox->enqueue(new CalculateFicheCompleteness($fiche->idString()));
        }
        $this->entityManager->flush();
    }

    /** @return list<string> */
    private static function keywordList(string $value): array
    {
        $parts = preg_split('/[,;\n]+/', $value) ?: [];

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $keyword): bool => '' !== $keyword));
    }
}
