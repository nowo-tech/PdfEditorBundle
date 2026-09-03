<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DemoLocaleController extends AbstractController
{
    private const LOCALES = ['en', 'es'];

    public function switch(string $locale, Request $request): Response
    {
        if (!\in_array($locale, self::LOCALES, true)) {
            throw $this->createNotFoundException(sprintf('Unsupported locale "%s".', $locale));
        }

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }

        $referer = $request->headers->get('referer');
        $host    = $request->getSchemeAndHttpHost();
        if (\is_string($referer) && str_starts_with($referer, $host)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('demo_home');
    }
}
