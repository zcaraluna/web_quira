#!/usr/bin/env python3
"""
ZKTeco Bridge Mejorado - Sistema QUIRA
Versión que maneja la limitación de 100 usuarios
"""

import asyncio
import json
import logging
import os
import sys
from datetime import datetime
from typing import Dict, List, Any, Optional

from fastapi import FastAPI, WebSocket, WebSocketDisconnect, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import uvicorn

# Agregar el directorio actual al path para importar zkteco_connector_v2
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from zkteco_connector_v2 import ZKTecoK40V2

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('zkteco_bridge.log'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

class ZKTecoBridgeImproved:
    def __init__(self, ip_address: str = "192.168.100.201", port: int = 4370):
        self.ip_address = ip_address
        self.port = port
        self.device = ZKTecoK40V2(ip_address, port)
        self.connected = False
        self.websocket_clients = set()
        
    async def connect_to_device(self) -> Dict[str, Any]:
        """Conectar al dispositivo ZKTeco"""
        try:
            logger.info(f"Conectando a ZKTeco en {self.ip_address}:{self.port}")
            
            if self.device.connect():
                self.connected = True
                logger.info("[OK] Conectado exitosamente a ZKTeco")
                
                # Obtener información del dispositivo
                device_info = self.device.get_device_info()
                
                return {
                    "success": True,
                    "message": "Conectado exitosamente a ZKTeco",
                    "device_info": device_info,
                    "ip": self.ip_address,
                    "port": self.port
                }
            else:
                self.connected = False
                logger.error("[ERROR] Error conectando a ZKTeco")
                return {
                    "success": False,
                    "message": "Error conectando a ZKTeco",
                    "ip": self.ip_address,
                    "port": self.port
                }
                
        except Exception as e:
            self.connected = False
            logger.error(f"[ERROR] Error conectando a ZKTeco: {e}")
            return {
                "success": False,
                "message": f"Error conectando a ZKTeco: {str(e)}",
                "ip": self.ip_address,
                "port": self.port
            }
    
    async def disconnect_device(self) -> Dict[str, Any]:
        """Desconectar del dispositivo ZKTeco"""
        try:
            if self.device.disconnect():
                self.connected = False
                logger.info("[OK] Desconectado del dispositivo ZKTeco")
                return {
                    "success": True,
                    "message": "Desconectado exitosamente"
                }
            else:
                return {
                    "success": False,
                    "message": "Error al desconectar"
                }
        except Exception as e:
            logger.error(f"[ERROR] Error desconectando: {e}")
            return {
                "success": False,
                "message": f"Error desconectando: {str(e)}"
            }
    
    async def get_users_improved(self) -> Dict[str, Any]:
        """Obtener usuarios con manejo mejorado de la limitación"""
        try:
            if not self.connected:
                return {
                    "success": False,
                    "message": "No hay conexión activa con el dispositivo",
                    "users": [],
                    "total_count": 0,
                    "retrieved_count": 0
                }
            
            logger.info("Obteniendo usuarios del dispositivo...")
            
            # Obtener conteo total
            total_count = self.device.get_user_count()
            logger.info(f"Total de usuarios en dispositivo: {total_count}")
            
            # Obtener usuarios con el método mejorado
            users = self.device.get_user_list(count=10000)  # Aumentar el límite
            
            retrieved_count = len(users) if users else 0
            logger.info(f"Usuarios obtenidos: {retrieved_count} de {total_count}")
            
            # Si hay una discrepancia, intentar obtener el último usuario específicamente
            if retrieved_count < total_count:
                logger.warning(f"[WARN] Lista incompleta: {retrieved_count} de {total_count} usuarios")
                
                # Intentar obtener el último usuario usando el método específico
                last_user = self.device.get_last_user()
                if last_user:
                    logger.info(f"Último usuario obtenido: UID {last_user['uid']}, Nombre: {last_user['name']}")
                    
                    # Verificar si el último usuario ya está en la lista
                    last_uid = last_user['uid']
                    if not any(user['uid'] == last_uid for user in users):
                        users.append(last_user)
                        retrieved_count = len(users)
                        logger.info(f"Agregado último usuario a la lista. Total ahora: {retrieved_count}")
            
            return {
                "success": True,
                "message": f"Usuarios obtenidos exitosamente",
                "users": users,
                "total_count": total_count,
                "retrieved_count": retrieved_count,
                "incomplete": retrieved_count < total_count
            }
            
        except Exception as e:
            logger.error(f"[ERROR] Error obteniendo usuarios: {e}")
            return {
                "success": False,
                "message": f"Error obteniendo usuarios: {str(e)}",
                "users": [],
                "total_count": 0,
                "retrieved_count": 0
            }
    
    async def get_device_info(self) -> Dict[str, Any]:
        """Obtener información del dispositivo"""
        try:
            if not self.connected:
                return {
                    "success": False,
                    "message": "No hay conexión activa con el dispositivo"
                }
            
            device_info = self.device.get_device_info()
            return {
                "success": True,
                "message": "Información del dispositivo obtenida",
                "device_info": device_info
            }
            
        except Exception as e:
            logger.error(f"[ERROR] Error obteniendo información del dispositivo: {e}")
            return {
                "success": False,
                "message": f"Error obteniendo información: {str(e)}"
            }
    
    async def add_user(self, uid: int, name: str, privilege: int = 0) -> Dict[str, Any]:
        """Agregar usuario al dispositivo"""
        try:
            if not self.connected:
                return {
                    "success": False,
                    "message": "No hay conexión activa con el dispositivo"
                }
            
            logger.info(f"Agregando usuario: UID {uid}, Nombre: {name}")
            
            if self.device.add_user(uid, name, privilege):
                logger.info(f"[OK] Usuario agregado exitosamente: UID {uid}")
                return {
                    "success": True,
                    "message": f"Usuario {name} agregado exitosamente con UID {uid}",
                    "uid": uid,
                    "name": name
                }
            else:
                logger.error(f"[ERROR] Error agregando usuario: UID {uid}")
                return {
                    "success": False,
                    "message": f"Error agregando usuario {name} con UID {uid}"
                }
                
        except Exception as e:
            logger.error(f"[ERROR] Error agregando usuario: {e}")
            return {
                "success": False,
                "message": f"Error agregando usuario: {str(e)}"
            }
    
    async def delete_user(self, uid: int) -> Dict[str, Any]:
        """Eliminar usuario del dispositivo"""
        try:
            if not self.connected:
                return {
                    "success": False,
                    "message": "No hay conexión activa con el dispositivo"
                }
            
            logger.info(f"Eliminando usuario: UID {uid}")
            
            if self.device.delete_user(uid):
                logger.info(f"[OK] Usuario eliminado exitosamente: UID {uid}")
                return {
                    "success": True,
                    "message": f"Usuario con UID {uid} eliminado exitosamente",
                    "uid": uid
                }
            else:
                logger.error(f"[ERROR] Error eliminando usuario: UID {uid}")
                return {
                    "success": False,
                    "message": f"Error eliminando usuario con UID {uid}"
                }
                
        except Exception as e:
            logger.error(f"[ERROR] Error eliminando usuario: {e}")
            return {
                "success": False,
                "message": f"Error eliminando usuario: {str(e)}"
            }
    
    async def search_postulante_by_name(self, name: str) -> Dict[str, Any]:
        """Buscar postulante por nombre"""
        try:
            if not self.connected:
                return {
                    "success": False,
                    "message": "No hay conexión activa con el dispositivo",
                    "users": []
                }
            
            logger.info(f"Buscando postulante: {name}")
            
            # Obtener todos los usuarios
            users_result = await self.get_users_improved()
            if not users_result["success"]:
                return users_result
            
            users = users_result["users"]
            
            # Buscar usuarios que coincidan con el nombre
            matching_users = []
            for user in users:
                user_name = user.get('name', '').lower()
                if name.lower() in user_name:
                    matching_users.append(user)
            
            logger.info(f"Encontrados {len(matching_users)} usuarios que coinciden con '{name}'")
            
            return {
                "success": True,
                "message": f"Búsqueda completada",
                "users": matching_users,
                "search_term": name,
                "total_found": len(matching_users)
            }
            
        except Exception as e:
            logger.error(f"[ERROR] Error buscando postulante: {e}")
            return {
                "success": False,
                "message": f"Error buscando postulante: {str(e)}",
                "users": []
            }
    
    async def broadcast_status(self, message: str, data: Dict[str, Any] = None):
        """Enviar mensaje a todos los clientes WebSocket conectados"""
        if self.websocket_clients:
            message_data = {
                "type": "status",
                "message": message,
                "timestamp": datetime.now().isoformat(),
                "data": data or {}
            }
            
            disconnected_clients = set()
            for client in self.websocket_clients:
                try:
                    await client.send_text(json.dumps(message_data))
                except:
                    disconnected_clients.add(client)
            
            # Remover clientes desconectados
            self.websocket_clients -= disconnected_clients

# Crear instancia del bridge
bridge = ZKTecoBridgeImproved()

# Crear aplicación FastAPI
app = FastAPI(
    title="ZKTeco Bridge - Sistema QUIRA",
    description="Bridge de comunicación entre la aplicación web y dispositivos ZKTeco",
    version="2.0.0"
)

# Configurar CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
async def root():
    """Endpoint raíz"""
    return {
        "message": "ZKTeco Bridge - Sistema QUIRA",
        "version": "2.0.0",
        "status": "running",
        "connected": bridge.connected
    }

@app.get("/status")
async def get_status():
    """Obtener estado del bridge"""
    return {
        "connected": bridge.connected,
        "ip": bridge.ip_address,
        "port": bridge.port,
        "timestamp": datetime.now().isoformat()
    }

@app.post("/connect")
async def connect():
    """Conectar al dispositivo ZKTeco"""
    result = await bridge.connect_to_device()
    await bridge.broadcast_status("Estado de conexión actualizado", result)
    return result

@app.post("/disconnect")
async def disconnect():
    """Desconectar del dispositivo ZKTeco"""
    result = await bridge.disconnect_device()
    await bridge.broadcast_status("Estado de conexión actualizado", result)
    return result

@app.get("/device-info")
async def get_device_info():
    """Obtener información del dispositivo"""
    return await bridge.get_device_info()

@app.get("/users")
async def get_users():
    """Obtener lista de usuarios del dispositivo"""
    return await bridge.get_users_improved()

@app.post("/add-user")
async def add_user(data: Dict[str, Any]):
    """Agregar usuario al dispositivo"""
    uid = data.get("uid")
    name = data.get("name")
    privilege = data.get("privilege", 0)
    
    if not uid or not name:
        raise HTTPException(status_code=400, detail="UID y nombre son requeridos")
    
    result = await bridge.add_user(uid, name, privilege)
    await bridge.broadcast_status("Usuario agregado", result)
    return result

@app.delete("/delete-user/{uid}")
async def delete_user(uid: int):
    """Eliminar usuario del dispositivo"""
    result = await bridge.delete_user(uid)
    await bridge.broadcast_status("Usuario eliminado", result)
    return result

@app.get("/search")
async def search_postulante(name: str):
    """Buscar postulante por nombre"""
    return await bridge.search_postulante_by_name(name)

@app.websocket("/ws/zkteco")
async def websocket_endpoint(websocket: WebSocket):
    """WebSocket para comunicación en tiempo real"""
    await websocket.accept()
    bridge.websocket_clients.add(websocket)
    
    try:
        # Enviar estado inicial
        await websocket.send_text(json.dumps({
            "type": "connection",
            "message": "Conectado al ZKTeco Bridge",
            "connected": bridge.connected,
            "timestamp": datetime.now().isoformat()
        }))
        
        # Mantener conexión activa
        while True:
            try:
                data = await websocket.receive_text()
                # Procesar mensajes del cliente si es necesario
            except WebSocketDisconnect:
                break
                
    except Exception as e:
        logger.error(f"Error en WebSocket: {e}")
    finally:
        bridge.websocket_clients.discard(websocket)

if __name__ == "__main__":
    print("=" * 60)
    print("    ZKTECO BRIDGE MEJORADO - SISTEMA QUIRA")
    print("    Version 2.0.0 - Manejo mejorado de usuarios")
    print("=" * 60)
    print("Iniciando ZKTeco Bridge...")
    print("[INFO] Bridge disponible en: http://0.0.0.0:8001")
    print("[INFO] WebSocket disponible en: ws://0.0.0.0:8001/ws/zkteco")
    print("[INFO] Logs guardados en: zkteco_bridge.log")
    print("[WARN] Cierre esta ventana o presione CTRL+C para detener el servidor")
    print("=" * 60)
    
    uvicorn.run(app, host="0.0.0.0", port=8001, log_level="info")
