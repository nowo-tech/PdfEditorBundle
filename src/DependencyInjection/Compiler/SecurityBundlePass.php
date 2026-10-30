<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails the container build with a clear message when SecurityBundle is not enabled (REQ-UI-002 "Controllers and
 * enforcement" rule 4).
 *
 * The editor reads the current user from the token storage (access checks and workspace ownership), and the built-in
 * role checker needs the authorization checker. Without SecurityBundle the container would otherwise fail later with
 * a generic "non-existent service" error, or — worse — a host could believe the editor is protected.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SecurityBundlePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_pdf_editor.security.allow_unauthenticated')) {
            return;
        }

        if (!$container->has('security.token_storage')) {
            throw new LogicException('NowoPdfEditorBundle requires SecurityBundle (the editor binds workspaces to the current user or session): register Symfony\Bundle\SecurityBundle\SecurityBundle in config/bundles.php.');
        }

        $allowUnauthenticated = (bool) $container->getParameter('nowo_pdf_editor.security.allow_unauthenticated');
        $customChecker        = $container->hasParameter('nowo_pdf_editor.security.access_checker')
            && $container->getParameter('nowo_pdf_editor.security.access_checker') !== null;

        if (!$allowUnauthenticated && !$customChecker && !$container->has('security.authorization_checker')) {
            throw new LogicException('NowoPdfEditorBundle: the built-in role checker (nowo_pdf_editor.security.access_roles) needs the security.authorization_checker service; enable SecurityBundle with a firewall, or set nowo_pdf_editor.security.access_checker to your own PdfEditorAccessCheckerInterface service.');
        }
    }
}
