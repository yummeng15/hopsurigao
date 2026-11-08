<?php

$dir = dirname(__DIR__).'/app/demo-cover-staging';

$map = [
    '01_four_island_guyam.jpg' => '01_four_island_guyam_16x9.jpg',
    '03_corregidor_casolian.jpg' => '03_corregidor_casolian_16x9.jpg',
    '06_daku_island.jpg' => '06_daku_island_16x9.jpg',
    '07_guyam_naked_guyam.jpg' => '07_guyam_16x9.jpg',
    '07b_naked_pansukian.jpg' => '07b_naked_pansukian_16x9.jpg',
    '12_bucas_grande_middle.jpg' => '12_bucas_grande_16x9.jpg',
];

foreach ($map as $srcName => $dstName) {
    $src = $dir.'/'.$srcName;
    $dst = $dir.'/'.$dstName;

    if (! file_exists($src)) {
        echo "missing {$srcName}\n";
        continue;
    }

    $img = @imagecreatefromjpeg($src);

    if (! $img) {
        echo "fail decode {$srcName}\n";
        continue;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $targetRatio = 16 / 9;
    $srcRatio = $w / $h;

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
