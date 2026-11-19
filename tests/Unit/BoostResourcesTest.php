<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Unit;

const BOOST_ROOT = __DIR__.'/../../resources/boost';

test('the guideline exists and names the skill', function () {
    $guideline = (string) file_get_contents(BOOST_ROOT.'/guidelines/core.blade.php');

    $this->assertStringContainsString('pharaonic/laravel-translatable', $guideline);
    $this->assertStringContainsString('translatable-development', $guideline);
    $this->assertSame(substr_count($guideline, '@verbatim'), substr_count($guideline, '@endverbatim'), 'Every code snippet is wrapped in @verbatim.');
});

test('every skill has frontmatter matching its folder', function () {
    $skills = glob(BOOST_ROOT.'/skills/*/SKILL.md') ?: [];
    $this->assertNotSame([], $skills);

    foreach ($skills as $path) {
        $contents = (string) file_get_contents($path);
        $this->assertSame(1, preg_match('/\A---\n(.*?)\n---\n/s', $contents, $match), $path.' starts with YAML frontmatter.');

        $fields = [];

        foreach (explode("\n", $match[1]) as $line) {
            if (preg_match('/^([a-z]+):\s*(.+)$/', $line, $field) === 1) {
                $fields[$field[1]] = $field[2];
            }
        }

        $this->assertSame(basename(dirname($path)), $fields['name'] ?? null, 'The skill name is its folder name.');
        $this->assertNotSame('', trim($fields['description'] ?? ''), 'The skill has a description.');
    }
});
