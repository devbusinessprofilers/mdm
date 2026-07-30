<?php

namespace App\Pim\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleRedirectListener implements EventSubscriberInterface
{
    public const USER_LANGUAGE_CHOICE_SESSION_KEY = 'userLanguageChoice';

    /**
     * Marqueur ajouté par le sélecteur de langue. Seules les URL le portant
     * mémorisent le choix de l'utilisateur en session.
     */
    public const LOCALE_SWITCH_QUERY_PARAM = 'setlang';

    /**
     * Locale utilisée quand aucune langue du navigateur ne fait partie des locales du site.
     */
    public const FALLBACK_LOCALE = 'fr';

    /**
     * @param string[] $locales
     */
    public function __construct(
        private readonly string $defaultLocale,
        private readonly array $locales
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        if ($request->isXmlHttpRequest()) {
            return;
        }

        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return;
        }

        $pathInfo = $request->getPathInfo();

        $explicitLocale = $this->getPathLocale($pathInfo);
        if ($explicitLocale !== null) {
            // Clic explicite sur le sélecteur de langue (marqueur en query string) :
            // on mémorise le choix puis on redirige vers l'URL propre, sans le marqueur.
            if ($request->query->has(self::LOCALE_SWITCH_QUERY_PARAM)) {
                if ($request->hasSession()) {
                    $request->getSession()->set(self::USER_LANGUAGE_CHOICE_SESSION_KEY, $explicitLocale);
                }

                $event->setResponse(new RedirectResponse($this->buildUrlWithoutSwitchParam($request), 302));

                return;
            }

            // URL préfixée atteinte sans le sélecteur (saisie manuelle, favori, lien) :
            // on ne mémorise rien et on réaligne la locale affichée sur la langue préférée
            // (session, puis navigateur). On ne redirige que si elle diffère, pour éviter
            // toute boucle lorsque l'URL correspond déjà à la préférence.
            $preferredLanguage = $this->getPreferredLanguage($request);
            if ($preferredLanguage !== $explicitLocale) {
                $rest = substr($pathInfo, strlen('/' . $explicitLocale));
                $targetPath = rtrim($request->getBaseUrl(), '/') . '/' . $preferredLanguage . $rest;
                $queryString = $request->getQueryString();
                if ($queryString) {
                    $targetPath .= '?' . $queryString;
                }

                $event->setResponse(new RedirectResponse($targetPath, 302));
            }

            return;
        }

        // Pour une URL sans locale, le choix en session est prioritaire sur la langue du navigateur.
        $preferredLanguage = $this->getPreferredLanguage($request);

        // La racine est redirigée vers la locale préférée sans perdre les paramètres de requête.
        if ($pathInfo === '/') {
            // Slash final : la page d'accueil est routée sur "/{_locale}/".
            $targetPath = rtrim($request->getBaseUrl(), '/') . '/' . ($preferredLanguage ?: $this->defaultLocale) . '/';
            $queryString = $request->getQueryString();
            if ($queryString) {
                $targetPath .= '?' . $queryString;
            }

            $event->setResponse(new RedirectResponse($targetPath, 302));

            return;
        }

        // Keep Admin routes unprefixed (e.g. /admin/*), but preserve
        if ($pathInfo === '/admin/login') {
            $targetPath = rtrim($request->getBaseUrl(), '/') . '/' . $preferredLanguage . $pathInfo;
            $queryString = $request->getQueryString();
            if ($queryString) {
                $targetPath .= '?' . $queryString;
            }

            $event->setResponse(new RedirectResponse($targetPath, 302));

            return;
        }
        $excludedPrefixes = [
            '/_wdt',
            '/_profiler',
            '/_error',
            '/_fragment',
            '/_components',
            '/dev_reset',
            '/assets',
            '/bundles',
            '/translations',
            '/api',
            '/admin',
            '/health',
        ];

        foreach ($this->locales as $locale) {
            $prefix = '/' . $locale;
            if ($pathInfo !== $prefix && !str_starts_with($pathInfo, $prefix . '/')) {
                continue;
            }

            $pathWithoutLocale = substr($pathInfo, strlen($prefix));
            if ($pathWithoutLocale === false || $pathWithoutLocale === '') {
                return;
            }

            if ($pathWithoutLocale === '/admin/login') {
                return;
            }

            foreach ($excludedPrefixes as $excludedPrefix) {
                if (!str_starts_with($pathWithoutLocale, $excludedPrefix)) {
                    continue;
                }

                $targetPath = rtrim($request->getBaseUrl(), '/') . $pathWithoutLocale;
                $queryString = $request->getQueryString();
                if ($queryString) {
                    $targetPath .= '?' . $queryString;
                }

                $event->setResponse(new RedirectResponse($targetPath, 302));

                return;
            }

            return;
        }

        if ($pathInfo === '/admin/login') {
            $targetPath = rtrim($request->getBaseUrl(), '/') . '/' . $this->defaultLocale . $pathInfo;
            $queryString = $request->getQueryString();
            if ($queryString) {
                $targetPath .= '?' . $queryString;
            }

            $event->setResponse(new RedirectResponse($targetPath, 302));

            return;
        }

        foreach ($excludedPrefixes as $excludedPrefix) {
            if (str_starts_with($pathInfo, $excludedPrefix)) {
                return;
            }
        }

        $targetPath = rtrim($request->getBaseUrl(), '/') . '/' . $preferredLanguage . $pathInfo;
        $queryString = $request->getQueryString();
        if ($queryString) {
            $targetPath .= '?' . $queryString;
        }

        $event->setResponse(new RedirectResponse($targetPath, 302));
    }

    /**
     * Reconstruit l'URL courante en retirant le marqueur du sélecteur de langue,
     * tout en préservant les autres paramètres de requête.
     */
    private function buildUrlWithoutSwitchParam(Request $request): string
    {
        $params = $request->query->all();
        unset($params[self::LOCALE_SWITCH_QUERY_PARAM]);

        $targetPath = rtrim($request->getBaseUrl(), '/') . $request->getPathInfo();
        if ($params !== []) {
            $targetPath .= '?' . http_build_query($params);
        }

        return $targetPath;
    }

    /**
     * Retourne la locale portée explicitement par le premier segment de l'URL.
     */
    private function getPathLocale(string $pathInfo): ?string
    {
        foreach ($this->locales as $locale) {
            $prefix = '/' . $locale;
            if ($pathInfo === $prefix || str_starts_with($pathInfo, $prefix . '/')) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Retourne la locale de session, puis celle du navigateur, puis la locale de repli.
     *
     * Si aucune langue du navigateur ne fait partie des locales du site, le site bascule
     * sur la locale de repli.
     */
    private function getPreferredLanguage(Request $request): string
    {
        if ($request->hasSession()) {
            $userLanguageChoice = $request->getSession()->get(self::USER_LANGUAGE_CHOICE_SESSION_KEY);
            if (is_string($userLanguageChoice) && in_array($userLanguageChoice, $this->locales, true)) {
                return $userLanguageChoice;
            }
        }

        // Symfony::getPreferredLanguage() renvoie la première locale de la liste quand aucune
        // langue du navigateur ne correspond ; on fait donc notre propre correspondance pour
        // pouvoir distinguer ce cas et basculer explicitement sur la locale de repli.
        foreach ($request->getLanguages() as $language) {
            $base = strtolower(explode('_', str_replace('-', '_', $language))[0]);
            if (in_array($base, $this->locales, true)) {
                return $base;
            }
        }

        return self::FALLBACK_LOCALE;
    }
}
