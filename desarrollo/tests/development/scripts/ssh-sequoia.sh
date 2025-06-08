#!/bin/bash
# Script para conectar a Sequoia Speed sin warnings de locale
# Uso: ./ssh-sequoia.sh [comando opcional]

SSH_KEY="/users/ronaldinfante/id_rsa"
SSH_HOST="motodota@68.66.226.124"
SSH_PORT="7822"
SSH_MACS="hmac-sha2-256"

# Configuración de locale limpia
export LANG=C.UTF-8
export LC_ALL=C.UTF-8
export LANGUAGE=C.UTF-8

if [ $# -eq 0 ]; then
    # Conexión interactiva sin warnings
    ssh -i "$SSH_KEY" -o MACs="$SSH_MACS" -o ConnectTimeout=10 \
        -o ServerAliveInterval=30 -o ServerAliveCountMax=3 \
        -o SendEnv="" -o SetEnv="LANG=C.UTF-8,LC_ALL=C.UTF-8,LANGUAGE=C.UTF-8" \
        "$SSH_HOST" -p "$SSH_PORT"
else
    # Ejecutar comando remoto sin warnings
    ssh -i "$SSH_KEY" -o MACs="$SSH_MACS" -o ConnectTimeout=10 \
        -o ServerAliveInterval=30 -o ServerAliveCountMax=3 \
        -o SendEnv="" -o SetEnv="LANG=C.UTF-8,LC_ALL=C.UTF-8,LANGUAGE=C.UTF-8" \
        "$SSH_HOST" -p "$SSH_PORT" "$@"
fi
