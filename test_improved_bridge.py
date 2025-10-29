#!/usr/bin/env python3
"""
Script de prueba para el ZKTeco Bridge Mejorado
Verifica que el problema de la limitación de 100 usuarios esté solucionado
"""

import requests
import json
import time

def test_bridge():
    """Probar el bridge mejorado"""
    base_url = "http://localhost:8001"
    
    print("=" * 60)
    print("    PRUEBA DEL ZKTECO BRIDGE MEJORADO")
    print("    Verificando solución de limitación de usuarios")
    print("=" * 60)
    
    try:
        # 1. Verificar estado del bridge
        print("\n1. Verificando estado del bridge...")
        response = requests.get(f"{base_url}/status")
        if response.status_code == 200:
            status = response.json()
            print(f"   [OK] Bridge conectado: {status['connected']}")
            print(f"   [INFO] IP: {status['ip']}")
            print(f"   [INFO] Puerto: {status['port']}")
        else:
            print(f"   [ERROR] Error obteniendo estado: {response.status_code}")
            return False
        
        # 2. Conectar al dispositivo
        print("\n2. Conectando al dispositivo ZKTeco...")
        response = requests.post(f"{base_url}/connect")
        if response.status_code == 200:
            result = response.json()
            if result['success']:
                print(f"   [OK] {result['message']}")
            else:
                print(f"   [ERROR] {result['message']}")
                return False
        else:
            print(f"   [ERROR] Error conectando: {response.status_code}")
            return False
        
        # 3. Obtener información del dispositivo
        print("\n3. Obteniendo información del dispositivo...")
        response = requests.get(f"{base_url}/device-info")
        if response.status_code == 200:
            result = response.json()
            if result['success']:
                device_info = result['device_info']
                print(f"   [OK] Dispositivo: {device_info.get('device_name', 'N/A')}")
                print(f"   [INFO] Firmware: {device_info.get('firmware_version', 'N/A')}")
                print(f"   [INFO] Serial: {device_info.get('serial_number', 'N/A')}")
            else:
                print(f"   [ERROR] {result['message']}")
        else:
            print(f"   [ERROR] Error obteniendo información: {response.status_code}")
        
        # 4. Obtener usuarios (PRUEBA PRINCIPAL)
        print("\n4. Obteniendo usuarios del dispositivo...")
        print("   [INFO] Esta es la prueba principal para verificar la limitación...")
        
        response = requests.get(f"{base_url}/users")
        if response.status_code == 200:
            result = response.json()
            if result['success']:
                users = result['users']
                total_count = result['total_count']
                retrieved_count = result['retrieved_count']
                incomplete = result['incomplete']
                
                print(f"   [RESULTADO] Total en dispositivo: {total_count}")
                print(f"   [RESULTADO] Usuarios obtenidos: {retrieved_count}")
                print(f"   [RESULTADO] Lista incompleta: {incomplete}")
                
                if retrieved_count >= total_count:
                    print(f"   [OK] ¡ÉXITO! Se obtuvieron todos los usuarios ({retrieved_count}/{total_count})")
                else:
                    print(f"   [WARN] Lista incompleta: {retrieved_count}/{total_count} usuarios")
                
                # Mostrar los últimos usuarios para verificar
                if users:
                    print(f"\n   [INFO] Últimos 5 usuarios obtenidos:")
                    last_users = users[-5:] if len(users) >= 5 else users
                    for user in last_users:
                        print(f"      - UID {user['uid']}: {user['name']}")
                
                # Verificar si hay usuarios con nombre asignado
                users_with_names = [u for u in users if u.get('name') and not u.get('name', '').startswith('NN-')]
                print(f"\n   [INFO] Usuarios con nombre asignado: {len(users_with_names)}")
                
                if users_with_names:
                    print(f"   [INFO] Último usuario con nombre: UID {users_with_names[-1]['uid']} - {users_with_names[-1]['name']}")
                
            else:
                print(f"   [ERROR] {result['message']}")
                return False
        else:
            print(f"   [ERROR] Error obteniendo usuarios: {response.status_code}")
            return False
        
        # 5. Desconectar
        print("\n5. Desconectando del dispositivo...")
        response = requests.post(f"{base_url}/disconnect")
        if response.status_code == 200:
            result = response.json()
            if result['success']:
                print(f"   [OK] {result['message']}")
            else:
                print(f"   [WARN] {result['message']}")
        
        print("\n" + "=" * 60)
        print("    PRUEBA COMPLETADA")
        print("=" * 60)
        
        return True
        
    except requests.exceptions.ConnectionError:
        print("   [ERROR] No se puede conectar al bridge. ¿Está ejecutándose?")
        return False
    except Exception as e:
        print(f"   [ERROR] Error durante la prueba: {e}")
        return False

if __name__ == "__main__":
    success = test_bridge()
    if success:
        print("\n[OK] Prueba completada exitosamente")
    else:
        print("\n[ERROR] La prueba falló")
