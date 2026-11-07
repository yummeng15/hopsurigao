<?php

$source = imagecreatefromjpeg(__DIR__.'/spots/magkukuob-tight.jpg');
$width = imagesx($source);
$height = imagesy($source);
$targetWidth = 1100;
$targetHeight = (int) round($height * ($targetWidth / $width));
$canvas = imagecreatetruecolor($targetWidth, $targetHeight);
imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
$matches = glob(__DIR__.'/../app/public/packages/4/3*.jpg');
imagejpeg($canvas, $matches[0], 88);
echo $matches[0];
