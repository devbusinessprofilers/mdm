<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Entity\FicheSalesforceExport;
use App\Etl\Enum\SalesforceCsvInterface;
use App\Etl\Repository\FicheSalesforceExportRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Envoi manuel groupé d'une sélection de fiches vers Salesforce : e-mails
 * Produits multi-lignes découpés par taille de pièce jointe (une grosse
 * sélection part en quelques mails, pas en une pièce jointe démesurée), et un
 * unique e-mail Salles pour les fiches qui en possèdent. Tous types et tous
 * statuts, sans tri (décision produit). Le suivi est marqué envoyé pour
 * éviter un doublon immédiat par la synchro automatique.
 */
final readonly class SalesforceSelectionSender
{
    private const LOT_CHARGEMENT = 200;

    public function __construct(
        private FicheRepository $fiches,
        private FicheSalesforceExportRepository $exports,
        private SalesforceProduitsCsvExporter $produits,
        private SalesforceSallesCsvExporter $salles,
        private SalesforceCsvMailer $mailer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $ficheIds ULID texte
     *
     * @return int Nombre de fiches transmises
     *
     * @throws \DomainException si la synchro n'est pas configurée
     */
    public function envoyer(array $ficheIds): int
    {
        if (!$this->mailer->isConfigured()) {
            throw new \DomainException("La synchronisation Salesforce n'est pas activée (paramètres /admin).");
        }
        /** @var list<Fiche> $fiches */
        $fiches = [];
        foreach (array_chunk($ficheIds, self::LOT_CHARGEMENT) as $lot) {
            $ulids = array_map(static fn (string $id): Ulid => Ulid::fromString($id), $lot);
            foreach ($this->fiches->findBy(['id' => $ulids]) as $fiche) {
                $fiches[] = $fiche;
            }
        }
        if ([] === $fiches) {
            return 0;
        }

        foreach ($this->produits->csvParPaquets($fiches) as $paquet) {
            $this->mailer->envoyer(SalesforceCsvInterface::Produits, $paquet['csv']);
        }

        $avecSalles = array_values(array_filter($fiches, fn (Fiche $f): bool => $this->salles->possedeDesSalles($f)));
        if ([] !== $avecSalles) {
            $this->mailer->envoyer(SalesforceCsvInterface::Salles, $this->salles->csv($avecSalles));
        }

        $idsAvecSalles = array_fill_keys(array_map(static fn (Fiche $f): string => $f->idString(), $avecSalles), true);
        foreach ($fiches as $fiche) {
            $export = $this->exports->forFiche($fiche->id());
            if (null === $export) {
                $export = new FicheSalesforceExport($fiche->id(), $fiche->code());
                $this->entityManager->persist($export);
            }
            $export->recordProduitsSent($export->dirtyAt());
            if (isset($idsAvecSalles[$fiche->idString()])) {
                $export->recordSallesSent($export->dirtyAt());
            }
        }
        $this->entityManager->flush();

        return count($fiches);
    }

    /**
     * Envoi immédiat d'une seule fiche (bouton de la fiche) : Produits
     * uniquement, les salles suivent l'envoi nocturne groupé.
     *
     * @throws \DomainException si la synchro n'est pas configurée
     */
    public function envoyerFiche(Fiche $fiche): void
    {
        if (!$this->mailer->isConfigured()) {
            throw new \DomainException("La synchronisation Salesforce n'est pas activée (paramètres /admin).");
        }
        $this->mailer->envoyer(SalesforceCsvInterface::Produits, $this->produits->csv([$fiche]));
        $export = $this->exports->forFiche($fiche->id());
        if (null === $export) {
            $export = new FicheSalesforceExport($fiche->id(), $fiche->code());
            $this->entityManager->persist($export);
        }
        $export->recordProduitsSent($export->dirtyAt());
        $this->entityManager->flush();
    }
}
