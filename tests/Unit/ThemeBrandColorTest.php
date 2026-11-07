<?php

function theme1Css(): string
{
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/themes/theme1.css');

    expect($css)->toBeString();

    return $css;
}

/**
 * @return array<string, string>
 */
function theme1Block(string $css, string $selector): array
{
    preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]+)\}/', $css, $matches);

    expect($matches)->not->toBeEmpty();

    preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/', $matches[1], $variables, PREG_SET_ORDER);

    $values = [];
    foreach ($variables as $variable) {
        $values[$variable[1]] = trim($variable[2]);
    }

    return $values;
}

test('light and dark brand colors stay the same cyan', function () {
    $css = theme1Css();

    expect($css)->not->toContain('#00bba7');

    $light = theme1Block($css, ':root');
    $dark = theme1Block($css, '.dark');

    expect($light['primary'])->toBe('#00FFFF')
        ->and($light['sidebar-primary'])->toBe('#00FFFF')
        ->and($dark['primary'])->toBe('#00FFFF')
        ->and($dark['sidebar-primary'])->toBe('#00FFFF')
        ->and($light['primary-foreground'])->toBe('#083344')
        ->and($dark['primary-foreground'])->toBe('#083344')
        ->and($light['sidebar-primary-foreground'])->toBe('#083344')
        ->and($dark['sidebar-primary-foreground'])->toBe('#083344');
});
