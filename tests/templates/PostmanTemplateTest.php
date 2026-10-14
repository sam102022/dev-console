<?php
declare(strict_types=1);

namespace App\tests\templates;

use App\tests\AbstractTestCase;

class PostmanTemplateTest extends AbstractTestCase
{
    public function testPostmanTemplateHasIndependentAlertContainersAndNoGlobalOutput(): void
    {
        $html = self::$twig->render('postman.html.twig', [
            'has_postman_api_key' => true,
            'lang' => 'fr',
            'current_route' => 'postman',
        ]);

        // L'ancien conteneur global ne doit plus exister
        $this->assertStringNotContainsString('id="output"', $html);

        // Les 3 conteneurs dédiés doivent être présents avec la classe alert d-none mb-3
        $this->assertStringContainsString('<div id="workspaceOutput" class="alert d-none mb-3"></div>', $html);
        $this->assertStringContainsString('<div id="envOutput" class="alert d-none mb-3"></div>', $html);
        $this->assertStringContainsString('<div id="openapiOutput" class="alert d-none mb-3"></div>', $html);
    }
}
