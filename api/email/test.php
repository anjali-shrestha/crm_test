<?php
header("Content-type: image/gif");
$img = imagecreatefromgif('blank_pixel.gif');
imagegif($img);
?>