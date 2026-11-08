<?php

ini_set('memory_limit', '512M');

$dir = dirname(__DIR__).'/app/demo-cover-staging';

$map = [
    '01_guyam_picturesque.jpg' => '01_guyam_16x9.jpg',
    '03_corregidor_casolian.jpg' => '03_corregidor_16x9.jpg',
    '04_kawhagan_unsplash_source.webp' => '04_kawhagan_16x9.jpg',
    '06_daku_siargao18.jpg' => '06_daku_16x9.jpg',
    '07_pansukian_naked.jpg' => '07_naked_16x9.jpg',
    '12_sohoton_cove_flickr.jpg' => '12_sohoton_16x9.jpg',
];

foreach ($map as $srcName => $dstName) {
    $src = $dir.'/'.$srcName;
    $dst = $dir.'/'.$dstName;

    if (! file_exists($src) || filesize($src) < 1000) {
        echo "missing_or_tiny {$srcName}\n";
        continue;
    }

    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $img = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($src),
        'png' => @imagecreatefrompng($src),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => false,
    };

    if (! $img) {
        echo "fail_decode {$srcName}\n";
        continue;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $targetRatio = 16 / 9;
    $srcRatio = $w / max($h, 1);

    if ($srcRatio > $targetRatio) {
        $cropH = $h;
        $cropW = (int) round($h * $targetRatio);
        $x = (int) (($w - $cropW) / 2);
        $y = 0;
    } else {
        $cropW = $w;
        $cropH = (int) round($w / $targetRatio);
        $x = 0;
        $y = (int) (($h - $cropH) / 2);
    }

    $outW = 1600;
    $outH = 900;
    $out = imagecreatetruecolor($outW, $outH);
    imagecopyresampled($out, $img, 0, 0, $x, $y, $outW, $outH, $cropW, $cropH);
    imagejpeg($out, $dst, 82);
    imagedestroy($img);
    imagedestroy($out);

    echo $dstName.' '.filesize($dst)."\n";
}
