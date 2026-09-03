<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoScenarioControllerTest extends WebTestCase
{
    public function testScenariosHubRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/demo/scenarios');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Scenario hub');
    }

    public function testOpenScenarioRedirectsToWorkspace(): void
    {
        $client = static::createClient();
        $client->request('GET', '/demo/scenarios/text/open');
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/pdf-editor/', $location);
        self::assertStringContainsString('scenario=text', $location);
    }
}
