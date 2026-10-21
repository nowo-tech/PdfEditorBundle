<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_keys;
use function array_values;
use function basename;
use function file_get_contents;
use function glob;
use function implode;
use function preg_match_all;
use function sort;
use function sprintf;

/**
 * Every shipped locale must define the same keys as English (the fallback), and every key used by the
 * Twig views / React app must exist — otherwise the UI shows raw "pdf_editor.*" ids.
 */
#[CoversNothing]
final class TranslationParityTest extends TestCase
{
    private const DIR    = __DIR__ . '/../../../src/Resources/translations';
    private const DOMAIN = 'NowoPdfEditorBundle';

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideLocales(): iterable
    {
        foreach (glob(self::DIR . '/' . self::DOMAIN . '.*.yaml') ?: [] as $file) {
            $locale = (string) preg_replace('/^' . self::DOMAIN . '\.(.+)\.yaml$/', '$1', basename($file));
            yield $locale => [$locale];
        }
    }

    public function testAllShippedLocalesArePresent(): void
    {
        $locales = array_keys(iterator_to_array(self::provideLocales()));
        sort($locales);

        self::assertSame(['de', 'en', 'es', 'fr', 'it', 'nl', 'pt'], $locales);
    }

    #[DataProvider('provideLocales')]
    public function testLocaleHasSameKeysAsEnglish(string $locale): void
    {
        $english = $this->keys('en');
        $keys    = $this->keys($locale);

        self::assertSame([], array_values(array_diff($english, $keys)), sprintf('Keys missing in "%s".', $locale));
        self::assertSame([], array_values(array_diff($keys, $english)), sprintf('Keys in "%s" missing in English.', $locale));
    }

    #[DataProvider('provideLocales')]
    public function testLocaleValuesAreNotEmpty(string $locale): void
    {
        foreach ($this->catalogue($locale) as $key => $value) {
            self::assertIsString($value, $key);
            self::assertNotSame('', trim($value), sprintf('Empty translation for "%s" in "%s".', $key, $locale));
        }
    }

    public function testEveryKeyUsedInViewsAndAssetsIsTranslatedInEnglish(): void
    {
        $sources = [
            ...(glob(__DIR__ . '/../../../src/Resources/views/*/*.twig') ?: []),
            ...(glob(__DIR__ . '/../../../src/Resources/assets/src/*.ts*') ?: []),
            ...(glob(__DIR__ . '/../../../src/Resources/assets/src/*/*.ts*') ?: []),
        ];
        self::assertNotSame([], $sources);

        $used = [];
        foreach ($sources as $source) {
            preg_match_all('/[\'"](pdf_editor\.[a-z_]+(?:\.[a-z_]+)*)[\'"]/', (string) file_get_contents($source), $matches);
            foreach ($matches[1] as $key) {
                $used[$key] = true;
            }
        }
        self::assertNotSame([], $used);

        $missing = array_values(array_diff(array_keys($used), $this->keys('en')));
        self::assertSame([], $missing, 'Keys used in views/assets but missing in English: ' . implode(', ', $missing));
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $keys = array_keys($this->catalogue($locale));
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogue(string $locale): array
    {
        /** @var array<string, mixed> $data */
        $data = Yaml::parseFile(sprintf('%s/%s.%s.yaml', self::DIR, self::DOMAIN, $locale));

        return $data;
    }
}
