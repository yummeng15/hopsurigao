<?php

$root = dirname(__DIR__, 2);
$spots = __DIR__.'/spots';
$packages = $root.'/storage/app/public/packages';

$magSource = 'C:/Users/Melody Huerta/.cursor/projects/c-wamp64-www-hopsurigaov2/assets/c__Users_Melody_Huerta_AppData_Roaming_Cursor_User_workspaceStorage_a2c0ad593885f992d3bfd55d3d1fdb94_images_image-d1332496-0755-4c2b-9bb0-328b6f4ccbbb.jpg';
$mag = imagecreatefromjpeg($magSource);
$magCrop = imagecrop($mag, ['x' => 698, 'y' => 24, 'width' => 248, 'height' => 318]);
imagejpeg($magCrop, $spots.'/magkukuob-tight.jpg', 92);
imagedestroy($mag);
imagedestroy($magCrop);

$sets = [
    2 => [
        $spots.'/boardwalk-1.jpg',
        $spots.'/boardwalk-3.jpg',
    ],
    3 => [
        $spots.'/grid-1.jpg',
    ],
    4 => [
        $spots.'/grid-2.jpg',
        $spots.'/grid-5.jpg',
        $spots.'/magkukuob-tight.jpg',
    ],
    5 => [
        $spots.'/pilar-2.jpg',
    ],
    6 => [
        $spots.'/pilar-1.jpg',
        $spots.'/boardwalk-2.jpg',
    ],
];

foreach ($sets as $id => $files) {
    $dir = $packages.'/'.$id;

    foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
        foreach (glob($dir.'/*.'.$extension) ?: [] as $old) {
            unlink($old);
        }
    }

    foreach ($files as $index => $file) {
        $source = imagecreatefromjpeg($file);
        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = 1400;
        $targetHeight = (int) round($height * ($targetWidth / $width));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $name = ($index + 1).bin2hex(random_bytes(16)).'.jpg';
        imagejpeg($canvas, $dir.'/'.$name, 88);
        imagedestroy($source);
        imagedestroy($canvas);
        echo $id.' '.$name.PHP_EOL;
    }
}
