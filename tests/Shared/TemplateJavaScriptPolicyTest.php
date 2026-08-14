<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;

final class TemplateJavaScriptPolicyTest extends TestCase
{
    public function testTwigTemplatesContainNoExecutableInlineJavaScript(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/templates'));
        foreach ($iterator as $template) {
            if (!$template->isFile() || 'twig' !== $template->getExtension()) {
                continue;
            }
            $path = $template->getPathname();
            $content = file_get_contents($path);
            self::assertIsString($content);

            // Règles universelles : aucun JavaScript exécutable inline, où que ce soit.
            self::assertDoesNotMatchRegularExpression('/<script\b/i', $content, $path);
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $content, $path);
            self::assertDoesNotMatchRegularExpression('/javascript\s*:/i', $content, $path);

            // Les éléments de formulaire bruts (input, select, form…) sont interdits
            // dans les pages, mais légitimes dans deux endroits sanctionnés :
            //   - la bibliothèque de composants du design-system (templates/pim/components) ;
            //   - le thème de formulaire Symfony (templates/form).
            // C'est là que vivent les <input>/<select> ; ailleurs, on passe par un
            // composant ou par le thème de formulaire (ActionType pour un POST+CSRF).
            if ($this->estZoneComposant($path)) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression('/<(?:form|input|select|textarea)\b/i', $content, $path);
            self::assertDoesNotMatchRegularExpression('/<button\b[^>]*\btype\s*=\s*["\'](?:submit|reset)["\']/i', $content, $path);
        }
    }

    /**
     * Zones sanctionnées où les éléments de formulaire bruts sont autorisés :
     * la bibliothèque de composants et le thème de formulaire.
     */
    private function estZoneComposant(string $path): bool
    {
        $normalise = str_replace('\\', '/', $path);

        return str_contains($normalise, '/templates/pim/components/')
            || str_contains($normalise, '/templates/pim/form/')
            || str_contains($normalise, '/templates/form/');
    }

    public function testPageControllersAreLazyAndDemoAssetsAreAbsent(): void
    {
        $project = dirname(__DIR__, 2);
        foreach (['form_collection', 'confirm', 'hot_reload'] as $controller) {
            $content = file_get_contents($project.'/assets/controllers/'.$controller.'_controller.js');
            self::assertIsString($content);
            self::assertStringContainsString("stimulusFetch: 'lazy'", $content);
        }

        self::assertFileDoesNotExist($project.'/assets/controllers/hello_controller.js');
        $app = file_get_contents($project.'/assets/app.js');
        self::assertIsString($app);
        self::assertStringNotContainsString('console.log', $app);
    }

    public function testCollectionControllerUsesStimulusLifecycleSafeContracts(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2).'/assets/controllers/form_collection_controller.js');
        self::assertIsString($content);
        self::assertStringContainsString("static targets = ['items']", $content);
        self::assertStringContainsString('static values = {', $content);
        self::assertStringContainsString("dataset.action = 'form-collection#remove'", $content);
        self::assertStringNotContainsString('addEventListener', $content);

        // L'éditeur MDM porte désormais les collections (macro `collection`).
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/mdm/fiche_editeur.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('data-controller="form-collection"', $template);
        self::assertStringContainsString('data-action="form-collection#add"', $template);
        self::assertStringContainsString('data-action="form-collection#remove"', $template);
    }
}
