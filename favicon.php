<?php
/**
 * Generador de favicon dinámico para el Sistema Quira
 * Este archivo genera un favicon a partir de la imagen quiraXXXL.png
 */

// Configurar headers para imagen
header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000'); // Cache por 1 año

// Ruta de la imagen original
$logo_path = 'assets/media/various/quiraXXXL.png';

// Verificar si la imagen existe
if (!file_exists($logo_path)) {
    // Si no existe, crear un favicon simple
    $image = imagecreate(32, 32);
    $bg = imagecolorallocate($image, 102, 126, 234); // Color azul del tema
    $text_color = imagecolorallocate($image, 255, 255, 255);
    
    // Dibujar "Q" en el centro
    imagestring($image, 5, 8, 8, 'Q', $text_color);
    
    imagepng($image);
    imagedestroy($image);
    exit;
}

// Cargar la imagen original
$original = imagecreatefrompng($logo_path);
if (!$original) {
    // Fallback si no se puede cargar
    $image = imagecreate(32, 32);
    $bg = imagecolorallocate($image, 102, 126, 234);
    $text_color = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 5, 8, 8, 'Q', $text_color);
    imagepng($image);
    imagedestroy($image);
    exit;
}

// Obtener dimensiones originales
$original_width = imagesx($original);
$original_height = imagesy($original);

// Crear nueva imagen de 32x32 (tamaño estándar de favicon)
$favicon = imagecreatetruecolor(32, 32);

// Hacer fondo transparente
imagealphablending($favicon, false);
imagesavealpha($favicon, true);
$transparent = imagecolorallocatealpha($favicon, 0, 0, 0, 127);
imagefill($favicon, 0, 0, $transparent);

// Redimensionar la imagen manteniendo proporciones
imagecopyresampled($favicon, $original, 0, 0, 0, 0, 32, 32, $original_width, $original_height);

// Output del favicon
imagepng($favicon);

// Limpiar memoria
imagedestroy($original);
imagedestroy($favicon);
?>

