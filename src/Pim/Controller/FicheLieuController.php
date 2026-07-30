<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\FicheLieuMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Éditeur de fiche Lieu : 16 sections, 124 champs.
 *
 * Intégration de la maquette uniquement. La section affichée est choisie par
 * la query string, en attendant un vrai routage par onglet ; rien n'est
 * enregistré.
 */
final class FicheLieuController extends AbstractController
{
    #[Route('/referentiel/lieux/fiche', name: 'app_mdm_fiche_lieu', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $index = FicheLieuMaquette::indexValide($request->query->getInt('section'));
        $capacitesEnEdition = 'edition' === $request->query->getString('capacites');

        // Options des menus déroulants, une entrée par champ de type liste.
        $listes = [];

        foreach (FicheLieuMaquette::section($index)['champs'] as $champ) {
            if ($champ['liste']) {
                $listes[$champ['label']] = FicheLieuMaquette::optionsListe(
                    $champ['label'],
                    $champ['valeur'],
                    $champ['vide'],
                );
            }
        }

        return $this->render('mdm/fiche_lieu.html.twig', [
            'index' => $index,
            'listes' => $listes,
            'onglets' => FicheLieuMaquette::onglets($index),
            'section' => FicheLieuMaquette::section($index),
            'groupes_puces' => FicheLieuMaquette::groupesPuces($index),
            'a_des_formules' => FicheLieuMaquette::aDesFormules($index),
            'a_des_medias' => FicheLieuMaquette::aDesMedias($index),
            'a_des_collaborateurs' => FicheLieuMaquette::aDesCollaborateurs($index),
            'collaborateurs' => FicheLieuMaquette::collaborateurs(),
            'a_des_disponibilites' => FicheLieuMaquette::aDesDisponibilites($index),
            'a_des_capacites' => FicheLieuMaquette::aDesCapacites($index),
            'a_privatisation' => FicheLieuMaquette::aPrivatisation($index),
            'suggestions' => FicheLieuMaquette::suggestions($index),
            'jours' => FicheLieuMaquette::jours(),
            'periodes' => FicheLieuMaquette::periodesFermeture(),
            'salles' => FicheLieuMaquette::salles(),
            'canaux' => FicheLieuMaquette::canaux(),
            'historique' => FicheLieuMaquette::historique(),
            'capacites_en_edition' => $capacitesEnEdition,
        ]);
    }
}
