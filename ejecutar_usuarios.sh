#!/bin/bash

echo "========================================"
echo "    SCRIPT PARA INSERTAR USUARIOS"
echo "    Sistema QUIRA - Servidor Remoto"
echo "    Ubuntu/Linux"
echo "========================================"
echo

echo "Conectando al servidor PostgreSQL..."
echo "Host: 64.176.18.16"
echo "Database: sistema_postulantes"
echo "Usuario: postgres"
echo

# Ejecutar el script SQL
psql -h 64.176.18.16 -U postgres -d sistema_postulantes -f insert_usuarios.sql

echo
echo "========================================"
echo "    PROCESO COMPLETADO"
echo "========================================"
