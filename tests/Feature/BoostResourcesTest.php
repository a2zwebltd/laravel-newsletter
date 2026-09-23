<?php

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__, 2).'/resources/boost';

it('ships the Boost guideline and skill files', function () use ($root) {
    expect($root.'/guidelines/core.blade.php')->toBeFile()
        ->and($root.'/skills/newsletter-development/SKILL.md')->toBeFile();
});

it('gives every skill frontmatter whose name matches its folder', function () use ($root) {
    $skills = glob($root.'/skills/*/SKILL.md');

    expect($skills)->not->toBeEmpty();

    foreach ($skills as $file) {
        preg_match('/\A---\R(.*?)\R---\R/s', (string) file_get_contents($file), $matches);
        expect($matches)->not->toBeEmpty();

        $frontmatter = Yaml::parse($matches[1]);

        expect($frontmatter['name'] ?? null)->toBe(basename(dirname($file)))
            ->and(trim((string) ($frontmatter['description'] ?? '')))->not->toBe('');
    }
});

it('renders the guideline through Blade', function () use ($root) {
    $html = Blade::render((string) file_get_contents($root.'/guidelines/core.blade.php'));

    expect($html)->toContain('## Laravel Newsletter')
        ->and($html)->toContain('newsletter-development');
});
