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

    public function testWorkspaceRendersTheEditorInsideTheDemoLayout(): void
    {
        $client = static::createClient();
        $client->request('GET', '/demo/scenarios/text/open');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.demo-topbar');
        self::assertSelectorExists('main[data-nowo-ui-bundle="NowoPdfEditorBundle"] [data-testid="pdf-editor-workspace"] #nowo-pdf-editor-react-root');
        self::assertSelectorExists('script[type="module"][src="/bundles/nowopdfeditor/build/pdf-editor.js"]');
        $config = (string) $client->getCrawler()->filter('#nowo-pdf-editor-react-root')->attr('data-config');
        self::assertStringContainsString('"scenario":{"id":"text"', $config);
    }

    public function testUploadPageRendersTheUiKitFormScaffold(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pdf-editor');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.demo-topbar');
        self::assertSelectorExists('[data-nowo-ui-page="form"][data-testid="pdf-editor-upload"] form[enctype="multipart/form-data"] input[type="file"]');
        self::assertSelectorTextContains('.demo-upload-aside h2', 'How it works');
    }
}
