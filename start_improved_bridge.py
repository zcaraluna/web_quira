#!/usr/bin/env python3
"""
Script de inicio para el ZKTeco Bridge Mejorado
Sistema QUIRA - Versión Web
"""

import os
import sys
import subprocess
import time
import signal
import logging
from pathlib import Path

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('bridge_improved_startup.log'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

class ZKTecoBridgeImprovedManager:
    def __init__(self):
        self.bridge_process = None
        self.bridge_script = Path(__file__).parent / 'zkteco_bridge_improved.py'
        self.pid_file = Path(__file__).parent / 'zkteco_bridge_improved.pid'
        
    def check_dependencies(self):
        """Verificar que las dependencias estén instaladas"""
        logger.info("Verificando dependencias...")
        
        required_packages = [
            'fastapi',
            'uvicorn',
            'zk'
        ]
        
        missing_packages = []
        
        for package in required_packages:
            try:
                __import__(package)
                logger.info(f"[OK] {package} está instalado")
            except ImportError:
                missing_packages.append(package)
                logger.error(f"[ERROR] {package} no está instalado")
        
        if missing_packages:
            logger.error(f"Faltan las siguientes dependencias: {', '.join(missing_packages)}")
            logger.info("Instale las dependencias con: pip install " + " ".join(missing_packages))
            return False
        
        logger.info("[OK] Todas las dependencias están instaladas")
        return True
    
    def start_bridge(self):
        """Iniciar el bridge ZKTeco mejorado"""
        if not self.check_dependencies():
            return False
        
        if self.is_running():
            logger.warning("El bridge ZKTeco mejorado ya está ejecutándose")
            return True
        
        logger.info("Iniciando ZKTeco Bridge Mejorado...")
        
        try:
            # Iniciar el proceso del bridge
            self.bridge_process = subprocess.Popen([
                sys.executable, str(self.bridge_script)
            ], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            
            # Guardar PID
            with open(self.pid_file, 'w') as f:
                f.write(str(self.bridge_process.pid))
            
            # Esperar un momento para verificar que se inició correctamente
            time.sleep(2)
            
            if self.bridge_process.poll() is None:
                logger.info(f"[OK] ZKTeco Bridge Mejorado iniciado exitosamente (PID: {self.bridge_process.pid})")
                logger.info("[INFO] Bridge disponible en: http://0.0.0.0:8001")
                logger.info("[INFO] WebSocket disponible en: ws://0.0.0.0:8001/ws/zkteco")
                logger.info("[INFO] Logs guardados en: zkteco_bridge.log")
                return True
            else:
                logger.error("[ERROR] El bridge mejorado no se pudo iniciar correctamente")
                return False
                
        except Exception as e:
            logger.error(f"[ERROR] Error al iniciar el bridge mejorado: {e}")
            return False
    
    def stop_bridge(self):
        """Detener el bridge ZKTeco mejorado"""
        logger.info("Deteniendo ZKTeco Bridge Mejorado...")
        
        try:
            if self.pid_file.exists():
                with open(self.pid_file, 'r') as f:
                    pid = int(f.read().strip())
                
                # Intentar terminar el proceso
                try:
                    os.kill(pid, signal.SIGTERM)
                    time.sleep(2)
                    
                    # Verificar si se detuvo
                    try:
                        os.kill(pid, 0)  # No hace nada si el proceso existe
                        # Si llegamos aquí, el proceso aún existe
                        logger.warning("Proceso no se detuvo con SIGTERM, usando SIGKILL")
                        os.kill(pid, signal.SIGKILL)
                    except ProcessLookupError:
                        # El proceso ya no existe
                        pass
                    
                    logger.info("[OK] ZKTeco Bridge Mejorado detenido exitosamente")
                    
                except ProcessLookupError:
                    logger.warning("El proceso ya no existe")
                
                # Eliminar archivo PID
                self.pid_file.unlink()
                
            else:
                logger.warning("No se encontró archivo PID")
                
        except Exception as e:
            logger.error(f"[ERROR] Error al detener el bridge mejorado: {e}")
    
    def restart_bridge(self):
        """Reiniciar el bridge ZKTeco mejorado"""
        logger.info("Reiniciando ZKTeco Bridge Mejorado...")
        self.stop_bridge()
        time.sleep(1)
        return self.start_bridge()
    
    def is_running(self):
        """Verificar si el bridge mejorado está ejecutándose"""
        if not self.pid_file.exists():
            return False
        
        try:
            with open(self.pid_file, 'r') as f:
                pid = int(f.read().strip())
            
            # Verificar si el proceso existe usando subprocess en Windows
            import subprocess
            try:
                # En Windows, usar tasklist para verificar si el proceso existe
                result = subprocess.run(['tasklist', '/FI', f'PID eq {pid}'], 
                                      capture_output=True, text=True, 
                                      creationflags=subprocess.CREATE_NO_WINDOW)
                return str(pid) in result.stdout
            except:
                # Fallback: intentar con os.kill
                try:
                    os.kill(pid, 0)
                    return True
                except (ProcessLookupError, OSError):
                    return False
            
        except (FileNotFoundError, ProcessLookupError, ValueError, OSError):
            # El archivo no existe, el proceso no existe, o el PID es inválido
            if self.pid_file.exists():
                self.pid_file.unlink()
            return False
    
    def get_status(self):
        """Obtener estado del bridge mejorado"""
        if self.is_running():
            try:
                with open(self.pid_file, 'r') as f:
                    pid = int(f.read().strip())
                return {
                    'running': True,
                    'pid': pid,
                    'status': 'active',
                    'version': '2.0.0 - Mejorado'
                }
            except:
                return {
                    'running': False,
                    'status': 'error',
                    'version': '2.0.0 - Mejorado'
                }
        else:
            return {
                'running': False,
                'status': 'stopped',
                'version': '2.0.0 - Mejorado'
            }

def main():
    """Función principal"""
    manager = ZKTecoBridgeImprovedManager()
    
    if len(sys.argv) < 2:
        print("Uso: python start_improved_bridge.py [start|stop|restart|status]")
        sys.exit(1)
    
    command = sys.argv[1].lower()
    
    if command == 'start':
        if manager.start_bridge():
            print("[OK] Bridge mejorado iniciado exitosamente")
            sys.exit(0)
        else:
            print("[ERROR] Error al iniciar el bridge mejorado")
            sys.exit(1)
    
    elif command == 'stop':
        manager.stop_bridge()
        print("[OK] Bridge mejorado detenido")
        sys.exit(0)
    
    elif command == 'restart':
        if manager.restart_bridge():
            print("[OK] Bridge mejorado reiniciado exitosamente")
            sys.exit(0)
        else:
            print("[ERROR] Error al reiniciar el bridge mejorado")
            sys.exit(1)
    
    elif command == 'status':
        status = manager.get_status()
        if status['running']:
            print(f"[OK] Bridge mejorado ejecutándose (PID: {status['pid']}) - {status['version']}")
        else:
            print(f"[ERROR] Bridge mejorado no está ejecutándose - {status['version']}")
        sys.exit(0)
    
    else:
        print("Comando no reconocido. Use: start, stop, restart, o status")
        sys.exit(1)

if __name__ == "__main__":
    main()
