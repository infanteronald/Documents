#!/bin/bash

# Script para subir archivos modificados al servidor de producción
# Usando expect para manejar la passphrase automáticamente

REMOTE_HOST="68.66.226.124"
REMOTE_USER="motodota"
REMOTE_PORT="7822"
REMOTE_PATH="/home/motodota/sequoiaspeed.com.co/pedidos/"
KEY_PATH="/Users/ronaldinfante/id_rsa"
PASSPHRASE="Blink.182..."

# Lista de archivos modificados recientemente
FILES_TO_UPLOAD=(
    "filters.php"
    "listar_pedidos.php" 
    "orden_pedido.php"
    "pedido.php"
    "comprobante.php"
)

echo "🚀 Iniciando subida de archivos modificados..."

# Función para subir un archivo usando expect
upload_file() {
    local file=$1
    echo "📤 Subiendo $file..."
    
    expect << EOF
    set timeout 30
    spawn scp -i $KEY_PATH -P $REMOTE_PORT "$file" $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH
    expect {
        "Enter passphrase for" {
            send "$PASSPHRASE\r"
            expect {
                "100%" {
                    puts "✅ $file subido exitosamente"
                }
                timeout {
                    puts "❌ Timeout subiendo $file"
                    exit 1
                }
            }
        }
        "Permission denied" {
            puts "❌ Error de permisos para $file"
            exit 1
        }
        timeout {
            puts "❌ Timeout conectando para $file"
            exit 1
        }
    }
    expect eof
EOF

    if [ $? -eq 0 ]; then
        echo "✅ $file subido correctamente"
    else
        echo "❌ Error subiendo $file"
        return 1
    fi
}

# Verificar que expect esté instalado
if ! command -v expect &> /dev/null; then
    echo "❌ Error: 'expect' no está instalado. Instalando..."
    if command -v brew &> /dev/null; then
        brew install expect
    else
        echo "❌ No se puede instalar expect automáticamente. Instálelo manualmente."
        exit 1
    fi
fi

# Subir cada archivo
for file in "${FILES_TO_UPLOAD[@]}"; do
    if [ -f "$file" ]; then
        upload_file "$file"
    else
        echo "⚠️ Archivo $file no encontrado, saltando..."
    fi
done

echo "🎉 Proceso de subida completado"