#!/bin/bash

echo "Instalando PhpSpreadsheet para generar archivos Excel reales..."
echo

# Verificar si composer está instalado
if ! command -v composer &> /dev/null; then
    echo "ERROR: Composer no está instalado."
    echo "Por favor instala Composer ejecutando:"
    echo "curl -sS https://getcomposer.org/installer | php"
    echo "sudo mv composer.phar /usr/local/bin/composer"
    echo "sudo chmod +x /usr/local/bin/composer"
    echo
    exit 1
fi

echo "Composer encontrado. Instalando dependencias..."
echo

# Instalar PhpSpreadsheet
composer install --no-dev --optimize-autoloader

if [ $? -eq 0 ]; then
    echo
    echo "¡Instalación completada exitosamente!"
    echo "PhpSpreadsheet está listo para generar archivos Excel reales."
    echo
    echo "Para verificar la instalación:"
    echo "ls -la vendor/autoload.php"
else
    echo
    echo "ERROR: No se pudo instalar PhpSpreadsheet."
    echo "Verifica tu conexión a internet y permisos de escritura."
    echo "Asegúrate de que el directorio tenga permisos de escritura:"
    echo "sudo chown -R www-data:www-data /ruta/a/tu/proyecto"
    echo "sudo chmod -R 755 /ruta/a/tu/proyecto"
fi

echo
