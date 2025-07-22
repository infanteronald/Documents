/**
 * 🌈 NEON CONQUEST - Game Engine
 * Motor del juego más adictivo de 2025
 * Día 1: Core Engine + Movement + Territory System
 */

class NeonConquest {
    constructor() {
        this.canvas = null;
        this.ctx = null;
        this.gameState = 'loading'; // loading, playing, paused, gameOver
        
        // Configuración del juego
        this.config = {
            width: 0,
            height: 0,
            targetFPS: 60,
            playerSpeed: 3,
            trailWidth: 4,
            glowIntensity: 15
        };
        
        // Estado del jugador
        this.player = {
            x: 0,
            y: 0,
            trail: [], // Array de puntos del rastro
            color: '#00ffff',
            energy: 100,
            speed: 3,
            isDrawing: false
        };
        
        // Estado del juego
        this.game = {
            score: 0,
            territory: 0,
            level: 1,
            time: 0,
            particles: [],
            powerups: [],
            streak: 0, // Racha de conquistas sin morir
            multiplier: 1 // Multiplicador de puntos
        };
        
        // Control de input
        this.input = {
            mouse: { x: 0, y: 0, isDown: false },
            touch: { x: 0, y: 0, isDown: false },
            keys: new Set()
        };
        
        // Sistema de territorio
        this.territory = {
            conquered: new Set(), // Píxeles conquistados
            currentArea: 0,
            totalArea: 0,
            grid: null,
            gridSize: 10
        };
        
        // Efectos visuales
        this.effects = {
            trails: [],
            explosions: [],
            glowParticles: []
        };
        
        // Audio context (para música reactiva)
        this.audio = {
            context: null,
            gainNode: null,
            oscillator: null,
            isEnabled: false
        };
        
        this.lastTime = 0;
        this.animationId = null;
        
        // Sistema de enemigos IA
        this.enemyManager = null;
    }
    
    // Inicialización del juego
    init() {
        console.log('🚀 Iniciando Neon Conquest...');
        
        this.setupCanvas();
        this.setupEventListeners();
        this.setupAudio();
        this.initializeGame();
        this.startGameLoop();
        
        this.gameState = 'playing';
        console.log('✅ Juego inicializado correctamente');
    }
    
    setupCanvas() {
        this.canvas = document.getElementById('gameCanvas');
        this.ctx = this.canvas.getContext('2d');
        
        // Configurar tamaño responsive
        this.resizeCanvas();
        window.addEventListener('resize', () => this.resizeCanvas());
        
        // Configuración del canvas para mejor renderizado
        this.ctx.imageSmoothingEnabled = true;
        this.ctx.imageSmoothingQuality = 'high';
    }
    
    resizeCanvas() {
        const maxWidth = window.innerWidth - 40;
        const maxHeight = window.innerHeight - 40;
        const aspectRatio = 16/9;
        
        let width = maxWidth;
        let height = maxWidth / aspectRatio;
        
        if (height > maxHeight) {
            height = maxHeight;
            width = maxHeight * aspectRatio;
        }
        
        this.canvas.width = width;
        this.canvas.height = height;
        this.config.width = width;
        this.config.height = height;
        
        // Reinicializar grid de territorio
        this.initializeTerritoryGrid();
        
        // Reposicionar jugador si está fuera de bounds
        if (this.player.x > width || this.player.y > height) {
            this.resetPlayerPosition();
        }
    }
    
    setupEventListeners() {
        // Mouse events
        this.canvas.addEventListener('mousedown', (e) => this.handleMouseDown(e));
        this.canvas.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        this.canvas.addEventListener('mouseup', (e) => this.handleMouseUp(e));
        this.canvas.addEventListener('mouseleave', (e) => this.handleMouseUp(e));
        
        // Touch events
        this.canvas.addEventListener('touchstart', (e) => this.handleTouchStart(e));
        this.canvas.addEventListener('touchmove', (e) => this.handleTouchMove(e));
        this.canvas.addEventListener('touchend', (e) => this.handleTouchEnd(e));
        
        // Keyboard events
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));
        document.addEventListener('keyup', (e) => this.handleKeyUp(e));
        
        // Prevent context menu
        this.canvas.addEventListener('contextmenu', (e) => e.preventDefault());
    }
    
    setupAudio() {
        try {
            this.audio.context = new (window.AudioContext || window.webkitAudioContext)();
            this.audio.gainNode = this.audio.context.createGain();
            this.audio.gainNode.connect(this.audio.context.destination);
            this.audio.gainNode.gain.setValueAtTime(0.1, this.audio.context.currentTime);
            this.audio.isEnabled = true;
        } catch (e) {
            console.warn('Audio no disponible:', e);
            this.audio.isEnabled = false;
        }
    }
    
    initializeGame() {
        // Inicializar posición del jugador
        this.resetPlayerPosition();
        
        // Inicializar grid de territorio
        this.initializeTerritoryGrid();
        
        // Inicializar sistema de enemigos
        this.enemyManager = new EnemyManager(this);
        
        // Reset de estadísticas
        this.game.score = 0;
        this.game.territory = 0;
        this.game.level = 1;
        this.game.time = 0;
        this.game.streak = 0;
        this.game.multiplier = 1;
        
        // Reset de jugador
        this.player.energy = 100;
        
        // Limpiar arrays
        this.player.trail = [];
        this.game.particles = [];
        this.effects.trails = [];
        this.effects.explosions = [];
        this.effects.glowParticles = [];
        
        console.log('🎮 Juego inicializado con sistema de enemigos IA');
    }
    
    resetPlayerPosition() {
        this.player.x = this.config.width * 0.1;
        this.player.y = this.config.height * 0.5;
        this.player.trail = [];
        this.player.isDrawing = false;
    }
    
    initializeTerritoryGrid() {
        const gridWidth = Math.ceil(this.config.width / this.territory.gridSize);
        const gridHeight = Math.ceil(this.config.height / this.territory.gridSize);
        
        this.territory.grid = Array(gridHeight).fill().map(() => Array(gridWidth).fill(0));
        this.territory.totalArea = gridWidth * gridHeight;
        this.territory.conquered = new Set();
        this.territory.currentArea = 0;
    }
    
    // Game Loop Principal
    startGameLoop() {
        const gameLoop = (currentTime) => {
            if (this.gameState === 'playing') {
                const deltaTime = currentTime - this.lastTime;
                this.update(deltaTime);
                this.render();
            }
            
            this.lastTime = currentTime;
            this.animationId = requestAnimationFrame(gameLoop);
        };
        
        this.animationId = requestAnimationFrame(gameLoop);
    }
    
    update(deltaTime) {
        // Actualizar tiempo de juego
        this.game.time += deltaTime;
        
        // Actualizar jugador
        this.updatePlayer(deltaTime);
        
        // Actualizar sistema de enemigos IA
        if (this.enemyManager) {
            this.enemyManager.update(deltaTime);
        }
        
        // Actualizar sistema de territorio
        this.updateTerritory();
        
        // Actualizar efectos visuales
        this.updateEffects(deltaTime);
        
        // Actualizar partículas
        this.updateParticles(deltaTime);
        
        // Actualizar nivel y multiplicadores
        this.updateGameProgression();
        
        // Actualizar UI
        this.updateUI();
        
        // Generar efectos de audio reactivo
        this.updateAudio();
    }
    
    updatePlayer(deltaTime) {
        // Obtener posición objetivo del input
        let targetX = this.input.mouse.x || this.input.touch.x;
        let targetY = this.input.mouse.y || this.input.touch.y;
        
        // Si hay input activo, mover hacia el objetivo
        if (this.input.mouse.isDown || this.input.touch.isDown) {
            const dx = targetX - this.player.x;
            const dy = targetY - this.player.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            
            if (distance > 2) {
                const moveX = (dx / distance) * this.player.speed;
                const moveY = (dy / distance) * this.player.speed;
                
                this.player.x += moveX;
                this.player.y += moveY;
                
                // Crear trail
                this.addTrailPoint(this.player.x, this.player.y);
                this.player.isDrawing = true;
                
                // Generar partículas de movimiento
                this.createMovementParticles();
                
                // Verificar colisiones con bordes
                this.checkBoundaryCollision();
            }
        } else {
            // Si no hay input, finalizar trail si existe
            if (this.player.isDrawing && this.player.trail.length > 0) {
                this.finishTrail();
            }
        }
        
        // Mantener jugador dentro de bounds
        this.player.x = Math.max(5, Math.min(this.config.width - 5, this.player.x));
        this.player.y = Math.max(5, Math.min(this.config.height - 5, this.player.y));
    }
    
    addTrailPoint(x, y) {
        this.player.trail.push({ x, y, time: this.game.time });
        
        // Limitar longitud del trail
        if (this.player.trail.length > 1000) {
            this.player.trail.shift();
        }
    }
    
    finishTrail() {
        if (this.player.trail.length > 10) {
            this.calculateTerritory();
            this.createTrailEffect();
        }
        
        this.player.trail = [];
        this.player.isDrawing = false;
    }
    
    calculateTerritory() {
        // Implementación simple de flood fill para calcular territorio conquistado
        if (this.player.trail.length < 3) return;
        
        // Crear polígono del trail
        const polygon = this.player.trail.map(point => [point.x, point.y]);
        
        // Calcular área usando Shoelace formula
        let area = 0;
        for (let i = 0; i < polygon.length; i++) {
            const j = (i + 1) % polygon.length;
            area += polygon[i][0] * polygon[j][1];
            area -= polygon[j][0] * polygon[i][1];
        }
        area = Math.abs(area) / 2;
        
        // Convertir a puntos de territorio
        const territoryPoints = Math.floor(area / 100);
        const points = Math.round(territoryPoints * 10 * this.game.multiplier);
        this.game.score += points;
        
        // Actualizar territorio conquistado
        this.markConqueredTerritory(polygon);
        
        // Efectos visuales de conquista
        this.createConquestEffect(polygon);
        
        console.log(`🏆 ¡Territorio conquistado! +${territoryPoints} puntos`);
    }
    
    markConqueredTerritory(polygon) {
        // Marcar puntos dentro del polígono como conquistados
        const minX = Math.max(0, Math.floor(Math.min(...polygon.map(p => p[0])) / this.territory.gridSize));
        const maxX = Math.min(this.territory.grid[0].length - 1, Math.ceil(Math.max(...polygon.map(p => p[0])) / this.territory.gridSize));
        const minY = Math.max(0, Math.floor(Math.min(...polygon.map(p => p[1])) / this.territory.gridSize));
        const maxY = Math.min(this.territory.grid.length - 1, Math.ceil(Math.max(...polygon.map(p => p[1])) / this.territory.gridSize));
        
        let conqueredCount = 0;
        
        for (let y = minY; y <= maxY; y++) {
            for (let x = minX; x <= maxX; x++) {
                const pointX = x * this.territory.gridSize + this.territory.gridSize / 2;
                const pointY = y * this.territory.gridSize + this.territory.gridSize / 2;
                
                if (this.isPointInPolygon(pointX, pointY, polygon)) {
                    const gridKey = `${x},${y}`;
                    if (!this.territory.conquered.has(gridKey)) {
                        this.territory.conquered.add(gridKey);
                        this.territory.grid[y][x] = 1;
                        conqueredCount++;
                    }
                }
            }
        }
        
        this.territory.currentArea = this.territory.conquered.size;
        this.game.territory = Math.round((this.territory.currentArea / this.territory.totalArea) * 100);
    }
    
    isPointInPolygon(x, y, polygon) {
        let inside = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            if (((polygon[i][1] > y) !== (polygon[j][1] > y)) &&
                (x < (polygon[j][0] - polygon[i][0]) * (y - polygon[i][1]) / (polygon[j][1] - polygon[i][1]) + polygon[i][0])) {
                inside = !inside;
            }
        }
        return inside;
    }
    
    updateTerritory() {
        // Actualizar porcentaje de territorio
        this.game.territory = Math.round((this.territory.currentArea / this.territory.totalArea) * 100);
    }
    
    updateEffects(deltaTime) {
        // Actualizar trails de efectos
        this.effects.trails = this.effects.trails.filter(trail => {
            trail.life -= deltaTime * 0.001;
            trail.opacity = trail.life;
            return trail.life > 0;
        });
        
        // Actualizar explosiones
        this.effects.explosions = this.effects.explosions.filter(explosion => {
            explosion.radius += explosion.speed * deltaTime * 0.001;
            explosion.life -= deltaTime * 0.001;
            explosion.opacity = explosion.life;
            return explosion.life > 0;
        });
        
        // Actualizar partículas de glow
        this.effects.glowParticles = this.effects.glowParticles.filter(particle => {
            particle.x += particle.vx * deltaTime * 0.001;
            particle.y += particle.vy * deltaTime * 0.001;
            particle.life -= deltaTime * 0.001;
            particle.opacity = particle.life;
            return particle.life > 0;
        });
    }
    
    updateParticles(deltaTime) {
        this.game.particles = this.game.particles.filter(particle => {
            particle.x += particle.vx * deltaTime * 0.001;
            particle.y += particle.vy * deltaTime * 0.001;
            particle.life -= deltaTime * 0.001;
            return particle.life > 0;
        });
    }
    
    updateGameProgression() {
        // Actualizar nivel basado en puntuación
        const newLevel = Math.floor(this.game.score / 2000) + 1;
        if (newLevel > this.game.level) {
            this.game.level = newLevel;
            
            // Aumentar multiplicador de puntos
            this.game.multiplier = 1 + (this.game.level - 1) * 0.2;
            
            // Efectos visuales de level up
            this.createLevelUpEffect();
            
            // Regenerar energía parcialmente
            this.player.energy = Math.min(100, this.player.energy + 25);
            
            console.log(`🎉 ¡Level Up! Nivel ${this.game.level} - Multiplicador: x${this.game.multiplier.toFixed(1)}`);
        }
        
        // Actualizar racha de conquistas
        if (this.game.territory > 0) {
            this.game.streak = Math.floor(this.game.territory / 10);
        }
    }
    
    createLevelUpEffect() {
        // Efecto especial de subida de nivel
        for (let i = 0; i < 50; i++) {
            this.effects.glowParticles.push({
                x: this.player.x + (Math.random() - 0.5) * 100,
                y: this.player.y + (Math.random() - 0.5) * 100,
                vx: (Math.random() - 0.5) * 300,
                vy: (Math.random() - 0.5) * 300,
                life: 3,
                opacity: 1,
                color: '#ffff00',
                size: Math.random() * 6 + 3
            });
        }
        
        // Explosión dorada
        this.effects.explosions.push({
            x: this.player.x,
            y: this.player.y,
            radius: 0,
            speed: 200,
            life: 2,
            opacity: 1,
            color: '#ffd700'
        });
    }
    
    updateUI() {
        // Actualizar estadísticas en UI
        document.getElementById('score').textContent = this.game.score.toLocaleString();
        document.getElementById('territory').textContent = this.game.territory + '%';
        document.getElementById('energy').textContent = Math.round(this.player.energy);
        document.getElementById('level').textContent = this.game.level;
        
        // Mostrar información de enemigos si existe el elemento
        const enemyInfo = document.getElementById('enemyInfo');
        if (enemyInfo && this.enemyManager) {
            const enemyCount = this.enemyManager.getEnemyCount();
            const difficulty = this.enemyManager.getDifficultyLevel();
            enemyInfo.textContent = `${enemyCount} (Dif: ${difficulty})`;
        }
        
        // Mostrar multiplicador si es mayor a 1
        const multiplierInfo = document.getElementById('multiplier');
        if (multiplierInfo && this.game.multiplier > 1) {
            multiplierInfo.textContent = `x${this.game.multiplier.toFixed(1)}`;
            multiplierInfo.style.display = 'inline';
        } else if (multiplierInfo) {
            multiplierInfo.style.display = 'none';
        }
    }
    
    updateAudio() {
        if (!this.audio.isEnabled) return;
        
        // Música reactiva basada en movimiento y territorio
        const baseFreq = 220 + (this.game.territory * 2);
        const volume = 0.05 + (this.player.isDrawing ? 0.05 : 0);
        
        if (volume > 0.05) {
            this.playTone(baseFreq, volume, 0.1);
        }
    }
    
    playTone(frequency, volume, duration) {
        if (!this.audio.isEnabled) return;
        
        try {
            const oscillator = this.audio.context.createOscillator();
            const gainNode = this.audio.context.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audio.gainNode);
            
            oscillator.frequency.setValueAtTime(frequency, this.audio.context.currentTime);
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0, this.audio.context.currentTime);
            gainNode.gain.linearRampToValueAtTime(volume, this.audio.context.currentTime + 0.01);
            gainNode.gain.exponentialRampToValueAtTime(0.001, this.audio.context.currentTime + duration);
            
            oscillator.start(this.audio.context.currentTime);
            oscillator.stop(this.audio.context.currentTime + duration);
        } catch (e) {
            console.warn('Error reproduciendo audio:', e);
        }
    }
    
    createMovementParticles() {
        // Crear partículas de rastro
        this.effects.glowParticles.push({
            x: this.player.x + (Math.random() - 0.5) * 10,
            y: this.player.y + (Math.random() - 0.5) * 10,
            vx: (Math.random() - 0.5) * 50,
            vy: (Math.random() - 0.5) * 50,
            life: 1,
            opacity: 1,
            color: this.player.color,
            size: Math.random() * 3 + 1
        });
    }
    
    createTrailEffect() {
        // Efecto visual cuando se completa un trail
        this.effects.trails.push({
            points: [...this.player.trail],
            life: 2,
            opacity: 1,
            color: '#ff00ff',
            width: 6
        });
    }
    
    createConquestEffect(polygon) {
        // Efecto de explosión en territorio conquistado
        const centerX = polygon.reduce((sum, p) => sum + p[0], 0) / polygon.length;
        const centerY = polygon.reduce((sum, p) => sum + p[1], 0) / polygon.length;
        
        this.effects.explosions.push({
            x: centerX,
            y: centerY,
            radius: 0,
            speed: 100,
            life: 1,
            opacity: 1,
            color: '#ffff00'
        });
        
        // Crear múltiples partículas
        for (let i = 0; i < 20; i++) {
            this.effects.glowParticles.push({
                x: centerX,
                y: centerY,
                vx: (Math.random() - 0.5) * 200,
                vy: (Math.random() - 0.5) * 200,
                life: 2,
                opacity: 1,
                color: '#ffff00',
                size: Math.random() * 4 + 2
            });
        }
    }
    
    checkBoundaryCollision() {
        const margin = 5;
        if (this.player.x <= margin || this.player.x >= this.config.width - margin ||
            this.player.y <= margin || this.player.y >= this.config.height - margin) {
            
            // Collision con borde - perder energía
            this.player.energy -= 1;
            
            if (this.player.energy <= 0) {
                this.gameOver();
            }
        }
    }
    
    render() {
        // Limpiar canvas
        this.ctx.fillStyle = '#000011';
        this.ctx.fillRect(0, 0, this.config.width, this.config.height);
        
        // Renderizar territorio conquistado
        this.renderConqueredTerritory();
        
        // Renderizar efectos
        this.renderEffects();
        
        // Renderizar trail actual del jugador
        this.renderPlayerTrail();
        
        // Renderizar enemigos IA
        if (this.enemyManager) {
            this.enemyManager.render(this.ctx);
        }
        
        // Renderizar jugador
        this.renderPlayer();
        
        // Renderizar partículas
        this.renderParticles();
        
        // Renderizar UI overlay en canvas
        this.renderCanvasUI();
    }
    
    renderConqueredTerritory() {
        this.ctx.fillStyle = 'rgba(0, 255, 255, 0.1)';
        
        for (const gridKey of this.territory.conquered) {
            const [x, y] = gridKey.split(',').map(Number);
            const pixelX = x * this.territory.gridSize;
            const pixelY = y * this.territory.gridSize;
            
            this.ctx.fillRect(pixelX, pixelY, this.territory.gridSize, this.territory.gridSize);
        }
    }
    
    renderPlayerTrail() {
        if (this.player.trail.length < 2) return;
        
        this.ctx.lineWidth = this.config.trailWidth;
        this.ctx.strokeStyle = this.player.color;
        this.ctx.shadowColor = this.player.color;
        this.ctx.shadowBlur = this.config.glowIntensity;
        
        this.ctx.beginPath();
        this.ctx.moveTo(this.player.trail[0].x, this.player.trail[0].y);
        
        for (let i = 1; i < this.player.trail.length; i++) {
            this.ctx.lineTo(this.player.trail[i].x, this.player.trail[i].y);
        }
        
        this.ctx.stroke();
        this.ctx.shadowBlur = 0;
    }
    
    renderPlayer() {
        const pulseSize = 8 + Math.sin(this.game.time * 0.01) * 2;
        
        // Glow exterior
        this.ctx.shadowColor = this.player.color;
        this.ctx.shadowBlur = 20;
        this.ctx.fillStyle = this.player.color;
        this.ctx.beginPath();
        this.ctx.arc(this.player.x, this.player.y, pulseSize, 0, Math.PI * 2);
        this.ctx.fill();
        
        // Core interior
        this.ctx.shadowBlur = 0;
        this.ctx.fillStyle = '#ffffff';
        this.ctx.beginPath();
        this.ctx.arc(this.player.x, this.player.y, pulseSize * 0.3, 0, Math.PI * 2);
        this.ctx.fill();
    }
    
    renderEffects() {
        // Renderizar trails de efectos
        this.effects.trails.forEach(trail => {
            if (trail.points.length < 2) return;
            
            this.ctx.globalAlpha = trail.opacity;
            this.ctx.lineWidth = trail.width;
            this.ctx.strokeStyle = trail.color;
            this.ctx.shadowColor = trail.color;
            this.ctx.shadowBlur = 10;
            
            this.ctx.beginPath();
            this.ctx.moveTo(trail.points[0].x, trail.points[0].y);
            
            for (let i = 1; i < trail.points.length; i++) {
                this.ctx.lineTo(trail.points[i].x, trail.points[i].y);
            }
            
            this.ctx.stroke();
            this.ctx.shadowBlur = 0;
            this.ctx.globalAlpha = 1;
        });
        
        // Renderizar explosiones
        this.effects.explosions.forEach(explosion => {
            this.ctx.globalAlpha = explosion.opacity;
            this.ctx.strokeStyle = explosion.color;
            this.ctx.lineWidth = 3;
            this.ctx.shadowColor = explosion.color;
            this.ctx.shadowBlur = 15;
            
            this.ctx.beginPath();
            this.ctx.arc(explosion.x, explosion.y, explosion.radius, 0, Math.PI * 2);
            this.ctx.stroke();
            
            this.ctx.shadowBlur = 0;
            this.ctx.globalAlpha = 1;
        });
        
        // Renderizar partículas de glow
        this.effects.glowParticles.forEach(particle => {
            this.ctx.globalAlpha = particle.opacity;
            this.ctx.fillStyle = particle.color;
            this.ctx.shadowColor = particle.color;
            this.ctx.shadowBlur = 8;
            
            this.ctx.beginPath();
            this.ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
            this.ctx.fill();
            
            this.ctx.shadowBlur = 0;
            this.ctx.globalAlpha = 1;
        });
    }
    
    renderParticles() {
        this.game.particles.forEach(particle => {
            this.ctx.globalAlpha = particle.life;
            this.ctx.fillStyle = particle.color || '#00ffff';
            this.ctx.beginPath();
            this.ctx.arc(particle.x, particle.y, particle.size || 2, 0, Math.PI * 2);
            this.ctx.fill();
            this.ctx.globalAlpha = 1;
        });
    }
    
    renderCanvasUI() {
        // Renderizar elementos UI directamente en el canvas si es necesario
        // (Por ahora usamos HTML overlay)
    }
    
    // Event Handlers
    handleMouseDown(e) {
        const rect = this.canvas.getBoundingClientRect();
        this.input.mouse.x = e.clientX - rect.left;
        this.input.mouse.y = e.clientY - rect.top;
        this.input.mouse.isDown = true;
        
        // Activar audio context si es necesario
        if (this.audio.isEnabled && this.audio.context.state === 'suspended') {
            this.audio.context.resume();
        }
    }
    
    handleMouseMove(e) {
        const rect = this.canvas.getBoundingClientRect();
        this.input.mouse.x = e.clientX - rect.left;
        this.input.mouse.y = e.clientY - rect.top;
    }
    
    handleMouseUp(e) {
        this.input.mouse.isDown = false;
    }
    
    handleTouchStart(e) {
        e.preventDefault();
        const rect = this.canvas.getBoundingClientRect();
        const touch = e.touches[0];
        this.input.touch.x = touch.clientX - rect.left;
        this.input.touch.y = touch.clientY - rect.top;
        this.input.touch.isDown = true;
        
        // Activar audio context
        if (this.audio.isEnabled && this.audio.context.state === 'suspended') {
            this.audio.context.resume();
        }
    }
    
    handleTouchMove(e) {
        e.preventDefault();
        const rect = this.canvas.getBoundingClientRect();
        const touch = e.touches[0];
        this.input.touch.x = touch.clientX - rect.left;
        this.input.touch.y = touch.clientY - rect.top;
    }
    
    handleTouchEnd(e) {
        e.preventDefault();
        this.input.touch.isDown = false;
    }
    
    handleKeyDown(e) {
        this.input.keys.add(e.code);
        
        // Atajos de teclado
        if (e.code === 'Space') {
            e.preventDefault();
            // Pausa/reanudar
        }
    }
    
    handleKeyUp(e) {
        this.input.keys.delete(e.code);
    }
    
    gameOver() {
        this.gameState = 'gameOver';
        
        // Mostrar pantalla de game over
        const gameOverScreen = document.getElementById('gameOverScreen');
        const finalStats = document.getElementById('finalStats');
        
        finalStats.innerHTML = `
            <div style="margin: 20px 0;">
                <div>🏆 Puntuación Final: <span style="color: #00ffff;">${this.game.score.toLocaleString()}</span></div>
                <div>🌐 Territorio Conquistado: <span style="color: #ff00ff;">${this.game.territory}%</span></div>
                <div>⏱️ Tiempo Jugado: <span style="color: #ffff00;">${Math.round(this.game.time / 1000)}s</span></div>
                <div>📊 Nivel Alcanzado: <span style="color: #00ff00;">${this.game.level}</span></div>
            </div>
        `;
        
        gameOverScreen.style.display = 'block';
        
        console.log('💀 Game Over - Puntuación:', this.game.score);
    }
    
    restart() {
        this.gameState = 'playing';
        document.getElementById('gameOverScreen').style.display = 'none';
        
        // Limpiar enemigos antes de reinicializar
        if (this.enemyManager) {
            this.enemyManager.clear();
        }
        
        this.initializeGame();
        console.log('🔄 Juego reiniciado');
    }
}

// Instancia global del juego
let game;

// Función de inicialización
function initGame() {
    game = new NeonConquest();
    game.init();
}

// Función para reiniciar
function restartGame() {
    if (game) {
        game.restart();
    }
}

// Exportar para uso global
window.NeonConquest = NeonConquest;
window.initGame = initGame;
window.restartGame = restartGame;