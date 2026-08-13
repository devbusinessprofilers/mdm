<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\LocalisationRepository;
use App\Pim\Service\ReferentielGeographiqueFrancais;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: LocalisationRepository::class)]
#[ORM\Table(name: 'pim_localisation')]
#[ORM\Index(name: 'IDX_PIM_LOCALISATION_FINGERPRINT', columns: ['address_fingerprint'])]
#[ORM\Index(name: 'IDX_PIM_LOCALISATION_POSTAL', columns: ['country_code', 'code_postal', 'id'])]
#[ORM\Index(name: 'IDX_PIM_LOCALISATION_CITY', columns: ['country_code', 'ville_normalisee', 'id'])]
#[ORM\HasLifecycleCallbacks]
class Localisation
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $villeNormalisee = null;

    #[ORM\Column(type: Types::BINARY, length: 32, nullable: true)]
    private ?string $addressFingerprint = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $departement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ruePostale = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $arrondissement = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 15, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 15, nullable: true)]
    private ?string $longitude = null;

    /** Score de la dernière vérification API Adresse (BAN), 0..1. */
    #[ORM\Column(nullable: true)]
    private ?float $banScore = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $banVerifieLe = null;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->initializeTimestamps();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function pays(): ?string
    {
        return $this->pays;
    }

    public function changePays(?string $pays): void
    {
        $this->pays = self::normalizeNullableString($pays);
        $this->refreshSearchFields();
        $this->touch();
    }

    public function countryCode(): ?string
    {
        return $this->countryCode;
    }

    public function changeCountryCode(?string $countryCode): void
    {
        $countryCode = self::normalizeNullableString($countryCode);
        if (null !== $countryCode && 2 !== strlen($countryCode)) {
            throw new \InvalidArgumentException('Le code pays doit contenir deux lettres ISO.');
        }
        $this->countryCode = null === $countryCode ? null : strtoupper($countryCode);
        $this->refreshSearchFields();
        $this->touch();
    }

    public function region(): ?string
    {
        return $this->region;
    }

    public function changeRegion(?string $region): void
    {
        $region = self::normalizeNullableString($region);
        // Libellé canonique INSEE (« Île-De-France » → « Île-de-France ») ;
        // une valeur hors référentiel (région étrangère) reste telle quelle.
        $this->region = null === $region ? null : ReferentielGeographiqueFrancais::normaliserRegion($region);
        $this->touch();
    }

    public function departement(): ?string
    {
        return $this->departement;
    }

    public function changeDepartement(?string $departement): void
    {
        $departement = self::normalizeNullableString($departement);
        $this->departement = null === $departement ? null : ReferentielGeographiqueFrancais::normaliserDepartement($departement);
        $this->touch();
    }

    public function ruePostale(): ?string
    {
        return $this->ruePostale;
    }

    public function changeRuePostale(?string $ruePostale): void
    {
        $this->ruePostale = self::normalizeNullableString($ruePostale);
        $this->refreshSearchFields();
        $this->touch();
    }

    public function codePostal(): ?string
    {
        return $this->codePostal;
    }

    public function changeCodePostal(?string $codePostal): void
    {
        $codePostal = self::normalizeNullableString($codePostal);
        // Zéro initial perdu par un tableur : réparé pour la France
        // uniquement (les CP belges ou suisses à 4 chiffres sont légitimes).
        // Le pays est renseigné avant le code postal dans tous les flux
        // (imports, formulaire, API).
        if (null !== $codePostal && $this->estFrance()) {
            $codePostal = ReferentielGeographiqueFrancais::reparerCodePostal($codePostal);
        }
        $this->codePostal = $codePostal;
        $this->refreshSearchFields();
        $this->touch();
    }

    private function estFrance(): bool
    {
        return 'FR' === $this->countryCode
            || (null !== $this->pays && 'france' === ReferentielGeographiqueFrancais::cle($this->pays));
    }

    public function ville(): ?string
    {
        return $this->ville;
    }

    public function changeVille(?string $ville): void
    {
        $this->ville = self::normalizeNullableString($ville);
        $this->refreshSearchFields();
        $this->touch();
    }

    public function arrondissement(): ?string
    {
        return $this->arrondissement;
    }

    public function changeArrondissement(?string $arrondissement): void
    {
        $this->arrondissement = self::normalizeNullableString($arrondissement);
        $this->touch();
    }

    public function latitude(): ?string
    {
        return $this->latitude;
    }

    public function changeLatitude(?string $latitude): void
    {
        $this->latitude = self::normalizeLatitude($latitude);
        $this->touch();
    }

    /** @throws \InvalidArgumentException */
    public static function normalizeLatitude(?string $latitude): ?string
    {
        return self::normalizeCoordinate($latitude, 90, 'latitude');
    }

    public function longitude(): ?string
    {
        return $this->longitude;
    }

    public function changeLongitude(?string $longitude): void
    {
        $this->longitude = self::normalizeLongitude($longitude);
        $this->touch();
    }

    /** @throws \InvalidArgumentException */
    public static function normalizeLongitude(?string $longitude): ?string
    {
        return self::normalizeCoordinate($longitude, 180, 'longitude');
    }

    public function banScore(): ?float
    {
        return $this->banScore;
    }

    public function banVerifieLe(): ?\DateTimeImmutable
    {
        return $this->banVerifieLe;
    }

    /** Trace de vérification API Adresse (BAN) — donnée technique, pas de touch(). */
    public function recordBanVerification(?float $score): void
    {
        $this->banScore = $score;
        $this->banVerifieLe = new \DateTimeImmutable();
    }

    public function villeNormalisee(): ?string
    {
        return $this->villeNormalisee;
    }

    public function addressFingerprint(): ?string
    {
        return $this->addressFingerprint;
    }

    private function refreshSearchFields(): void
    {
        $this->villeNormalisee = null === $this->ville ? null : self::normalizeForSearch($this->ville);
        $parts = array_filter([$this->countryCode, $this->codePostal, $this->ville, $this->ruePostale], static fn (?string $part): bool => null !== $part);
        $this->addressFingerprint = [] === $parts ? null : hash('sha256', self::normalizeForSearch(implode('|', $parts)), true);
    }

    private static function normalizeForSearch(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return mb_strtolower(preg_replace('/\s+/', ' ', trim(false === $ascii ? $value : $ascii)) ?? $value);
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private static function normalizeCoordinate(
        ?string $value,
        int $maximumAbsoluteValue,
        string $coordinate,
    ): ?string {
        $value = self::normalizeNullableString($value);
        if (null === $value) {
            return null;
        }

        if (1 !== preg_match('/^[+-]?\d+(?:\.\d{1,15})?$/', $value)) {
            throw new \InvalidArgumentException(sprintf('The %s must be a decimal number with at most 15 decimal places.', $coordinate));
        }

        $absoluteValue = ltrim($value, '+-');
        [$integerPart, $decimalPart] = array_pad(explode('.', $absoluteValue, 2), 2, '');
        $integerPart = ltrim($integerPart, '0');
        $integerPart = '' === $integerPart ? '0' : $integerPart;
        $outsideBounds = strlen($integerPart) > strlen((string) $maximumAbsoluteValue)
            || (int) $integerPart > $maximumAbsoluteValue
            || ((int) $integerPart === $maximumAbsoluteValue && '' !== trim($decimalPart, '0'));

        if ($outsideBounds) {
            throw new \InvalidArgumentException(sprintf(
                'The %s must be a number between -%d and %d.',
                $coordinate,
                $maximumAbsoluteValue,
                $maximumAbsoluteValue,
            ));
        }

        return $value;
    }
}
