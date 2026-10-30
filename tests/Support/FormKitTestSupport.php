<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Nowo\PdfEditorBundle\Form\EditorTextType;
use Nowo\PdfEditorBundle\Form\PdfUploadType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

/**
 * Form factory for tests without a kernel: the editor form types get the {@see FormOptionsMerger} the FormKit
 * compiler pass injects in a container, with the bundle's own profile.
 */
final class FormKitTestSupport
{
    public static function merger(): FormOptionsMerger
    {
        $profile = PdfEditorExtension::FORM_KIT_PROFILE_CONFIG;

        return new FormOptionsMerger(
            [PdfEditorExtension::FORM_KIT_PROFILE => $profile, 'default' => $profile],
            'default',
            new ConstraintDefinitionFactory(),
        );
    }

    public static function formFactory(): FormFactoryInterface
    {
        $merger = self::merger();
        $upload = new PdfUploadType();
        $upload->setFormOptionsMerger($merger);
        $text = new EditorTextType();
        $text->setFormOptionsMerger($merger);

        return Forms::createFormFactoryBuilder()->addTypes([$upload, $text])->getFormFactory();
    }
}
