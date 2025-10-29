-- Script para actualizar contraseñas de usuarios agregados
-- Hash correcto de "123456": $2y$10$dAOyx6lZUACah9EFXUORP.llDtU/apNpWZf9gxBfZ0XRkzQWAumTa
-- Fecha: $(date)

-- Actualizar contraseña de todos los usuarios con rol USUARIO que tienen primer_inicio = true
UPDATE usuarios 
SET contrasena = '$2y$10$dAOyx6lZUACah9EFXUORP.llDtU/apNpWZf9gxBfZ0XRkzQWAumTa'
WHERE rol = 'USUARIO' AND primer_inicio = true;

-- Verificar cuántos usuarios se actualizaron
SELECT COUNT(*) as usuarios_actualizados 
FROM usuarios 
WHERE rol = 'USUARIO' 
AND primer_inicio = true 
AND contrasena = '$2y$10$dAOyx6lZUACah9EFXUORP.llDtU/apNpWZf9gxBfZ0XRkzQWAumTa';

-- Mostrar lista de usuarios actualizados
SELECT id, usuario, nombre, apellido, rol, primer_inicio, fecha_creacion
FROM usuarios 
WHERE rol = 'USUARIO' 
AND primer_inicio = true 
AND contrasena = '$2y$10$dAOyx6lZUACah9EFXUORP.llDtU/apNpWZf9gxBfZ0XRkzQWAumTa'
ORDER BY id;
