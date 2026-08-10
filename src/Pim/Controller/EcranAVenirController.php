<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Destination d'attente des entrées de menu dont l'écran n'est pas intégré.
 *
 * Le composant `Header:Menu` du portail rend chaque feuille avec
 * `path(route)` : une entrée sans route lève une erreur. Plutôt que de poser un
 * lien mort ou de retirer la moitié du menu, chaque entrée en attente reçoit sa
 * propre URL et une page qui dit ce qu'il en est.
 *
 * **À supprimer au fil des intégrations** : chaque écran livré reprend son
 * entrée dans {@see \App\Pim\Maquette\EnteteMaquette}, et cette route disparaît
 * quand la dernière est branchée.
 */
final class EcranAVenirController extends AbstractController
{
    /** @var array<string, string> Intitulé de l'écran attendu, par code de menu */
    private const ECRANS = [
        'restaurants' => 'Restaurants',
        'activites' => 'Activités',
        'services' => 'Services événementiels',
        'plateaux' => 'Plateaux repas',
        'masse' => 'Mise à jour massive',
        'campagnes' => 'Campagnes IA',
        'imports' => 'Imports & exports',
        'synchronisation' => 'Synchronisation',
        'administration' => 'Administration',
        'champs' => 'Champs & taxonomies',
        'roles' => 'Rôles & droits',
        'utilisateurs' => 'Utilisateurs',
        'journal' => "Journal d'activité",
    ];

    #[Route('/a-venir/{ecran}', name: 'app_mdm_a_venir', methods: ['GET'])]
    public function __invoke(string $ecran): Response
    {
        if (!\array_key_exists($ecran, self::ECRANS)) {
            throw $this->createNotFoundException();
        }

        return $this->render('mdm/a_venir.html.twig', [
            'ecran' => $ecran,
            'titre' => self::ECRANS[$ecran],
        ]);
    }
}
