<?php
// Footer fijo y modal del desarrollador
// Este archivo debe ser incluido en todas las páginas principales
?>

<!-- Footer fijo -->
<div class="fixed-footer">
    <p class="footer-text">
        Powered by <span class="developer-name">s1mple</span>
    </p>
</div>

<!-- Modal del desarrollador -->
<div class="modal-overlay">
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
</div>

<script>
// Funcionalidad del modal del desarrollador
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
});

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
</script>
