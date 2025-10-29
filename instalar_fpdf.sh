#!/bin/bash

# Script para instalar FPDF en el VPS Ubuntu
# Ejecutar como: sudo bash instalar_fpdf.sh

echo "Instalando FPDF para el sistema de exportación de postulantes..."

# Crear directorio para FPDF
mkdir -p /var/www/html/web_quira/fpdf

# Descargar FPDF
cd /tmp
wget http://www.fpdf.org/en/dl.php?v=185&f=zip -O fpdf.zip

# Instalar unzip si no está instalado
apt-get update
apt-get install -y unzip

# Extraer FPDF
unzip fpdf.zip
cp -r fpdf/* /var/www/html/web_quira/fpdf/

# Establecer permisos correctos
chown -R www-data:www-data /var/www/html/web_quira/fpdf/
chmod -R 755 /var/www/html/web_quira/fpdf/

# Limpiar archivos temporales
rm -rf /tmp/fpdf*
rm -f /tmp/fpdf.zip

echo "FPDF instalado correctamente en /var/www/html/web_quira/fpdf/"
echo "La funcionalidad de exportación ya está lista para usar."
