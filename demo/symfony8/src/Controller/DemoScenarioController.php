<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\FixturesLocator;
use App\Demo\ScenarioCatalog;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DemoScenarioController extends AbstractController
{
    public function __construct(
        private readonly FixturesLocator $fixtures,
    ) {
    }

    public function hub(): Response
    {
        return $this->render('demo/scenarios.html.twig', [
            'scenarios' => ScenarioCatalog::all(),
        ]);
    }

    public function open(string $scenario, WorkspaceManager $workspaces): Response
    {
        $meta = ScenarioCatalog::get($scenario);
        if ($meta === null) {
            throw $this->createNotFoundException(sprintf('Unknown scenario "%s".', $scenario));
        }

        $fixturePath = $this->fixtures->resolveFile($meta['fixtureFile']);

        $workspaces->ensureWorkspaceDir();
        $workspace = $workspaces->createFromPath($fixturePath, $meta['fixtureFile']);

        $url = $this->generateUrl('nowo_pdf_editor_workspace', [
            'id'       => $workspace->id,
            'scenario' => $scenario,
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        return new RedirectResponse($url);
    }
}
