<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Security;

use Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

use function strlen;

#[CoversClass(WorkspaceOwnerResolver::class)]
final class WorkspaceOwnerResolverTest extends TestCase
{
    public function testNoTokenStorageAndNoRequestStackHasNoOwner(): void
    {
        self::assertNull((new WorkspaceOwnerResolver())->currentOwner());
    }

    public function testAuthenticatedUserOwnsByIdentifier(): void
    {
        $alice = new WorkspaceOwnerResolver($this->tokens('alice'));
        $bob   = new WorkspaceOwnerResolver($this->tokens('bob'));

        self::assertSame('user:' . hash('sha256', 'alice'), $alice->currentOwner());
        self::assertNotSame($alice->currentOwner(), $bob->currentOwner());
    }

    public function testAnonymousOwnerIsBoundToSessionToken(): void
    {
        $stack   = new RequestStack();
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $stack->push($request);

        $resolver = new WorkspaceOwnerResolver(new TokenStorage(), $stack);
        $first    = $resolver->currentOwner();

        self::assertNotNull($first);
        self::assertStringStartsWith('session:', $first);
        self::assertSame($first, $resolver->currentOwner(), 'Stable for the same session.');
        self::assertIsString($session->get(WorkspaceOwnerResolver::SESSION_KEY));

        $otherStack   = new RequestStack();
        $otherRequest = new Request();
        $otherRequest->setSession(new Session(new MockArraySessionStorage()));
        $otherStack->push($otherRequest);

        self::assertNotSame($first, (new WorkspaceOwnerResolver(new TokenStorage(), $otherStack))->currentOwner());
    }

    public function testTamperedSessionTokenIsRegenerated(): void
    {
        $stack   = new RequestStack();
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set(WorkspaceOwnerResolver::SESSION_KEY, 'short');
        $request->setSession($session);
        $stack->push($request);

        (new WorkspaceOwnerResolver(null, $stack))->currentOwner();

        $token = $session->get(WorkspaceOwnerResolver::SESSION_KEY);
        self::assertIsString($token);
        self::assertSame(32, strlen($token));
    }

    public function testRequestWithoutSessionHasNoOwner(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request());

        self::assertNull((new WorkspaceOwnerResolver(new TokenStorage(), $stack))->currentOwner());
    }

    private function tokens(string $identifier): TokenStorage
    {
        $storage = new TokenStorage();
        $user    = new InMemoryUser($identifier, null, ['ROLE_ADMIN']);
        $storage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return $storage;
    }
}
