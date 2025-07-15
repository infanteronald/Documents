# Respaldo de Configuraciones de VSCode

Este directorio contiene un respaldo completo de tus configuraciones de VSCode.

## Estructura de carpetas

```
conf/
├── vscode/
│   ├── project/          # Configuraciones específicas del proyecto (.vscode)
│   │   ├── extensions.json
│   │   └── sftp.json
│   ├── global/           # Configuraciones globales de VSCode
│   │   ├── settings.json
│   │   ├── keybindings.json
│   │   └── snippets/
│   └── extensions.txt    # Lista de extensiones instaladas
├── restore_vscode.sh     # Script de restauración automática
└── README.md            # Este archivo
```

## Cómo usar

### Para restaurar automáticamente:

1. Asegúrate de tener VSCode instalado
2. Ejecuta el script de restauración:
   ```bash
   cd /path/to/pedidos
   ./conf/restore_vscode.sh
   ```

### Para restaurar manualmente:

1. **Configuraciones globales:**
   - Copia los archivos de `conf/vscode/global/` a `~/Library/Application Support/Code/User/`

2. **Configuraciones del proyecto:**
   - Copia los archivos de `conf/vscode/project/` a `.vscode/` en la raíz del proyecto

3. **Extensiones:**
   - Instala cada extensión listada en `conf/vscode/extensions.txt` usando:
     ```bash
     code --install-extension <extension-id>
     ```

## Agregar más extensiones

Si instalas nuevas extensiones y quieres agregarlas al respaldo:

1. Lista tus extensiones actuales:
   ```bash
   code --list-extensions
   ```

2. Agrega las nuevas extensiones a `conf/vscode/extensions.txt`

## Actualizar el respaldo

Para actualizar este respaldo con cambios nuevos, ejecuta estos comandos:

```bash
# Copiar configuraciones del proyecto
cp -r .vscode/* conf/vscode/project/

# Copiar configuraciones globales
cp ~/Library/Application\ Support/Code/User/settings.json conf/vscode/global/
cp ~/Library/Application\ Support/Code/User/keybindings.json conf/vscode/global/
cp -r ~/Library/Application\ Support/Code/User/snippets conf/vscode/global/

# Actualizar lista de extensiones
code --list-extensions > conf/vscode/extensions.txt
```