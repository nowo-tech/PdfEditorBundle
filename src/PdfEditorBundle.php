<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle;

use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Isolated visual PDF editor (CKEditor-like workspace) for Symfony.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PdfEditorBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof PdfEditorExtension) {
            $this->extension = new PdfEditorExtension();
        }

        return $this->extension;
    }
}
