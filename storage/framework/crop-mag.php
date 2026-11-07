<?php

$path = 'C:/Users/Melody Huerta/.cursor/projects/c-wamp64-www-hopsurigaov2/assets/c__Users_Melody_Huerta_AppData_Roaming_Cursor_User_workspaceStorage_a2c0ad593885f992d3bfd55d3d1fdb94_images_image-d1332496-0755-4c2b-9bb0-328b6f4ccbbb.jpg';
$image = imagecreatefromjpeg($path);
$crop = imagecrop($image, ['x' => 708, 'y' => 14, 'width' => 214, 'height' => 378]);
imagejpeg($crop, __DIR__.'/spots/magkukuob-tight.jpg', 92);
echo imagesx($crop).'x'.imagesy($crop);
