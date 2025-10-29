#!/usr/bin/env python3
"""
Script de diagnóstico para verificar la conexión entre la página web y el bridge
"""

import requests
import json
import time

def diagnosticar_conexion():
    """Diagnosticar la conexión entre la página web y el bridge"""
    
    print("=" * 60)
    print("    DIAGNÓSTICO DE CONEXIÓN - PÁGINA WEB Y BRIDGE")
    print("=" * 60)
    
    base_url = "http://localhost:8001"
    
    try:
        # 1. Verificar que el bridge esté ejecutándose
        print("\n1. Verificando que el bridge esté ejecutándose...")
        try:
            response = requests.get(f"{base_url}/", timeout=5)
            if response.status_code == 200:
                data = response.json()
                print(f"   [OK] Bridge ejecutándose - Versión: {data.get('version', 'N/A')}")
                print(f"   [INFO] Estado: {data.get('status', 'N/A')}")
                print(f"   [INFO] Conectado: {data.get('connected', False)}")
            else:
                print(f"   [ERROR] Bridge respondió con código: {response.status_code}")
                return False
        except requests.exceptions.ConnectionError:
            print("   [ERROR] No se puede conectar al bridge en puerto 8001")
            print("   [INFO] Verificar que el bridge esté ejecutándose")
            return False
        except Exception as e:
            print(f"   [ERROR] Error conectando al bridge: {e}")
            return False
        
        # 2. Verificar endpoints específicos
        print("\n2. Verificando endpoints específicos...")
        
        endpoints = [
            ("/status", "Estado del bridge"),
            ("/device-info", "Información del dispositivo"),
            ("/users", "Lista de usuarios")
        ]
        
        for endpoint, descripcion in endpoints:
            try:
                response = requests.get(f"{base_url}{endpoint}", timeout=5)
                if response.status_code == 200:
                    print(f"   [OK] {descripcion}: Funcionando")
                else:
                    print(f"   [WARN] {descripcion}: Código {response.status_code}")
            except Exception as e:
                print(f"   [ERROR] {descripcion}: {e}")
        
        # 3. Verificar conexión WebSocket
        print("\n3. Verificando configuración WebSocket...")
        ws_url = "ws://localhost:8001/ws/zkteco"
        print(f"   [INFO] URL WebSocket: {ws_url}")
        print("   [INFO] Para probar WebSocket, abrir la consola del navegador y ejecutar:")
        print(f"   [INFO] const ws = new WebSocket('{ws_url}');")
        print("   [INFO] ws.onopen = () => console.log('WebSocket conectado');")
        print("   [INFO] ws.onerror = (e) => console.error('Error WebSocket:', e);")
        
        # 4. Verificar configuración de la página web
        print("\n4. Verificando configuración de la página web...")
        print("   [INFO] La página web debe estar configurada para usar:")
        print("   [INFO] - HTTP URL: http://localhost:8001")
        print("   [INFO] - WebSocket URL: ws://localhost:8001/ws/zkteco")
        
        # 5. Probar conexión al dispositivo
        print("\n5. Probando conexión al dispositivo...")
        try:
            # Conectar al dispositivo
            response = requests.post(f"{base_url}/connect", timeout=10)
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    print("   [OK] Conexión al dispositivo exitosa")
                    
                    # Obtener información del dispositivo
                    response = requests.get(f"{base_url}/device-info", timeout=5)
                    if response.status_code == 200:
                        device_info = response.json()
                        if device_info.get('success'):
                            info = device_info.get('device_info', {})
                            print(f"   [INFO] Dispositivo: {info.get('device_name', 'N/A')}")
                            print(f"   [INFO] Firmware: {info.get('firmware_version', 'N/A')}")
                            print(f"   [INFO] Serial: {info.get('serial_number', 'N/A')}")
                        else:
                            print(f"   [WARN] No se pudo obtener información del dispositivo: {device_info.get('message', 'N/A')}")
                    
                    # Obtener usuarios
                    response = requests.get(f"{base_url}/users", timeout=10)
                    if response.status_code == 200:
                        users_result = response.json()
                        if users_result.get('success'):
                            users = users_result.get('users', [])
                            total = users_result.get('total_count', 0)
                            retrieved = users_result.get('retrieved_count', 0)
                            print(f"   [OK] Usuarios obtenidos: {retrieved}/{total}")
                            
                            if users:
                                print(f"   [INFO] Último usuario: UID {users[-1]['uid']} - {users[-1]['name']}")
                        else:
                            print(f"   [WARN] No se pudieron obtener usuarios: {users_result.get('message', 'N/A')}")
                    
                    # Desconectar
                    requests.post(f"{base_url}/disconnect", timeout=5)
                    
                else:
                    print(f"   [ERROR] Error conectando al dispositivo: {result.get('message', 'N/A')}")
            else:
                print(f"   [ERROR] Error en conexión al dispositivo: {response.status_code}")
        except Exception as e:
            print(f"   [ERROR] Error probando conexión al dispositivo: {e}")
        
        print("\n" + "=" * 60)
        print("    DIAGNÓSTICO COMPLETADO")
        print("=" * 60)
        
        print("\n[PASOS PARA SOLUCIONAR PROBLEMAS EN LA PÁGINA WEB]:")
        print("1. Abrir la consola del navegador (F12)")
        print("2. Recargar la página web")
        print("3. Verificar si hay errores en la consola")
        print("4. Verificar que la página esté usando el puerto 8001")
        print("5. Si hay errores de CORS, verificar la configuración del bridge")
        
        return True
        
    except Exception as e:
        print(f"[ERROR] Error durante el diagnóstico: {e}")
        return False

if __name__ == "__main__":
    diagnosticar_conexion()
