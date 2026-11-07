<?php

function logoImage(string $filename): GdImage
{
    $path = dirname(__DIR__, 2).'/public/images/'.$filename;
    $image = imagecreatefrompng($path);

    expect($image)->not->toBeFalse();

    return $image;
}

function logoPixel(GdImage $image, int $x, int $y): array
{
    $rgba = imagecolorat($image, $x, $y);

    return [
        'r' => ($rgba >> 16) & 0xFF,
        'g' => ($rgba >> 8) & 0xFF,
        'b' => $rgba & 0xFF,
        'a' => ($rgba >> 24) & 0x7F,
    ];
}

test('light mode logo uses a black wordmark on a transparent background', function () {
    $image = logoImage('hop-surigao-logo-light.png');

    $corner = logoPixel($image, 2, 2);
    expect($corner['a'])->toBe(127);

    $hop = logoPixel($image, 300, 90);
    expect($hop['r'])->toBe(0)
        ->and($hop['g'])->toBe(0)
        ->and($hop['b'])->toBe(0)
        ->and($hop['a'])->toBe(0);

    $surigao = logoPixel($image, 520, 90);
    expect($surigao['b'])->toBeGreaterThan(180)
        ->and($surigao['g'])->toBeGreaterThan(180)
        ->and($surigao['r'])->toBeLessThan(140);
});

test('dark mode logo keeps the white wordmark on a transparent background', function () {
    $image = logoImage('hop-surigao-logo.png');

    $corner = logoPixel($image, 2, 2);
    expect($corner['a'])->toBe(127);

    $hop = logoPixel($image, 300, 90);
    expect($hop['r'])->toBeGreaterThan(240)
        ->and($hop['g'])->toBeGreaterThan(240)
        ->and($hop['b'])->toBeGreaterThan(240);
});
