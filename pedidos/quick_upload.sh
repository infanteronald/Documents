#!/bin/bash

# Script rápido para subir archivos específicos al servidor
# Uso: ./quick_upload.sh archivo1.php archivo2.php
# O: ./quick_upload.sh (sube archivos modificados en las últimas 2 horas)

REMOTE_HOST="68.66.226.124"
REMOTE_USER="motodota"
REMOTE_PORT="7822"
REMOTE_PATH="/home/motodota/sequoiaspeed.com.co/pedidos/"
KEY_PATH="/Users/ronaldinfante/id_rsa"
PASSPHRASE="Blink.182..."

upload_file() {
    local file=$1
    echo "📤 Subiendo $file..."
    
    expect << EOF >/dev/null 2>&1
    set timeout 30
    spawn scp -i $KEY_PATH -P $REMOTE_PORT "$file" $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH
    expect {
        "Enter passphrase for" {
            send "$PASSPHRASE\r"
            expect {
                "100%" { 
                    puts "OK"
                    exit 0 
                }
                timeout { exit 1 }
            }
        }
        "Permission denied" { exit 1 }
        timeout { exit 1 }
    }
    expect eof
EOF

    if [ $? -eq 0 ]; then
        echo "✅ $file"
    else
        echo "❌ $file - ERROR"
        return 1
    fi
}

if [ $# -eq 0 ]; then
    # Si no se especifican archivos, buscar modificados recientemente
    echo "🔍 Buscando archivos modificados en las últimas 2 horas..."
    FILES=$(find . -name "*.php" -mtime -2h ! -path "./.git/*" ! -path "./uploads/*" ! -path "./desarrollo/*" 2>/dev/null)
    
    if [ -z "$FILES" ]; then
        echo "⚠️ No se encontraron archivos PHP modificados recientemente"
        exit 0
    fi
    
    echo "📦 Archivos a subir:"
    echo "$FILES" | sed 's/^/  /'
    echo ""
    
    for file in $FILES; do
        upload_file "$file"
    done
else
    # Subir archivos específicos
    for file in "$@"; do
        if [ -f "$file" ]; then
            upload_file "$file"
        else
            echo "❌ $file - No encontrado"
        fi
    done
fi

echo ""
echo "🎉 Proceso completado"