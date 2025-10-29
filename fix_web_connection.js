// Script de reparación para la conexión de la página web
// Ejecutar en la consola del navegador (F12) en la página agregar_postulante.php

console.log('🔧 Iniciando reparación de conexión...');

// 1. Verificar si el bridge está disponible
async function verificarBridge() {
    try {
        const response = await fetch('http://localhost:8001/');
        const data = await response.json();
        console.log('✅ Bridge disponible:', data);
        return true;
    } catch (error) {
        console.error('❌ Bridge no disponible:', error);
        return false;
    }
}

// 2. Reconectar el bridge
async function reconectarBridge() {
    console.log('🔄 Reconectando bridge...');
    
    // Verificar si existe la variable global zktecoBridge
    if (typeof zktecoBridge !== 'undefined') {
        console.log('📡 Bridge encontrado, reconectando...');
        
        try {
            // Desconectar si está conectado
            if (zktecoBridge.isConnected()) {
                await zktecoBridge.disconnect();
                console.log('🔌 Bridge desconectado');
            }
            
            // Reconectar
            await zktecoBridge.connect();
            console.log('✅ Bridge reconectado');
            
            // Conectar al dispositivo
            const deviceConnected = await zktecoBridge.connectToDevice();
            if (deviceConnected) {
                console.log('✅ Dispositivo conectado');
                
                // Obtener información del dispositivo
                const deviceInfo = await zktecoBridge.getDeviceInfo();
                console.log('📊 Información del dispositivo:', deviceInfo);
                
                // Obtener usuarios
                const users = await zktecoBridge.getUsers();
                console.log('👥 Usuarios obtenidos:', users.users ? users.users.length : 0, 'de', users.total_count);
                
                return true;
            } else {
                console.error('❌ No se pudo conectar al dispositivo');
                return false;
            }
            
        } catch (error) {
            console.error('❌ Error reconectando:', error);
            return false;
        }
    } else {
        console.error('❌ Variable zktecoBridge no encontrada');
        return false;
    }
}

// 3. Función principal de reparación
async function repararConexion() {
    console.log('🚀 Iniciando reparación de conexión...');
    
    // Verificar bridge
    const bridgeDisponible = await verificarBridge();
    if (!bridgeDisponible) {
        console.error('❌ El bridge no está disponible. Verificar que esté ejecutándose.');
        return false;
    }
    
    // Reconectar
    const reconectado = await reconectarBridge();
    if (reconectado) {
        console.log('🎉 ¡Conexión reparada exitosamente!');
        
        // Actualizar estado en la página si existe la función
        if (typeof updateDeviceStatus === 'function') {
            updateDeviceStatus('Conectado al dispositivo biométrico', 'connected');
        }
        
        return true;
    } else {
        console.error('❌ No se pudo reparar la conexión');
        return false;
    }
}

// 4. Ejecutar reparación automáticamente
repararConexion().then(success => {
    if (success) {
        console.log('✅ Reparación completada exitosamente');
    } else {
        console.log('❌ La reparación falló. Verificar logs anteriores.');
    }
});

// 5. Función para ejecutar manualmente si es necesario
window.repararConexion = repararConexion;
console.log('💡 Para ejecutar manualmente: repararConexion()');
