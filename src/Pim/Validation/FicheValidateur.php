<?php

declare(strict_types=1);

namespace App\Pim\Validation;

use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Service\PhotoObligations;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Socle des validateurs de fiche (Lieu, Restaurant, Activité, Service) : les
 * contrôles que chaque gamme réécrivait — longueurs, URL, lien vidéo, plafond
 * et minimum de photos, fichiers DAM traités — et le groupe de soumission.
 * Les règles métier propres à chaque gamme restent dans son validateur.
 */
abstract class FicheValidateur extends ConstraintValidator
{
    public function __construct(
        protected readonly MediaAssetRepository $assets,
        protected readonly PhotoObligations $photoObligations,
    ) {
    }

    /** Vrai quand les contraintes de soumission s'ajoutent à celles du brouillon. */
    protected function enSoumission(): bool
    {
        return ValidationGroups::SUBMISSION === $this->context->getGroup();
    }

    protected function violation(string $message, string $path): void
    {
        $this->context->buildViolation($message)->atPath($path)->addViolation();
    }

    protected function longueurMax(?string $value, int $max, string $path, ?string $message = null): void
    {
        if (null !== $value && mb_strlen($value) > $max) {
            $this->violation($message ?? sprintf('La valeur ne peut pas dépasser %d caractères.', $max), $path);
        }
    }

    protected function url(?string $value, string $path, string $message = 'Cette valeur doit être une URL valide.'): void
    {
        if (null !== $value && false === filter_var($value, FILTER_VALIDATE_URL)) {
            $this->violation($message, $path);
        }
    }

    /** Lien vidéo : URL valide chez un hébergeur reconnu (LienVideoValidator). */
    protected function lienVideo(?string $value, string $path): void
    {
        if (null === $value) {
            return;
        }
        if (false === filter_var($value, FILTER_VALIDATE_URL)) {
            $this->violation('Le lien vidéo doit être une URL valide.', $path);
        } elseif (!LienVideoValidator::estHebergeurAutorise($value)) {
            $this->violation('Le lien vidéo doit pointer vers un hébergeur vidéo reconnu.', $path);
        }
    }

    /**
     * @param iterable<RessourceLieu> $ressources
     *
     * @return list<RessourceLieu>
     */
    protected function photos(iterable $ressources): array
    {
        $photos = [];
        foreach ($ressources as $resource) {
            if (NatureRessource::Photo === $resource->nature()) {
                $photos[] = $resource;
            }
        }

        return $photos;
    }

    /**
     * Plafond de photos de la gamme, en brouillon comme en soumission.
     *
     * @param list<RessourceLieu> $photos
     * @param string              $message avec un `%d` pour le plafond
     */
    protected function plafondPhotos(TypeFiche $type, array $photos, string $message): void
    {
        $maximum = $this->photoObligations->maximum($type);
        if (count($photos) > $maximum) {
            $this->violation(sprintf($message, $maximum), 'ressources');
        }
    }

    /**
     * À la soumission : la principale étant la première photo de l'ordre, au
     * moins une photo est toujours exigée même si le minimum est surchargé à 0.
     *
     * @param list<RessourceLieu> $photos
     */
    protected function photosSoumission(TypeFiche $type, array $photos): void
    {
        $minimum = max(1, $this->photoObligations->minimum($type));
        $maximum = $this->photoObligations->maximum($type);
        if (count($photos) < $minimum || count($photos) > $maximum) {
            $this->violation(sprintf('La soumission exige entre %d et %d photos.', $minimum, $maximum), 'ressources');
        }
    }

    /**
     * À la soumission : chaque ressource pointe vers un fichier DAM traité.
     *
     * @param iterable<RessourceLieu> $ressources
     */
    protected function ressourcesTraitees(iterable $ressources): void
    {
        foreach ($ressources as $resource) {
            $asset = '' === $resource->damAssetId() ? null : $this->assets->find($resource->damAssetId());
            if (null === $asset || MediaStatus::Processed !== $asset->status()) {
                $this->violation('Chaque ressource doit posséder un fichier DAM valide et traité.', 'ressources');

                return;
            }
        }
    }
}
