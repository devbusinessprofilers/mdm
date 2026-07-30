<?php

namespace App\Pim\Controller\ProviderPortal;

use App\Pim\Model\ProviderPortal\Mock\Chart\Analytics\Performance;
use App\Pim\Model\ProviderPortal\Mock\Chart\Analytics\SizeGroupChoiceViews;
use App\Pim\Model\ProviderPortal\Mock\Chart\Analytics\SRJEChoiceViews;
use App\Pim\Model\ProviderPortal\Mock\Chart\Dashboard\Dashboard;
use App\Pim\Model\ProviderPortal\Mock\Chart\EstablishmentChoiceViews;
use App\Pim\Model\ProviderPortal\Mock\Chart\PeriodChoiceViews;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChartController extends AbstractController
{
    #[Route(path: 'portal/account/dashboard', name: 'provider_portal_chart_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('provider_portal/pages/online/chart/dashboard.html.twig', [
            'dashboard' => Dashboard::mock(),
            'establishments' => EstablishmentChoiceViews::getChoiceViews(),
            'periods' => PeriodChoiceViews::getChoiceViews(),
            'desktopMenu' => 'Menu:Chart:Dashboard',
            'mobileMenu' => 'Menu:Chart:Dashboard:MobileMenu',
        ]);
    }

    #[Route(path: 'portal/account/analytics', name: 'provider_portal_chart_analytics')]
    public function analytics(): Response
    {
        return $this->render('provider_portal/pages/online/chart/analytics.html.twig', [
            'performance' => Performance::mock(),
            'establishments' => EstablishmentChoiceViews::getChoiceViews(),
            'periods' => PeriodChoiceViews::getChoiceViews(),
            'sizeGroups' => SizeGroupChoiceViews::getChoiceViews(),
            'srjeList' => SRJEChoiceViews::getChoiceViews(),
            'desktopMenu' => 'Menu:Chart:Analytics',
            'mobileMenu' => 'Menu:Chart:Analytics:MobileMenu',
        ]);
    }

    #[Route(path: 'portal/account/competition', name: 'provider_portal_chart_competition')]
    public function competition(): Response
    {
        return $this->redirectToRoute('provider_portal_chart_analytics');
    }
}
