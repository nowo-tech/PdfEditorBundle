<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests;

use Nowo\PdfEditorBundle\Config\EditorProfile;

final class ProfileFactory
{
    public static function create(string $workspaceDir, string $engineScript = '/tmp/missing-engine.py'): EditorProfile
    {
        return new EditorProfile(
            name: 'default',
            pythonBinary: 'python3',
            engineScript: $engineScript,
            timeout: 5.0,
            idleTimeout: 2.0,
            maxUploadBytes: 1024 * 1024,
            renderDpi: 72,
            workspaceDir: $workspaceDir,
            engineMode: 'python',
            allowUnauthenticated: true,
            roles: ['ROLE_ADMIN'],
        );
    }
}
