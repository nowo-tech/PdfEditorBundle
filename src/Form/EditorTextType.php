<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig(PdfEditorExtension::FORM_KIT_PROFILE)]
final class EditorTextType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'text', TextareaType::class, [
            'label'    => 'pdf_editor.properties.text',
            'required' => false,
            'attr'     => [
                'rows' => 4,
                'id'   => 'nowo-pdf-editor-text',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'NowoPdfEditorBundle',
            'csrf_token_id'      => 'pdf_editor',
        ]);
    }
}
