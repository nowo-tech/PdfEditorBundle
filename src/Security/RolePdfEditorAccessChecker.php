<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class RolePdfEditorAccessChecker implements PdfEditorAccessCheckerInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $roles,
    ) {
    }

    public function canUseEditor(?object $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach ($this->roles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
