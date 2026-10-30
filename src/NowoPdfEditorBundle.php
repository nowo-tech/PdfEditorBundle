<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle;

use Nowo\PdfEditorBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\PdfEditorBundle\DependencyInjection\Compiler\SecurityBundlePass;
use Nowo\PdfEditorBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Isolated visual PDF editor workspace for Symfony.
 *
 * The bundle class, the Twig namespace (`@NowoPdfEditorBundle`), the application override directory
 * (`templates/bundles/NowoPdfEditorBundle/`) and the translation domain share the same public identifier
 * (REQ-TWIG-002 / REQ-I18N-003). Not final only because the deprecated {@see PdfEditorBundle} alias extends it;
 * treat it as final.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class NowoPdfEditorBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // @igor-ignore - Container compile-time registration; not runtime worker state.
        $container->addCompilerPass(new TwigPathsPass());
        // @igor-ignore - Container compile-time registration; not runtime worker state.
        $container->addCompilerPass(new SecurityBundlePass());
        // @igor-ignore - Container compile-time registration; not runtime worker state.
        $container->addCompilerPass(new CsrfProtectionPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof PdfEditorExtension) {
            // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
            $this->extension = new PdfEditorExtension();
        }

        return $this->extension;
    }
}
