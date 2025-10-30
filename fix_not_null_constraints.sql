-- Script para permitir NULL en nombre y apellido (temporal, hasta eliminar columnas)
-- Ejecutar este script para solucionar el error de NOT NULL

ALTER TABLE postulantes ALTER COLUMN nombre DROP NOT NULL;
ALTER TABLE postulantes ALTER COLUMN apellido DROP NOT NULL;

