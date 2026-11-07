<?php

$sources = [
    'boardwalk' => 'C:/Users/Melody Huerta/.cursor/projects/c-wamp64-www-hopsurigaov2/assets/c__Users_Melody_Huerta_AppData_Roaming_Cursor_User_workspaceStorage_a2c0ad593885f992d3bfd55d3d1fdb94_images_image-ba946ea5-72e6-48a4-b359-571d3428d913.png',
    'pilar' => 'C:/Users/Melody Huerta/.cursor/projects/c-wamp64-www-hopsurigaov2/assets/c__Users_Melody_Huerta_AppData_Roaming_Cursor_User_workspaceStorage_a2c0ad593885f992d3bfd55d3d1fdb94_images_image-64c0369d-7856-47d6-9919-902cad68e4ac.png',
    'socorro' => 'C:/Users/Melody Huerta/.cursor/projects/c-wamp64-www-hopsurigaov2/assets/c__Users_Melody_Huerta_AppData_Roaming_Cursor_User_workspaceStorage_a2c0ad593885f992d3bfd55d3d1fdb94_images_image-d1332496-0755-4c2b-9bb0-328b6f4ccbbb.jpg',
    'grid' => 'C:/Users/Melody Huerta/.cursor/projects/c-wamp64-www-hopsurigaov2/assets/c__Users_Melody_Huerta_AppData_Roaming_Cursor_User_workspaceStorage_a2c0ad593885f992d3bfd55d3d1fdb94_images_image-3c234464-1065-47ab-ad4b-cd82dd6f759c.jpg',
];

$out = __DIR__.'/spots';

if (! is_dir($out)) {
    mkdir($out, 0755, true);
}

foreach ($sources as $key => $path) {
    $image = imagecreatefromstring(file_get_contents($path));
    $width = imagesx($image);
    $height = imagesy($image);
    $mask = array_fill(0, $height, array_fill(0, $width, false));

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 255;
            $g = ($rgb >> 8) & 255;
            $b = $rgb & 255;
            $mask[$y][$x] = ! ($r > 248 && $g > 248 && $b > 248);
        }
    }

    $seen = array_fill(0, $height, array_fill(0, $width, false));
    $boxes = [];

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if (! $mask[$y][$x] || $seen[$y][$x]) {
                continue;
            }

            $queue = [[$x, $y]];
            $seen[$y][$x] = true;
            $minX = $x;
            $maxX = $x;
            $minY = $y;
            $maxY = $y;
            $count = 0;

            while ($queue) {
                [$cx, $cy] = array_pop($queue);
                $count++;
                $minX = min($minX, $cx);
                $maxX = max($maxX, $cx);
                $minY = min($minY, $cy);
                $maxY = max($maxY, $cy);

                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                    $nx = $cx + $dx;
                    $ny = $cy + $dy;

                    if ($nx < 0 || $ny < 0 || $nx >= $width || $ny >= $height) {
                        continue;
                    }

                    if ($seen[$ny][$nx] || ! $mask[$ny][$nx]) {
                        continue;
                    }

                    $seen[$ny][$nx] = true;
                    $queue[] = [$nx, $ny];
                }
            }

            if ($count > 800) {
                $boxes[] = [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1, $count];
            }
        }
    }

    usort($boxes, fn ($a, $b) => $a[1] <=> $b[1] ?: $a[0] <=> $b[0]);

    echo $key.PHP_EOL;

    foreach ($boxes as $index => $box) {
        [$x, $y, $w, $h] = $box;
        echo "  {$index} x={$x} y={$y} w={$w} h={$h}".PHP_EOL;
        $crop = imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        imagejpeg($crop, $out.'/'.$key.'-'.$index.'.jpg', 90);
        imagedestroy($crop);
    }

    imagedestroy($image);
}
