<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\PdfEditorBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * REQ-SEC-005: CSRF protection of the upload form and the apply endpoint fails closed.
 */
#[CoversClass(CsrfProtectionPass::class)]
final class CsrfProtectionPassTest extends TestCase
{
    public function testSkipsWhenTheBundleIsNotConfigured(): void
    {
        (new CsrfProtectionPass())->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }

    public function testPassesWithFormCsrfAndTokenManager(): void
    {
        (new CsrfProtectionPass())->process($this->container(true, true, true));

        $this->addToAssertionCount(1);
    }

    public function testFailsWithoutTokenManager(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires CSRF protection');
        (new CsrfProtectionPass())->process($this->container(false, true, true));
    }

    public function testFailsWithoutFormCsrfExtension(): void
    {
        $this->expectException(LogicException::class);
        (new CsrfProtectionPass())->process($this->container(true, false, true));
    }

    public function testFailsWhenFormCsrfIsDisabled(): void
    {
        $this->expectException(LogicException::class);
        (new CsrfProtectionPass())->process($this->container(true, true, false));
    }

    private function container(bool $tokenManager, bool $formExtension, bool $formCsrfEnabled): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_pdf_editor.default_profile', 'default');
        $container->setParameter('form.type_extension.csrf.enabled', $formCsrfEnabled);
        if ($tokenManager) {
            $container->setDefinition('security.csrf.token_manager', new Definition(stdClass::class));
        }
        if ($formExtension) {
            $container->setDefinition('form.type_extension.csrf', new Definition(stdClass::class));
        }

        return $container;
    }
}
