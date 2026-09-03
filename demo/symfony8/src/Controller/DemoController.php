<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

final class DemoController extends AbstractController
{
    public function home(
        PdfEngineInterface $engine,
        EditorProfile $profile,
    ): Response {
        try {
            $engineInfo = $engine->version();
        } catch (\Throwable) {
            $engineInfo = null;
        }

        return $this->render('demo/home.html.twig', [
            'symfony_version'  => Kernel::VERSION,
            'php_version'      => PHP_VERSION,
            'frankenphp_mode'  => $_SERVER['FRANKENPHP_MODE'] ?? getenv('FRANKENPHP_MODE') ?: 'worker',
            'engine'           => $engineInfo,
            'profile'          => $profile,
        ]);
    }
}
