# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview
Sequoia Speed - Sistema de Gestión de Pedidos is a PHP-based order management system with inventory control, shipping management, payment processing (Bold PSE), and real-time notifications.

## Common Development Commands

### Initial Setup

```bash
# Install PHP dependencies
composer install

# Create required directories
mkdir -p logs uploads/photos storage cache inventario/uploads/temp inventario/uploads/products reportes/exports

# Set proper permissions
chmod -R 755 logs uploads storage cache inventario/uploads reportes/exports
```

### Database Setup

```bash
# Execute database migrations in order
mysql -u [user] -p [database] < app/sql/0001_initial_schema.sql
mysql -u [user] -p [database] < app/sql/0002_rbac_tables.sql
mysql -u [user] -p [database] < app/sql/0003_security_enhancements.sql
mysql -u [user] -p [database] < inventario/sql/setup_inventario.sql
mysql -u [user] -p [database] < inventario/almacenes/sql/almacenes_setup.sql
mysql -u [user] -p [database] < inventario/categorias/sql/categorias_setup.sql
```

### Running the Application

```bash
# Start PHP development server
php -S localhost:8000 -t public/

# Monitor application logs
tail -f logs/app_*.log
tail -f logs/error_*.log
tail -f logs/sse_*.log

# Monitor SSE processes
php notifications/monitor_processes.php
```

### Testing
```bash
# Test database connection
php test_db_connection.php

# Test email configuration
php app/services/test_email.php

# Monitor SSE events
php notifications/monitor_processes.php
```

## High-Level Architecture

### Directory Structure
- `/accesos/` - Complete RBAC authentication system with 6 hierarchical roles
- `/inventario/` - Inventory management with products, warehouses, and categories
- `/transporte/` - VitalCarga delivery management system
- `/reportes/` - Reporting and analytics with Excel/PDF export
- `/bold/` - Bold PSE payment gateway integration
- `/notifications/` - Real-time push notifications and SSE
- `/app/` - Core MVC framework with controllers, models, and services
- `/public/` - Web root with API endpoints

### Key Technologies
- PHP 8.0+ with PSR-4 autoloading
- MySQL/MariaDB database
- Vanilla JavaScript with responsive dark theme UI
- Server-Sent Events (SSE) for real-time updates
- Web Push notifications via minishlink/web-push

### Security Features
- CSRF protection on all forms
- Session-based authentication with secure cookies
- Role-based access control with hierarchical permissions
- Comprehensive audit logging
- Input validation and SQL injection prevention

### Important Routes
- `/` - Main dashboard (requires login)
- `/login` - Authentication entry point
- `/api/` - RESTful API endpoints
- `/accesos/` - User and role management
- `/inventario/` - Product and warehouse management
- `/reportes/` - Reports and analytics

### Environment Configuration
Create `.env` file with:
```
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_user
DB_PASS=your_password
SMTP_HOST=smtp.gmail.com
SMTP_USER=your_email
SMTP_PASS=your_password
VAPID_PUBLIC_KEY=your_key
VAPID_PRIVATE_KEY=your_key
```

### Git Workflow
```bash
# Check current branch (usually nueva-interfaz)
git status

# Stage and commit changes
git add .
git commit -m "✨ feat: description" # Use gitmoji conventions

# Common branch operations
git checkout main
git merge nueva-interfaz
```

### VSCode Configuration
```bash
# Restore VSCode settings
bash conf/restore_vscode.sh
```

## Development Notes

- Always check user permissions before implementing features
- The system uses Spanish as the primary language
- Mobile-first responsive design is mandatory
- Dark theme (VS Code Dark) is the standard UI theme
- All monetary values use Colombian peso (COP)
- Timezone is America/Bogota (UTC-5)
- File uploads go to `/uploads/` with subfolders for different types
- Logs rotate daily with format: `type_YYYY-MM-DD.log`

## Common Issues and Solutions

1. **Session errors**: Check `/logs/` directory permissions
2. **Upload failures**: Verify `/uploads/` directory exists and has write permissions
3. **SSE not working**: Ensure `monitor_processes.php` is running
4. **Database connection**: Verify `.env` file and MySQL service status
5. **Push notifications**: Check VAPID keys in `.env`