<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DemoControllerTest extends WebTestCase
{
    public function testHomeRendersLandingInEnglish(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Edit PDFs inside Symfony');
        self::assertSelectorExists('a[href="/demo/scenarios"]');
        self::assertSelectorExists('.demo-locale-switch');
    }

    public function testLocaleSwitchToSpanish(): void
    {
        $client = static::createClient();
        $client->request('GET', '/demo/locale/es', [], [], [
            'HTTP_REFERER' => 'http://localhost/',
        ]);
        self::assertResponseRedirects('http://localhost/');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Edita PDFs en Symfony');
        self::assertSelectorExists('a.demo-locale-btn.is-active[href="/demo/locale/es"]');
    }
}
