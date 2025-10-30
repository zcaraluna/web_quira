/**
 * Cliente JavaScript para comunicación con ZKTeco Bridge
 * Sistema QUIRA - Versión Web
 */

class ZKTecoBridge {
    constructor(config = {}) {
        this.config = {
            wsUrl: config.wsUrl || 'ws://localhost:8001/ws/zkteco',
            httpUrl: config.httpUrl || 'http://localhost:8001',
            reconnectInterval: config.reconnectInterval || 5000,
            maxReconnectAttempts: config.maxReconnectAttempts || 5,
            ...config
        };
        
        this.ws = null;
        this.connected = false;
        this.reconnectAttempts = 0;
        this.messageHandlers = {};
        this.connectionHandlers = {
            onConnect: [],
            onDisconnect: [],
            onError: []
        };
        
        // Estado del dispositivo
        this.deviceStatus = {
            connected: false,
            ip: null,
            port: null,
            last_connection: null,
            error: null
        };
    }
    
    /**
     * Conectar al bridge WebSocket
     */
    async connect() {
        return new Promise((resolve, reject) => {
            try {
                this.ws = new WebSocket(this.config.wsUrl);
                
                this.ws.onopen = () => {
                    this.connected = true;
                    this.reconnectAttempts = 0;
                    console.log('Conectado al ZKTeco Bridge');
                    this.triggerConnectionHandlers('onConnect');
                    resolve();
                };
                
                this.ws.onmessage = (event) => {
                    try {
                        console.log('WebSocket message received:', event.data);
                        
                        // Verificar si el mensaje es válido
                        if (!event.data || event.data.trim() === '') {
                            console.log('Mensaje WebSocket vacío, ignorando...');
                            return;
                        }
                        
                        const message = JSON.parse(event.data);
                        this.handleMessage(message);
                    } catch (error) {
                        console.error('Error parsing WebSocket message:', error);
                        console.error('Raw message data:', event.data);
                    }
                };
                
                this.ws.onclose = () => {
                    this.connected = false;
                    console.log('Desconectado del ZKTeco Bridge');
                    this.triggerConnectionHandlers('onDisconnect');
                    this.attemptReconnect();
                };
                
                this.ws.onerror = (error) => {
                    console.error('Error en WebSocket:', error);
                    this.triggerConnectionHandlers('onError', error);
                    reject(error);
                };
                
            } catch (error) {
                reject(error);
            }
        });
    }
    
    /**
     * Desconectar del bridge
     */
    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.connected = false;
    }
    
    /**
     * Intentar reconectar automáticamente
     */
    attemptReconnect() {
        if (this.reconnectAttempts < this.config.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Intentando reconectar... (${this.reconnectAttempts}/${this.config.maxReconnectAttempts})`);
            
            setTimeout(() => {
                this.connect().catch(error => {
                    console.error('Error en reconexión:', error);
                });
            }, this.config.reconnectInterval);
        } else {
            console.error('Máximo número de intentos de reconexión alcanzado');
        }
    }
    
    /**
     * Manejar mensajes recibidos del bridge
     */
    handleMessage(message) {
        console.log('Handling message:', message);
        
        // Verificar que el mensaje tenga la estructura esperada
        if (!message || typeof message !== 'object') {
            console.error('Mensaje inválido recibido:', message);
            return;
        }
        
        const { type, data, ...otherProps } = message;
        
        // Para mensajes que no tienen 'data' pero tienen otras propiedades, usar el mensaje completo
        const messageData = data || otherProps;
        
        // Actualizar estado del dispositivo si viene en el mensaje
        if (messageData && messageData.connected !== undefined) {
            this.deviceStatus = { ...this.deviceStatus, ...messageData };
        }
        
        // Ejecutar handler específico si existe
        const handler = this.messageHandlers[type];
        if (handler) {
            handler(messageData);
        }
        
        // Handlers especiales
        switch (type) {
            case 'connection_established':
                console.log('Conexión establecida con bridge:', messageData?.client_id || 'N/A');
                break;
            case 'device_status':
                if (messageData) {
                    this.deviceStatus = { ...this.deviceStatus, ...messageData };
                }
                break;
            case 'error':
                console.error('Error del bridge:', messageData?.message || 'Error desconocido');
                // Si hay un error de serialización, intentar obtener más información
                if (messageData?.message && messageData.message.includes('JSON serializable')) {
                    console.error('Error de serialización JSON detectado. El servidor está enviando objetos no serializables.');
                }
                break;
        }
    }
    
    /**
     * Registrar handler para un tipo de mensaje específico
     */
    onMessage(type, handler) {
        this.messageHandlers[type] = handler;
    }
    
    /**
     * Registrar handlers de conexión
     */
    onConnect(handler) {
        this.connectionHandlers.onConnect.push(handler);
    }
    
    onDisconnect(handler) {
        this.connectionHandlers.onDisconnect.push(handler);
    }
    
    onError(handler) {
        this.connectionHandlers.onError.push(handler);
    }
    
    /**
     * Ejecutar handlers de conexión
     */
    triggerConnectionHandlers(type, data = null) {
        this.connectionHandlers[type].forEach(handler => {
            try {
                handler(data);
            } catch (error) {
                console.error(`Error en handler ${type}:`, error);
            }
        });
    }
    
    /**
     * Enviar comando al bridge
     */
    sendCommand(command, data = {}) {
        if (!this.connected) {
            throw new Error('No hay conexión con el bridge');
        }
        
        const message = {
            command: command,
            ...data
        };
        
        this.ws.send(JSON.stringify(message));
    }
    
    /**
     * Conectar al dispositivo ZKTeco
     */
    async connectToDevice(ip = null, port = null) {
        return new Promise((resolve, reject) => {
            // const timeout = setTimeout(() => {
            //     reject(new Error('Timeout al conectar al dispositivo'));
            // }, 10000);
            
            this.onMessage('connect_response', (data) => {
                // clearTimeout(timeout);
                console.log('Connect response data:', data);
                resolve(data?.success !== false); // true si no es explícitamente false
            });
            
            this.sendCommand('connect', { ip, port });
        });
    }
    
    /**
     * Desconectar del dispositivo ZKTeco
     */
    async disconnectFromDevice() {
        return new Promise((resolve) => {
            this.onMessage('disconnect_response', (data) => {
                console.log('Disconnect response data:', data);
                resolve(data?.success !== false); // true si no es explícitamente false
            });
            
            this.sendCommand('disconnect');
        });
    }
    
    /**
     * Obtener información del dispositivo
     */
    async getDeviceInfo() {
        return new Promise((resolve, reject) => {
            // const timeout = setTimeout(() => {
            //     reject(new Error('Timeout al obtener información del dispositivo'));
            // }, 5000);
            
            this.onMessage('device_info', (data) => {
                // clearTimeout(timeout);
                console.log('Device info response data:', data);
                resolve(data || {});
            });
            
            this.sendCommand('get_info');
        });
    }
    
    /**
     * Obtener lista de usuarios del dispositivo
     */
    async getUsers(limit = null) {
        return new Promise((resolve, reject) => {
            // const timeout = setTimeout(() => {
            //     reject(new Error('Timeout al obtener usuarios'));
            // }, 10000);
            
            this.onMessage('users_list', (data) => {
                // clearTimeout(timeout);
                console.log('Users list response data:', data);
                resolve(data || { users: [] });
            });
            
            const commandData = limit !== null ? { limit } : {};
            this.sendCommand('get_users', commandData);
        });
    }
    
    /**
     * Agregar usuario al dispositivo
     */
    async addUser(uid, name, privilege = 0, password = "", group_id = "") {
        return new Promise((resolve, reject) => {
            // const timeout = setTimeout(() => {
            //     reject(new Error('Timeout al agregar usuario'));
            // }, 10000);
            
            this.onMessage('add_user_response', (data) => {
                // clearTimeout(timeout);
                console.log('Add user response data:', data);
                // Asegurar que siempre devolvemos un objeto con success
                if (data && typeof data === 'object') {
                    resolve({
                        success: data.success !== false, // true si no es explícitamente false
                        ...data
                    });
                } else {
                    resolve({ success: false, error: 'No response' });
                }
            });
            
            this.sendCommand('add_user', {
                uid: parseInt(uid),
                name: name,
                privilege: privilege,
                password: password,
                group_id: group_id
            });
        });
    }
    
    /**
     * Eliminar usuario del dispositivo
     */
    async deleteUser(uid) {
        return new Promise((resolve, reject) => {
            // const timeout = setTimeout(() => {
            //     reject(new Error('Timeout al eliminar usuario'));
            // }, 10000);
            
            this.onMessage('delete_user_response', (data) => {
                // clearTimeout(timeout);
                resolve(data);
            });
            
            this.sendCommand('delete_user', { uid: parseInt(uid) });
        });
    }
    
    /**
     * Obtener registros de asistencia
     */
    async getAttendanceLogs(limit = 20, offset = 0) {
        return new Promise((resolve, reject) => {
            let timeoutId;
            let resolved = false;
            
            // Handler para logs de asistencia
            const attendanceHandler = (data) => {
                if (resolved) return;
                resolved = true;
                clearTimeout(timeoutId);
                this.messageHandlers['attendance_logs'] = null; // Limpiar handler
                resolve(data);
            };
            
            // Handler para errores específicos de logs de asistencia
            const errorHandler = (data) => {
                if (resolved) return;
                resolved = true;
                clearTimeout(timeoutId);
                this.messageHandlers['attendance_logs_error'] = null; // Limpiar handler
                reject(new Error(data.message || 'Error al obtener registros de asistencia'));
            };
            
            // Registrar handlers
            this.onMessage('attendance_logs', attendanceHandler);
            this.onMessage('attendance_logs_error', errorHandler);
            
            // Sin timeout - esperar indefinidamente hasta que lleguen todos los registros
            // timeoutId = setTimeout(() => {
            //     if (resolved) return;
            //     resolved = true;
            //     this.messageHandlers['attendance_logs'] = null; // Limpiar handler
            //     this.messageHandlers['attendance_logs_error'] = null; // Limpiar handler
            //     reject(new Error('Timeout al obtener registros de asistencia - El dispositivo puede tener muchos registros o hay un problema de serialización'));
            // }, 300000); // 5 minutos (300 segundos) para prueba
            
            this.sendCommand('get_attendance', { limit, offset });
        });
    }
    
    /**
     * Buscar postulante por nombre
     */
    async searchPostulante(name) {
        return new Promise((resolve, reject) => {
            // const timeout = setTimeout(() => {
            //     reject(new Error('Timeout al buscar postulante'));
            // }, 10000);
            
            this.onMessage('search_postulante_response', (data) => {
                // clearTimeout(timeout);
                if (data.error) {
                    reject(new Error(data.error));
                } else {
                    resolve(data.data);
                }
            });
            
            this.sendCommand('search_postulante', { name });
        });
    }
    
    /**
     * Hacer ping al bridge
     */
    async ping() {
        return new Promise((resolve, reject) => {
            // const timeout = setTimeout(() => {
            //     reject(new Error('Timeout en ping'));
            // }, 3000);
            
            this.onMessage('pong', (data) => {
                // clearTimeout(timeout);
                resolve(data);
            });
            
            this.sendCommand('ping');
        });
    }
    
    /**
     * Resetear conexión y limpiar estado interno
     */
    async reset() {
        return new Promise((resolve, reject) => {
            this.onMessage('reset_response', (data) => {
                console.log('Reset response data:', data);
                resolve(data?.success !== false);
            });
            
            this.sendCommand('reset');
        });
    }
    
    /**
     * Obtener estado actual del dispositivo
     */
    getDeviceStatus() {
        return { ...this.deviceStatus };
    }
    
    /**
     * Verificar si está conectado al bridge
     */
    isConnected() {
        return this.connected;
    }
    
    /**
     * Verificar si el dispositivo está conectado
     */
    isDeviceConnected() {
        return this.deviceStatus.connected;
    }
}

// Función para crear instancia global del bridge
function createZKTecoBridge(config = {}) {
    // Detectar si estamos en HTTPS para usar WSS
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = window.location.hostname;
    const port = config.port || 8001;
    
    const bridgeConfig = {
        wsUrl: `${protocol}//${host}:${port}/ws/zkteco`,
        httpUrl: `${window.location.protocol}//${host}:${port}`,
        ...config
    };
    
    return new ZKTecoBridge(bridgeConfig);
}

// Exportar para uso global
window.ZKTecoBridge = ZKTecoBridge;
window.createZKTecoBridge = createZKTecoBridge;
