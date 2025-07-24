#!/bin/bash
# Script para sincronizar archivos modificados con el servidor remoto

echo "🔄 Sincronizando archivos con el servidor..."

# Archivos específicos a sincronizar
rsync -avz --progress \
    -e "ssh -i /Users/ronaldinfante/Documents/id_rsa -p 7822 -o MACs=hmac-sha2-256" \
    --files-from=- \
    . motodota@68.66.226.124:/home/motodota/public_html/pedidos/ <<EOF
accesos/login.php
accesos/middleware/AuthMiddleware.php
accesos/models/User.php
accesos/recuperar_password.php
accesos/usuario_crear.php
accesos/usuario_editar.php
accesos/usuarios.php
listar_pedidos.php
listar_pedidos.css
EOF

echo "✨ Sincronización completada!"