/**
 * Auto-Asset Updater - Sequoia Speed
 * Sistema automático de actualización de rutas de assets
 * 
 * Este script se ejecuta automáticamente y actualiza las referencias
 * a assets legacy para que apunten a las rutas modernas
 */

class AssetUpdater {
  constructor() {
    this.assetMap = new Map([
      // CSS Legacy -> Moderno
      ['pedidos.css', '/public/assets/css/app.css'],
      ['estilos.css', '/public/assets/css/components.css'],
      ['payment_ux_enhanced.css', '/public/assets/css/payment.css'],
      ['apple-ui.css', '/public/assets/css/app.css'],
      ['sequoia-unified.css', '/public/assets/css/app.css'],
      
      // JS Legacy -> Moderno
      ['script.js', '/public/assets/js/app.js'],
      ['pedidos.js', '/public/assets/js/pedidos.js'],
      ['payment_ux_enhanced.js', '/public/assets/js/bold-integration.js']
    ]);

    this.init();
  }

  init() {
    console.log('🔄 Iniciando actualización automática de assets...');
    
    // Actualizar cuando el DOM esté listo
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.updateExistingAssets());
    } else {
      this.updateExistingAssets();
    }

    // Interceptar nuevas cargas
    this.interceptAssetLoading();
    
    console.log('✅ Actualizador de assets iniciado');
  }

  updateExistingAssets() {
    // Actualizar enlaces CSS existentes
    const cssLinks = document.querySelectorAll('link[rel="stylesheet"]');
    cssLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (this.assetMap.has(href)) {
        const newHref = this.assetMap.get(href);
        console.log(`🔄 Actualizando CSS: ${href} → ${newHref}`);
        link.href = newHref;
      }
    });

    // Actualizar scripts existentes
    const scripts = document.querySelectorAll('script[src]');
    scripts.forEach(script => {
      const src = script.getAttribute('src');
      if (this.assetMap.has(src)) {
        const newSrc = this.assetMap.get(src);
        console.log(`🔄 Actualizando JS: ${src} → ${newSrc}`);
        
        // Crear nuevo script para reemplazar el anterior
        const newScript = document.createElement('script');
        newScript.src = newSrc;
        newScript.async = script.async;
        newScript.defer = script.defer;
        
        // Reemplazar
        script.parentNode.insertBefore(newScript, script);
        script.remove();
      }
    });
  }

  interceptAssetLoading() {
    // Interceptar creación de elementos link
    const originalCreateElement = document.createElement;
    
    document.createElement = function(tagName) {
      const element = originalCreateElement.call(this, tagName);
      
      if (tagName.toLowerCase() === 'link') {
        const originalSetAttribute = element.setAttribute;
        element.setAttribute = function(name, value) {
          if (name === 'href' && window.assetUpdater.assetMap.has(value)) {
            const newValue = window.assetUpdater.assetMap.get(value);
            console.log(`🔄 Interceptando CSS: ${value} → ${newValue}`);
            value = newValue;
          }
          return originalSetAttribute.call(this, name, value);
        };
      }
      
      if (tagName.toLowerCase() === 'script') {
        const originalSetAttribute = element.setAttribute;
        element.setAttribute = function(name, value) {
          if (name === 'src' && window.assetUpdater.assetMap.has(value)) {
            const newValue = window.assetUpdater.assetMap.get(value);
            console.log(`🔄 Interceptando JS: ${value} → ${newValue}`);
            value = newValue;
          }
          return originalSetAttribute.call(this, name, value);
        };
      }
      
      return element;
    };
  }

  // Función helper para cargar assets modernos
  loadModernAsset(legacyPath, type = 'auto') {
    const modernPath = this.assetMap.get(legacyPath) || legacyPath;
    
    if (type === 'css' || (type === 'auto' && legacyPath.endsWith('.css'))) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = modernPath;
      document.head.appendChild(link);
      return link;
    }
    
    if (type === 'js' || (type === 'auto' && legacyPath.endsWith('.js'))) {
      const script = document.createElement('script');
      script.src = modernPath;
      document.head.appendChild(script);
      return script;
    }
    
    return null;
  }

  // Verificar si un asset legacy ha sido cargado
  isLegacyAssetLoaded(legacyPath) {
    // Verificar CSS
    const cssLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
    const hasLegacyCSS = cssLinks.some(link => 
      link.href.includes(legacyPath) || link.href.endsWith(legacyPath)
    );
    
    // Verificar JS
    const scripts = Array.from(document.querySelectorAll('script[src]'));
    const hasLegacyJS = scripts.some(script => 
      script.src.includes(legacyPath) || script.src.endsWith(legacyPath)
    );
    
    return hasLegacyCSS || hasLegacyJS;
  }

  // Cargar asset moderno solo si el legacy no está presente
  loadIfNotPresent(legacyPath, type = 'auto') {
    if (!this.isLegacyAssetLoaded(legacyPath)) {
      console.log(`📦 Cargando asset moderno para: ${legacyPath}`);
      return this.loadModernAsset(legacyPath, type);
    } else {
      console.log(`⚠️ Asset legacy detectado: ${legacyPath}, actualizando...`);
      // El interceptor se encargará de actualizar
      return null;
    }
  }
}

// Funciones globales para compatibilidad
window.loadAsset = function(path, type = 'auto') {
  if (window.assetUpdater) {
    return window.assetUpdater.loadModernAsset(path, type);
  }
  console.warn('⚠️ AssetUpdater no disponible');
  return null;
};

window.ensureModernAssets = function() {
  if (!window.assetUpdater) return;
  
  // Lista de assets críticos que deben estar presentes
  const criticalAssets = [
    { path: 'pedidos.css', type: 'css' },
    { path: 'script.js', type: 'js' },
    { path: 'payment_ux_enhanced.js', type: 'js' }
  ];
  
  criticalAssets.forEach(asset => {
    window.assetUpdater.loadIfNotPresent(asset.path, asset.type);
  });
};

// Inicializar automáticamente
(function() {
  // Esperar a que el documento esté listo
  function initAssetUpdater() {
    window.assetUpdater = new AssetUpdater();
    
    // Cargar assets críticos después de un breve delay
    setTimeout(() => {
      window.ensureModernAssets();
    }, 100);
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAssetUpdater);
  } else {
    initAssetUpdater();
  }
})();
