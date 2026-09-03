<?php

declare(strict_types=1);

namespace App\Demo;

final class ScenarioCatalog
{
    /** @return list<array{id: string, fixtureFile: string, title: string, lead: string, tools: list<string>, checklist: list<string>}> */
    public static function all(): array
    {
        return [
            [
                'id'          => 'full',
                'fixtureFile' => 'full-playground.pdf',
                'title'       => 'Full playground',
                'lead'        => 'All capabilities in one document: text, forms, watermark, pages and annotations.',
                'tools'       => ['select', 'text', 'form', 'annotate', 'watermark', 'redact'],
                'checklist'   => [
                    'Add FreeText and sticky notes',
                    'Edit AcroForm field in properties panel',
                    'Stamp watermark and remove DRAFT overlay',
                    'Redact a region, then Save to PDF',
                ],
            ],
            [
                'id'          => 'text',
                'fixtureFile' => 'text-paragraphs.pdf',
                'title'       => 'Text editing',
                'lead'        => 'Native PDF text, FreeText boxes and draft insert_text stamps.',
                'tools'       => ['select', 'text'],
                'checklist'   => [
                    'Select tool → click a text box to edit font/color',
                    'Text tool → click page to add FreeText',
                    'Select text span → Add to draft → Save',
                ],
            ],
            [
                'id'          => 'forms',
                'fixtureFile' => 'acroform-mixed.pdf',
                'title'       => 'AcroForm fields',
                'lead'        => 'Existing text, checkbox and dropdown widgets plus new fields.',
                'tools'       => ['select', 'form'],
                'checklist'   => [
                    'Select → click field → edit value and name',
                    'Form tool → add new text field on page',
                    'Save to persist widget changes',
                ],
            ],
            [
                'id'          => 'watermarks',
                'fixtureFile' => 'watermark-overlay.pdf',
                'title'       => 'Watermarks',
                'lead'        => 'Diagonal DRAFT overlay plus toolbar watermark stamp.',
                'tools'       => ['watermark', 'remove-watermarks'],
                'checklist'   => [
                    'Watermark tool → adds CONFIDENTIAL stamp to draft',
                    'Remove watermarks → queues XObject cleanup',
                    'Save to apply pdf-lib watermark ops',
                ],
            ],
            [
                'id'          => 'pages',
                'fixtureFile' => 'multi-page-5.pdf',
                'title'       => 'Page operations',
                'lead'        => 'Five pages: rotate, delete, duplicate and insert blank sheets.',
                'tools'       => ['select', 'rotate', 'delete-page', 'insert-page', 'duplicate-page'],
                'checklist'   => [
                    'Rotate current page 90°',
                    'Delete, duplicate or insert blank via page rail menu',
                    'Navigate with thumbnails and Save',
                ],
            ],
            [
                'id'          => 'redact',
                'fixtureFile' => 'text-paragraphs.pdf',
                'title'       => 'Redaction',
                'lead'        => 'Mark sensitive regions with embedpdf redact mode.',
                'tools'       => ['redact', 'select'],
                'checklist'   => [
                    'Redact tool → drag regions to redact',
                    'Save exports redacted PDF via embedpdf',
                ],
            ],
            [
                'id'          => 'annotate',
                'fixtureFile' => 'minimal-blank.pdf',
                'title'       => 'Notes & comments',
                'lead'        => 'Sticky notes and comment annotations on a blank canvas.',
                'tools'       => ['annotate', 'select', 'text'],
                'checklist'   => [
                    'Comment tool → click to add sticky note',
                    'Select → move or delete annotation',
                ],
            ],
            [
                'id'          => 'images',
                'fixtureFile' => 'with-images.pdf',
                'title'       => 'Graphics layer',
                'lead'        => 'Page with drawn graphic blocks — overlay text and annotations.',
                'tools'       => ['text', 'select', 'annotate'],
                'checklist'   => [
                    'Add text over the blue block',
                    'Use redact to cover a region',
                ],
            ],
            [
                'id'          => 'blank',
                'fixtureFile' => 'minimal-blank.pdf',
                'title'       => 'Blank canvas',
                'lead'        => 'Empty page — build a document from scratch.',
                'tools'       => ['text', 'form', 'annotate', 'watermark'],
                'checklist'   => [
                    'Add text boxes and form fields',
                    'Stamp watermark across all pages',
                ],
            ],
        ];
    }

    /** @return array{id: string, fixtureFile: string, title: string, lead: string, tools: list<string>, checklist: list<string>}|null */
    public static function get(string $id): ?array
    {
        foreach (self::all() as $scenario) {
            if ($scenario['id'] === $id) {
                return $scenario;
            }
        }

        return null;
    }
}
