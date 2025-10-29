# Instalación de PhpSpreadsheet para Excel Real

## Pasos para instalar la librería

### 1. Instalar Composer (si no lo tienes)
- Descargar desde: https://getcomposer.org/download/
- Instalar siguiendo las instrucciones del sitio

### 2. Instalar PhpSpreadsheet
Ejecutar uno de estos comandos en la carpeta del proyecto:

**Opción A - Usando el archivo .bat (Windows):**
```
install_dependencies.bat
```

**Opción B - Usando Composer directamente:**
```
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
