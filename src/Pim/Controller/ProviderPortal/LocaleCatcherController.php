<?php

namespace App\Pim\Controller\ProviderPortal;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Translation\LocaleSwitcher;

class LocaleCatcherController extends AbstractController
{
    public function defaultLocaleRedirect(string $path = '', LocaleSwitcher $localeSwitcher)
    {
        $suffix = '' !== $path ? '/'.ltrim($path, '/') : '';

        return $this->redirect(sprintf('/%s/portal%s', $localeSwitcher->getLocale(), $suffix));
    }
}
