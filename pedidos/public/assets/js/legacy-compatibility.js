/**
 * Wrapper de Compatibilidad Legacy
 * Secuoia Speed - Transición gradual a arquitectura profesional
 * 
 * Este archivo mantiene compatibilidad con archivos legacy mientras
 * migra gradualmente a la nueva estructura profesional.
 */

class LegacyCompatibilityWrapper {
  constructor() {
    this.legacyPaths = new Map();
    this.modernPaths = new Map();
    this.redirectMap = new Map();
    
    this.init();
  }

  init() {
    console.log('🔄 Inicializando wrapper de compatibilidad legacy...');
    
    // Mapear rutas legacy a modernas
    this.setupPathMapping();
    
    // Configurar redirects automáticos
    this.setupAutoRedirects();
    
    // Interceptar cargas de archivos legacy
    this.setupAssetInterception();
    
    console.log('✅ Wrapper de compatibilidad inicializado');
  }

  setupPathMapping() {
    // Assets CSS
    this.legacyPaths.set('pedidos.css', '/public/assets/css/app.css');
    this.legacyPaths.set('estilos.css', '/public/assets/css/components.css');
    this.legacyPaths.set('payment_ux_enhanced.css', '/public/assets/css/payment.css');
    this.legacyPaths.set('apple-ui.css', '/public/assets/css/app.css');
    this.legacyPaths.set('sequoia-unified.css', '/public/assets/css/app.css');
    this.legacyPaths.set('styles.css', '/public/assets/css/components.css');

    // Assets JavaScript
    this.legacyPaths.set('script.js', '/public/assets/js/app.js');
    this.legacyPaths.set('pedidos.js', '/public/assets/js/pedidos.js');
    this.legacyPaths.set('payment_ux_enhanced.js', '/public/assets/js/bold-integration.js');

    // Configuraciones
    this.legacyPaths.set('smtp_config.php', '/app/config/smtp.php');
    this.legacyPaths.set('conexion.php', '/app/config/database.php');

    // APIs - mapear a nueva estructura
    this.redirectMap.set('guardar_pedido.php', '/public/api/pedidos/create.php');
    this.redirectMap.set('actualizar_estado.php', '/public/api/pedidos/update-status.php');
    this.redirectMap.set('procesar_pago_manual.php', '/public/api/payments/manual.php');
    this.redirectMap.set('bold_webhook_enhanced.php', '/public/api/bold/webhook.php');
    this.redirectMap.set('productos_por_categoria.php', '/public/api/productos/by-category.php');
    this.redirectMap.set('exportar_excel.php', '/public/api/exports/excel.php');
  }

  setupAutoRedirects() {
    // Interceptar enlaces que apunten a archivos legacy
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a');
      if (!link) return;

      const href = link.getAttribute('href');
      if (href && this.redirectMap.has(href)) {
        e.preventDefault();
        const modernPath = this.redirectMap.get(href);
        
        // Preservar query parameters
        const url = new URL(link.href, window.location);
        const modernUrl = new URL(modernPath, window.location);
        modernUrl.search = url.search;
        
        window.location.href = modernUrl.toString();
      }
    });
  }

  setupAssetInterception() {
    // Interceptar cargas de CSS y JS legacy
    const originalCreateElement = document.createElement;
    
    document.createElement = function(tagName) {
      const element = originalCreateElement.call(this, tagName);
      
      if (tagName.toLowerCase() === 'link' && element.rel === 'stylesheet') {
        const originalSetAttribute = element.setAttribute;
        element.setAttribute = function(name, value) {
          if (name === 'href' && window.legacyWrapper.legacyPaths.has(value)) {
            value = window.legacyWrapper.legacyPaths.get(value);
            console.log(`🔄 Redirigiendo CSS legacy: ${arguments[1]} → ${value}`);
          }
          return originalSetAttribute.call(this, name, value);
        };
      }
      
      if (tagName.toLowerCase() === 'script') {
        const originalSetAttribute = element.setAttribute;
        element.setAttribute = function(name, value) {
          if (name === 'src' && window.legacyWrapper.legacyPaths.has(value)) {
            value = window.legacyWrapper.legacyPaths.get(value);
            console.log(`🔄 Redirigiendo JS legacy: ${arguments[1]} → ${value}`);
          }
          return originalSetAttribute.call(this, name, value);
        };
      }
      
      return element;
    };
  }

  // Funciones helper para mantener compatibilidad con código legacy
  loadLegacyAsset(legacyPath) {
    const modernPath = this.legacyPaths.get(legacyPath);
    if (modernPath) {
      console.log(`🔄 Cargando asset moderno: ${legacyPath} → ${modernPath}`);
      return modernPath;
    }
    
    console.warn(`⚠️ Asset legacy no mapeado: ${legacyPath}`);
    return legacyPath; // Fallback al path original
  }

  redirectToModernAPI(legacyEndpoint, data = {}) {
    const modernEndpoint = this.redirectMap.get(legacyEndpoint);
    if (modernEndpoint) {
      console.log(`🔄 Redirigiendo API: ${legacyEndpoint} → ${modernEndpoint}`);
      
      // Realizar petición a la API moderna
      return fetch(modernEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Legacy-Compatibility': 'true'
        },
        body: JSON.stringify(data)
      });
    }
    
    console.warn(`⚠️ Endpoint legacy no mapeado: ${legacyEndpoint}`);
    return null;
  }

  // Funciones de compatibilidad para JavaScript legacy
  createLegacyGlobals() {
    // Mantener funciones globales que el código legacy espera
    window.showNotification = function(message, type = 'info') {
      if (window.UIEnhancer && window.UIEnhancer.showNotification) {
        window.UIEnhancer.showNotification(message, type);
      } else {
        console.log(`[${type.toUpperCase()}] ${message}`);
      }
    };

    window.formatCurrency = function(amount) {
      if (window.AppUtils && window.AppUtils.formatCurrency) {
        return window.AppUtils.formatCurrency(amount);
      }
      return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP'
      }).format(amount);
    };

    window.validateEmail = function(email) {
      if (window.FormValidator && window.FormValidator.validateEmail) {
        return window.FormValidator.validateEmail(email);
      }
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    };

    // Compatibilidad con funciones de pago legacy
    if (typeof initializeBoldPayment === 'undefined') {
      window.initializeBoldPayment = function() {
        if (window.boldPayment) {
          return window.boldPayment.initializePayment();
        }
        console.warn('⚠️ Bold Payment no disponible');
        return false;
      };
    }

    // Compatibilidad con gestión de pedidos legacy
    window.updateOrderStatus = function(orderId, status) {
      if (window.PedidoManager && window.PedidoManager.updateStatus) {
        return window.PedidoManager.updateStatus(orderId, status);
      }
      
      // Fallback a API legacy
      return fetch('actualizar_estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${orderId}&estado=${status}`
      });
    };
  }

  // Migración progresiva de configuraciones
  async migrateConfiguration() {
    console.log('🔄 Iniciando migración de configuraciones...');
    
    try {
      // Cargar configuraciones modernas
      const response = await fetch('/app/config/app.php');
      if (response.ok) {
        console.log('✅ Configuraciones modernas cargadas');
        return true;
      }
    } catch (error) {
      console.warn('⚠️ Error cargando configuraciones modernas, usando legacy');
    }
    
    return false;
  }

  // Verificar disponibilidad de funciones modernas
  checkModernFeatures() {
    const features = {
      modernCSS: document.querySelector('link[href*="/public/assets/css/"]') !== null,
      modernJS: window.AppUtils !== undefined,
      modernPayment: window.boldPayment !== undefined,
      modernPedidos: window.PedidoManager !== undefined,
      modernValidation: window.FormValidator !== undefined
    };

    console.log('🔍 Estado de funciones modernas:', features);
    return features;
  }

  // Logs de migración
  logMigrationStatus() {
    const features = this.checkModernFeatures();
    const modernCount = Object.values(features).filter(Boolean).length;
    const totalCount = Object.keys(features).length;
    const percentage = Math.round((modernCount / totalCount) * 100);

    console.log(`📊 Progreso de migración: ${percentage}% (${modernCount}/${totalCount})`);
    
    if (percentage === 100) {
      console.log('🎉 ¡Migración completada! Todos los sistemas modernos están activos');
    } else if (percentage >= 75) {
      console.log('🚀 Migración avanzada - La mayoría de sistemas son modernos');
    } else if (percentage >= 50) {
      console.log('⚡ Migración en progreso - Sistemas híbridos activos');
    } else {
      console.log('🔄 Migración inicial - Principalmente sistemas legacy');
    }

    return { percentage, features };
  }
}

// Funciones helper globales para compatibilidad
function loadAsset(path, type = 'auto') {
  const wrapper = window.legacyWrapper;
  if (!wrapper) return path;

  const modernPath = wrapper.loadLegacyAsset(path);
  
  if (type === 'css' || (type === 'auto' && path.endsWith('.css'))) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = modernPath;
    document.head.appendChild(link);
  } else if (type === 'js' || (type === 'auto' && path.endsWith('.js'))) {
    const script = document.createElement('script');
    script.src = modernPath;
    document.head.appendChild(script);
  }
  
  return modernPath;
}

function migrateToModernAPI(endpoint, data = {}) {
  const wrapper = window.legacyWrapper;
  if (!wrapper) return null;
  
  return wrapper.redirectToModernAPI(endpoint, data);
}

// Inicialización automática
document.addEventListener('DOMContentLoaded', function() {
  window.legacyWrapper = new LegacyCompatibilityWrapper();
  window.legacyWrapper.createLegacyGlobals();
  
  // Migrar configuraciones si es posible
  window.legacyWrapper.migrateConfiguration().then(() => {
    // Log del estado de migración
    setTimeout(() => {
      window.legacyWrapper.logMigrationStatus();
    }, 2000); // Esperar a que carguen todos los scripts
  });
});
