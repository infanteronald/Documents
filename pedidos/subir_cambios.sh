#!/bin/bash
# Script para subir archivos modificados al servidor remoto

echo "🚀 Subiendo archivos modificados al servidor..."

# Lista de archivos modificados
FILES=(
    "accesos/login.php"
    "accesos/middleware/AuthMiddleware.php"
    "accesos/models/User.php"
    "accesos/recuperar_password.php"
    "accesos/usuario_crear.php"
    "accesos/usuario_editar.php"
    "accesos/usuarios.php"
    "listar_pedidos.php"
    "listar_pedidos.css"
)

# Configuración del servidor
SERVER="motodota@68.66.226.124"
PORT="7822"
KEY="/Users/ronaldinfante/Documents/id_rsa"
REMOTE_PATH="/home/motodota/public_html/pedidos/"

# Subir cada archivo
for FILE in "${FILES[@]}"
do
    echo "📤 Subiendo $FILE..."
    scp -i "$KEY" -P "$PORT" -o MACs=hmac-sha2-256 "$FILE" "$SERVER:$REMOTE_PATH$FILE"
    if [ $? -eq 0 ]; then
        echo "✅ $FILE subido exitosamente"
    else
        echo "❌ Error al subir $FILE"
    fi
done

echo "✨ Proceso completado!"