<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function bin2hex;
use function hash;
use function is_string;
use function random_bytes;
use function strlen;

/**
 * Authenticated users own workspaces by user identifier; anonymous callers by a random token kept in their
 * session (survives session id migration). Only a SHA-256 of the key is ever stored on disk.
 */
final readonly class WorkspaceOwnerResolver implements WorkspaceOwnerResolverInterface
{
    public const SESSION_KEY = '_nowo_pdf_editor_owner';

    public function __construct(
        private ?TokenStorageInterface $tokenStorage = null,
        private ?RequestStack $requestStack = null,
    ) {
    }

    public function currentOwner(): ?string
    {
        $user = $this->tokenStorage?->getToken()?->getUser();
        if ($user instanceof UserInterface && $user->getUserIdentifier() !== '') {
            return 'user:' . hash('sha256', $user->getUserIdentifier());
        }

        $request = $this->requestStack?->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        $token   = $session->get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 32) {
            $token = bin2hex(random_bytes(16));
            // @igor-ignore - Per-request session (RequestStack), not shared worker service state.
            $session->set(self::SESSION_KEY, $token);
        }

        return 'session:' . hash('sha256', $token);
    }
}
