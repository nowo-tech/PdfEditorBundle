<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Security;

use Nowo\PdfEditorBundle\Security\AllowAllPdfEditorAccessChecker;
use Nowo\PdfEditorBundle\Security\RolePdfEditorAccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use stdClass;

#[CoversClass(AllowAllPdfEditorAccessChecker::class)]
#[CoversClass(RolePdfEditorAccessChecker::class)]
final class AccessCheckerTest extends TestCase
{
    public function testAllowAll(): void
    {
        self::assertTrue((new AllowAllPdfEditorAccessChecker())->canUseEditor(null));
    }

    public function testRoleCheckerRequiresUserAndRole(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);
        $checker = new RolePdfEditorAccessChecker($auth, ['ROLE_ADMIN']);
        self::assertFalse($checker->canUseEditor(null));
        self::assertTrue($checker->canUseEditor(new stdClass()));
    }

    public function testRoleCheckerDenies(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $checker = new RolePdfEditorAccessChecker($auth, ['ROLE_ADMIN']);
        self::assertFalse($checker->canUseEditor(new stdClass()));
    }
}
