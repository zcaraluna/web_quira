# Instalación de PhpSpreadsheet para Excel Real

## Pasos para instalar la librería en Ubuntu VPS

### 1. Instalar Composer (si no lo tienes)
```bash
# Descargar e instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verificar instalación
composer --version
```

### 2. Instalar PhpSpreadsheet
Ejecutar uno de estos comandos en la carpeta del proyecto:

**Opción A - Usando el script de instalación (Recomendado):**
```bash
chmod +x install_dependencies.sh
./install_dependencies.sh
```

**Opción B - Usando Composer directamente:**
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Verificar instalación
Después de la instalación, deberías ver:
- Una carpeta `vendor/` en el proyecto
- Un archivo `vendor/autoload.php`

### 4. Probar la exportación
- Ir a Postulantes en el dashboard
- Hacer clic en "Exportar"
- Seleccionar "Excel (.xlsx)"
- El archivo se descargará sin advertencias

## Características del nuevo Excel

✅ **Archivo Excel binario real (.xlsx)**
✅ **Sin advertencias de seguridad**
✅ **Encabezados con formato profesional**
✅ **Columnas autoajustadas**
✅ **Compatible con Excel, LibreOffice, Google Sheets**
✅ **Método de respaldo si la librería no está disponible**

## Solución de problemas

Si la instalación falla:
1. Verificar que Composer esté instalado: `composer --version`
2. Verificar conexión a internet
3. Verificar permisos de escritura en la carpeta del proyecto
4. El sistema usará el método de respaldo (HTML) si PhpSpreadsheet no está disponible
