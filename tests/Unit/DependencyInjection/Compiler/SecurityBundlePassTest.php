<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\PdfEditorBundle\DependencyInjection\Compiler\SecurityBundlePass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * REQ-UI-002 rule 4: the container refuses to compile without SecurityBundle.
 */
#[CoversClass(SecurityBundlePass::class)]
final class SecurityBundlePassTest extends TestCase
{
    public function testSkipsWhenTheBundleIsNotConfigured(): void
    {
        $container = new ContainerBuilder();
        (new SecurityBundlePass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testFailsWithoutTokenStorage(): void
    {
        $container = $this->container(false, null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires SecurityBundle');
        (new SecurityBundlePass())->process($container);
    }

    public function testFailsWithoutAuthorizationCheckerForTheBuiltInRoleChecker(): void
    {
        $container = $this->container(false, null);
        $container->setDefinition('security.token_storage', new Definition(stdClass::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('security.authorization_checker');
        (new SecurityBundlePass())->process($container);
    }

    public function testPassesWithSecurityServices(): void
    {
        $container = $this->container(false, null);
        $container->setDefinition('security.token_storage', new Definition(stdClass::class));
        $container->setDefinition('security.authorization_checker', new Definition(stdClass::class));
        (new SecurityBundlePass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testCustomCheckerOrDemoModeDoNotNeedTheAuthorizationChecker(): void
    {
        foreach ([[false, 'app.checker'], [true, null]] as [$allowUnauthenticated, $checker]) {
            $container = $this->container($allowUnauthenticated, $checker);
            $container->setDefinition('security.token_storage', new Definition(stdClass::class));
            (new SecurityBundlePass())->process($container);
        }

        $this->addToAssertionCount(1);
    }

    private function container(bool $allowUnauthenticated, ?string $checker): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_pdf_editor.security.allow_unauthenticated', $allowUnauthenticated);
        $container->setParameter('nowo_pdf_editor.security.access_checker', $checker);

        return $container;
    }
}
