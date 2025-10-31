-- Script para corregir unidades con comillas faltantes en preinscriptos
-- Ejecutar: psql -h 64.176.18.16 -U postgres -d sistema_postulantes -f corregir_unidades_preinscriptos.sql

-- Actualizar unidades que tienen "Gral. JOSE E. DÍAZ sin la comilla final
UPDATE preinscriptos 
SET unidad = unidad || '"'
WHERE unidad LIKE '%Gral. JOSE E. DÍAZ' 
  AND unidad NOT LIKE '%Gral. JOSE E. DÍAZ"';

-- Verificar el resultado
SELECT ci, nombre_completo, unidad 
FROM preinscriptos 
WHERE unidad LIKE '%Gral. JOSE E. DÍAZ%'
ORDER BY ci
LIMIT 10;

