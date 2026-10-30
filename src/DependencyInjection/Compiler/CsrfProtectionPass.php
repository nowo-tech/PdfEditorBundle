<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails closed when CSRF protection is unavailable (REQ-SEC-005).
 *
 * The editor has two mutations: the upload form (`nowo_pdf_editor_home`, a Symfony form protected by the form CSRF
 * extension) and `apply` (an `X-CSRF-TOKEN` header checked against the CSRF token manager). A host that disables
 * `framework.form.csrf_protection`, or has no CSRF token manager, would silently turn the upload check off, so the
 * container refuses to compile instead.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_pdf_editor.default_profile')) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && $container->getParameter('form.type_extension.csrf.enabled') === false;

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoPdfEditorBundle requires CSRF protection for the upload form and the apply endpoint: enable framework.csrf_protection and framework.form.csrf_protection (symfony/security-csrf).');
        }
    }
}
