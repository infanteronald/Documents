#!/bin/bash

# SCRIPT DE OPTIMIZACIÓN PARA VS CODE
# Autor: Sistema de Limpieza Sequoia Speed
# Fecha: 2025-06-08

echo "🚀 OPTIMIZADOR DE VS CODE PARA SEQUOIA SPEED"
echo "=============================================="

# 1. Limpiar archivos temporales del proyecto
echo "📁 Limpiando archivos temporales del proyecto..."
find /Users/ronaldinfante/Documents/pedidos -name "*.log" -delete 2>/dev/null
find /Users/ronaldinfante/Documents/pedidos -name "*.tmp" -delete 2>/dev/null
find /Users/ronaldinfante/Documents/pedidos -name ".DS_Store" -delete 2>/dev/null

# 2. Limpiar caché de VS Code
echo "🧹 Limpiando caché de VS Code..."
rm -rf ~/Library/Application\ Support/Code/logs/* 2>/dev/null
rm -rf ~/Library/Application\ Support/Code/CachedData/* 2>/dev/null
rm -rf ~/Library/Caches/com.microsoft.VSCode/* 2>/dev/null

# 3. Optimizar configuración de VS Code para PHP
echo "⚙️  Creando configuración optimizada..."

# Crear configuración optimizada para el workspace
cat > /Users/ronaldinfante/Documents/pedidos/.vscode/settings.json << 'EOF'
{
    // === OPTIMIZACIÓN DE RENDIMIENTO ===
    "files.watcherExclude": {
        "**/node_modules/**": true,
        "**/uploads/**": true,
        "**/comprobantes/**": true,
        "**/guias/**": true,
        "**/desarrollo/temp/**": true,
        "**/desarrollo/obsolete/**": true,
        "**/.git/**": true,
        "**/vendor/**": true
    },
    "search.exclude": {
        "**/node_modules": true,
        "**/uploads": true,
        "**/comprobantes": true,
        "**/guias": true,
        "**/desarrollo/temp": true,
        "**/desarrollo/obsolete": true,
        "**/vendor": true
    },
    "files.exclude": {
        "**/desarrollo/temp": true,
        "**/desarrollo/obsolete": true,
        "**/.DS_Store": true,
        "**/Thumbs.db": true
    },

    // === CONFIGURACIÓN PHP ===
    "php.validate.enable": true,
    "php.validate.run": "onType",
    "php.suggest.basic": false,

    // === CONFIGURACIÓN DE ARCHIVOS ===
    "files.autoSave": "onWindowChange",
    "files.trimTrailingWhitespace": true,
    "files.insertFinalNewline": true,

    // === OPTIMIZACIÓN DE INTERFAZ ===
    "workbench.editor.enablePreview": false,
    "workbench.editor.enablePreviewFromQuickOpen": false,
    "editor.minimap.enabled": false,
    "explorer.incrementalNaming": "smart",

    // === CONFIGURACIÓN GIT ===
    "git.autoRefresh": false,
    "git.autoRepositoryDetection": false,

    // === CONFIGURACIÓN DE FORMATO ===
    "editor.formatOnSave": true,
    "editor.formatOnPaste": false,
    "editor.formatOnType": false,

    // === CONFIGURACIÓN DE BÚSQUEDA ===
    "search.smartCase": true,
    "search.useRipgrep": true,

    // === CONFIGURACIÓN DE EXTENSIONES ===
    "extensions.autoUpdate": false,
    "extensions.autoCheckUpdates": false,

    // === CONFIGURACIÓN DE TERMINAL ===
    "terminal.integrated.enablePersistentSessions": false
}
EOF

# 4. Crear configuración de extensiones recomendadas
cat > /Users/ronaldinfante/Documents/pedidos/.vscode/extensions.json << 'EOF'
{
    "recommendations": [
        "ms-vscode.vscode-php-pack",
        "bmewburn.vscode-intelephense-client",
        "xdebug.php-debug",
        "bradlc.vscode-tailwindcss"
    ],
    "unwantedRecommendations": [
        "ms-vscode.vscode-typescript-next",
        "ms-vscode.vscode-javascript",
        "ms-python.python"
    ]
}
EOF

# 5. Crear .gitignore para VS Code si no existe
if [ ! -f /Users/ronaldinfante/Documents/pedidos/.vscode/.gitignore ]; then
    cat > /Users/ronaldinfante/Documents/pedidos/.vscode/.gitignore << 'EOF'
# Ignorar configuraciones personales
settings.json
launch.json
tasks.json

# Mantener configuración del proyecto
!extensions.json
EOF
fi

echo "✅ Optimización completada!"
echo ""
echo "📋 RECOMENDACIONES ADICIONALES:"
echo "1. Reinicia VS Code completamente"
echo "2. Cierra pestañas innecesarias"
echo "3. Usa 'Cmd+Shift+P' → 'Developer: Reload Window'"
echo "4. Considera usar workspaces separados para diferentes partes del proyecto"
echo ""
echo "🔧 CONFIGURACIONES APLICADAS:"
echo "• Archivos de uploads/comprobantes/guias excluidos del watch"
echo "• Autoguardado optimizado"
echo "• Git autorefresh deshabilitado"
echo "• Minimapa deshabilitado"
echo "• Preview de archivos deshabilitado"
echo ""
echo "Para aplicar cambios: Reinicia VS Code"
