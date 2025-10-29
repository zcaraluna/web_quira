#!/usr/bin/env python3
"""
Script para limpiar archivos temporales creados durante el desarrollo
"""

import os
import shutil
from pathlib import Path

def limpiar_archivos_temporales():
    """Limpiar archivos temporales"""
    
    print("=" * 60)
    print("    LIMPIEZA DE ARCHIVOS TEMPORALES")
    print("=" * 60)
    
    # Archivos y directorios a eliminar
    archivos_a_eliminar = [
        'ZKTecoBridge.exe_extracted',
        'test_improved_bridge.py',
        'diagnostico_conexion.py',
        'test_web_connection.html',
        'limpiar_archivos_temporales.py',
        'pyinstxtractor.py',
        'decompile_script.py',
        'analyze_binary.py'
    ]
    
    archivos_eliminados = 0
    
    for archivo in archivos_a_eliminar:
        try:
            if os.path.exists(archivo):
                if os.path.isdir(archivo):
                    shutil.rmtree(archivo)
                    print(f"[OK] Directorio eliminado: {archivo}")
                else:
                    os.remove(archivo)
                    print(f"[OK] Archivo eliminado: {archivo}")
                archivos_eliminados += 1
            else:
                print(f"[INFO] No existe: {archivo}")
        except Exception as e:
            print(f"[ERROR] Error eliminando {archivo}: {e}")
    
    print(f"\n[RESULTADO] {archivos_eliminados} archivos/directorios eliminados")
    
    # Mostrar archivos importantes que se mantienen
    print("\n[ARCHIVOS IMPORTANTES MANTENIDOS]:")
    archivos_importantes = [
        'zkteco_bridge_improved.py',
        'start_improved_bridge.py',
        'zkteco_connector_v2.py',
        'agregar_postulante.php',
        'assets/js/zkteco-bridge.js'
    ]
    
    for archivo in archivos_importantes:
        if os.path.exists(archivo):
            print(f"  ✅ {archivo}")
        else:
            print(f"  ❌ {archivo} (NO ENCONTRADO)")
    
    print("\n" + "=" * 60)
    print("    LIMPIEZA COMPLETADA")
    print("=" * 60)

if __name__ == "__main__":
    limpiar_archivos_temporales()
