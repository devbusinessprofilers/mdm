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
            $content = file_get_contents($template->getPathname());
            self::assertIsString($content);
            self::assertDoesNotMatchRegularExpression('/<script\b/i', $content, $template->getPathname());
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $content, $template->getPathname());
            self::assertDoesNotMatchRegularExpression('/javascript\s*:/i', $content, $template->getPathname());
            self::assertDoesNotMatchRegularExpression('/<(?:form|input|select|textarea)\b/i', $content, $template->getPathname());
            self::assertDoesNotMatchRegularExpression('/<button\b[^>]*\btype\s*=\s*["\'](?:submit|reset)["\']/i', $content, $template->getPathname());
        }
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

        $template = file_get_contents(dirname(__DIR__, 2).'/templates/pim/lieu/form.html.twig');
        self::assertIsString($template);
        foreach (['form.salles', 'form.periodesFermeture', 'form.acces', 'form.ressources'] as $collection) {
            self::assertStringContainsString($collection, $template);
        }
        self::assertStringContainsString('data-action="form-collection#add"', $template);
        self::assertStringContainsString('data-action="form-collection#remove"', $template);
    }
}
