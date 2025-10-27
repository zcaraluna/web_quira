// Funcionalidad del modal del desarrollador
document.addEventListener('DOMContentLoaded', function() {
    // Crear el footer fijo
    createFixedFooter();
    
    // Crear el modal
    createDeveloperModal();
    
    // Agregar event listeners
    setupEventListeners();
});

function createFixedFooter() {
    // Verificar si el footer ya existe
    if (document.querySelector('.fixed-footer')) {
        return;
    }
    
    const footer = document.createElement('div');
    footer.className = 'fixed-footer';
    footer.innerHTML = `
        <p class="footer-text">
            Powered by <span class="developer-name">s1mple</span>
        </p>
    `;
    
    document.body.appendChild(footer);
}

function createDeveloperModal() {
    // Verificar si el modal ya existe
    if (document.querySelector('.modal-overlay')) {
        return;
    }
    
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2 class="modal-title">s1mple</h2>
                <div class="modal-divider"></div>
                <p class="modal-subtitle">From BITCAN</p>
            </div>
            
            <div class="modal-body">
                <div class="info-card">
                    <div class="info-label">Desarrollador</div>
                    <p class="info-content">GUILLERMO ANDRÉS</p>
                    <p class="info-content">RECALDE VALDEZ</p>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Contacto</div>
                    <p class="info-content-small">recaldev.ga@bitcan.com.py</p>
                    <p class="info-content-small mt-1">+595 973 408 754</p>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Servicios</div>
                    <p class="info-content-small">Desarrollo de sistemas de gestión y empresariales a medida</p>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Proyecto</div>
                    <p class="info-content info-content-green">aXeso</p>
                    <p class="info-content-small">Sistema de Control de Acceso</p>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Versión</div>
                    <p class="info-content">Beta 1.0.0</p>
                    <p class="info-content-small mt-1">16/10/2025</p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button class="btn-close" onclick="closeModal()">Cerrar</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function setupEventListeners() {
    // Click en el footer para abrir modal
    const footer = document.querySelector('.fixed-footer');
    if (footer) {
        footer.addEventListener('click', openModal);
    }
    
    // Click en el overlay para cerrar modal
    const modalOverlay = document.querySelector('.modal-overlay');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', closeModal);
    }
    
    // Tecla ESC para cerrar modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
}

function openModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevenir scroll del body
    }
}

function closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Restaurar scroll del body
    }
}

// Hacer las funciones globales para uso en onclick
window.openModal = openModal;
window.closeModal = closeModal;
