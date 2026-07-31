<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Service\AdminPageCatalog;
use App\Pim\Service\AdminEventCatalog;
use App\Pim\Service\AdminEventMonitor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_pim_admin', methods: ['GET'])]
    public function __invoke(Request $request, AdminPageCatalog $catalog, AdminEventCatalog $eventCatalog, AdminEventMonitor $eventMonitor): Response
    {
        return $this->render('pim/admin.html.twig', [
            'page_groups' => $catalog->groups(),
            'event_catalog' => $eventCatalog->events(),
            'event_monitor' => $eventMonitor->snapshot($request->query->has('fresh')),
        ]);
    }
}
