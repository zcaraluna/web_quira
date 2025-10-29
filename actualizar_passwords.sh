#!/bin/bash

echo "========================================"
echo "    ACTUALIZAR CONTRASEÑAS DE USUARIOS"
echo "    Sistema QUIRA - Servidor Remoto"
echo "    Ubuntu/Linux"
echo "========================================"
echo

echo "Actualizando contraseñas de usuarios con rol USUARIO..."
echo "Hash: \$2y\$10\$dAOyx6lZUACah9EFXUORP.llDtU/apNpWZf9gxBfZ0XRkzQWAumTa"
echo

# Ejecutar el script SQL
psql -h 64.176.18.16 -U postgres -d sistema_postulantes -f actualizar_passwords.sql

echo
echo "========================================"
echo "    PROCESO COMPLETADO"
echo "========================================"
