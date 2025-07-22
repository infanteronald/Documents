/**
 * 🤖 NEON CONQUEST - Enemy AI System
 * Sistema de Inteligencia Artificial para enemigos
 * Día 2: Enemigos que hacen el juego adictivo
 */

class EnemyManager {
    constructor(game) {
        this.game = game;
        this.enemies = [];
        this.spawnTimer = 0;
        this.spawnDelay = 3000; // 3 segundos entre spawns
        this.maxEnemies = 5;
        this.difficultyLevel = 1;
        
        // Tipos de enemigos
        this.enemyTypes = {
            HUNTER: {
                name: 'Hunter',
                color: '#ff0040',
                speed: 2,
                size: 6,
                behavior: 'chase',
                intelligence: 'medium',
                points: 100
            },
            INTERCEPTOR: {
                name: 'Interceptor',
                color: '#ff8000',
                speed: 1.5,
                size: 8,
                behavior: 'intercept',
                intelligence: 'high',
                points: 150
            },
            GUARDIAN: {
                name: 'Guardian',
                color: '#8000ff',
                speed: 1,
                size: 10,
                behavior: 'patrol',
                intelligence: 'low',
                points: 75
            },
            GHOST: {
                name: 'Ghost',
                color: '#ffffff',
                speed: 3,
                size: 4,
                behavior: 'phase',
                intelligence: 'very_high',
                points: 200
            }
        };
    }
    
    update(deltaTime) {
        // Gestionar spawn de enemigos
        this.updateSpawning(deltaTime);
        
        // Actualizar cada enemigo
        this.enemies.forEach(enemy => {
            this.updateEnemy(enemy, deltaTime);
        });
        
        // Remover enemigos muertos
        this.enemies = this.enemies.filter(enemy => enemy.alive);
        
        // Actualizar dificultad
        this.updateDifficulty();
        
        // Verificar colisiones con jugador
        this.checkPlayerCollisions();
    }
    
    updateSpawning(deltaTime) {
        this.spawnTimer += deltaTime;
        
        if (this.spawnTimer >= this.spawnDelay && this.enemies.length < this.maxEnemies) {
            this.spawnEnemy();
            this.spawnTimer = 0;
            
            // Reducir delay progresivamente
            this.spawnDelay = Math.max(1500, this.spawnDelay - 50);
        }
    }
    
    spawnEnemy() {
        // Seleccionar tipo de enemigo basado en dificultad
        const typeKeys = Object.keys(this.enemyTypes);
        let selectedType;
        
        if (this.difficultyLevel <= 2) {
            selectedType = this.enemyTypes.HUNTER;
        } else if (this.difficultyLevel <= 4) {
            selectedType = Math.random() < 0.7 ? this.enemyTypes.HUNTER : this.enemyTypes.GUARDIAN;
        } else if (this.difficultyLevel <= 6) {
            const rand = Math.random();
            if (rand < 0.4) selectedType = this.enemyTypes.HUNTER;
            else if (rand < 0.7) selectedType = this.enemyTypes.GUARDIAN;
            else selectedType = this.enemyTypes.INTERCEPTOR;
        } else {
            // Nivel alto - todos los tipos incluido Ghost
            const rand = Math.random();
            if (rand < 0.3) selectedType = this.enemyTypes.HUNTER;
            else if (rand < 0.5) selectedType = this.enemyTypes.GUARDIAN;
            else if (rand < 0.8) selectedType = this.enemyTypes.INTERCEPTOR;
            else selectedType = this.enemyTypes.GHOST;
        }
        
        // Generar posición de spawn (bordes del canvas)
        const spawnSide = Math.floor(Math.random() * 4);
        let x, y;
        
        switch (spawnSide) {
            case 0: // Top
                x = Math.random() * this.game.config.width;
                y = -20;
                break;
            case 1: // Right
                x = this.game.config.width + 20;
                y = Math.random() * this.game.config.height;
                break;
            case 2: // Bottom
                x = Math.random() * this.game.config.width;
                y = this.game.config.height + 20;
                break;
            case 3: // Left
                x = -20;
                y = Math.random() * this.game.config.height;
                break;
        }
        
        const enemy = {
            id: Date.now() + Math.random(),
            type: selectedType,
            x: x,
            y: y,
            targetX: x,
            targetY: y,
            velocity: { x: 0, y: 0 },
            alive: true,
            health: 100,
            lastPathUpdate: 0,
            pathUpdateInterval: 500, // Recalcular path cada 500ms
            state: 'spawning',
            stateTimer: 0,
            trail: [], // Para algunos tipos de enemigos
            specialTimer: 0,
            isPhasing: false, // Para Ghost
            lastPlayerPos: { x: 0, y: 0 },
            patrolTarget: null, // Para Guardian
            predictedPos: { x: 0, y: 0 } // Para Interceptor
        };
        
        this.enemies.push(enemy);
        
        // Efecto de spawn
        this.createSpawnEffect(x, y, selectedType.color);
        
        console.log(`👾 ${selectedType.name} spawned at (${Math.round(x)}, ${Math.round(y)})`);
    }
    
    updateEnemy(enemy, deltaTime) {
        enemy.stateTimer += deltaTime;
        
        // Actualizar comportamiento según tipo
        switch (enemy.type.behavior) {
            case 'chase':
                this.updateHunterBehavior(enemy, deltaTime);
                break;
            case 'intercept':
                this.updateInterceptorBehavior(enemy, deltaTime);
                break;
            case 'patrol':
                this.updateGuardianBehavior(enemy, deltaTime);
                break;
            case 'phase':
                this.updateGhostBehavior(enemy, deltaTime);
                break;
        }
        
        // Aplicar movimiento
        this.applyMovement(enemy, deltaTime);
        
        // Mantener enemigo dentro de bounds (con margen)
        this.constrainToBounds(enemy);
        
        // Actualizar trail si es necesario
        this.updateEnemyTrail(enemy);
    }
    
    updateHunterBehavior(enemy, deltaTime) {
        // Hunter: Persigue directamente al jugador
        const player = this.game.player;
        
        // Calcular distancia al jugador
        const dx = player.x - enemy.x;
        const dy = player.y - enemy.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        // Si está muy cerca, reducir velocidad para dar oportunidad al jugador
        let speedMultiplier = 1;
        if (distance < 50) {
            speedMultiplier = 0.7;
        }
        
        // Mover hacia el jugador
        if (distance > 5) {
            enemy.targetX = player.x;
            enemy.targetY = player.y;
            
            const moveX = (dx / distance) * enemy.type.speed * speedMultiplier;
            const moveY = (dy / distance) * enemy.type.speed * speedMultiplier;
            
            enemy.velocity.x = moveX;
            enemy.velocity.y = moveY;
        } else {
            enemy.velocity.x *= 0.8;
            enemy.velocity.y *= 0.8;
        }
    }
    
    updateInterceptorBehavior(enemy, deltaTime) {
        // Interceptor: Predice la posición futura del jugador
        const player = this.game.player;
        
        // Calcular velocidad del jugador
        const playerVel = {
            x: player.x - enemy.lastPlayerPos.x,
            y: player.y - enemy.lastPlayerPos.y
        };
        
        // Predecir posición futura del jugador
        const predictionTime = 60; // frames adelante
        enemy.predictedPos.x = player.x + (playerVel.x * predictionTime);
        enemy.predictedPos.y = player.y + (playerVel.y * predictionTime);
        
        // Limitar predicción a bounds
        enemy.predictedPos.x = Math.max(0, Math.min(this.game.config.width, enemy.predictedPos.x));
        enemy.predictedPos.y = Math.max(0, Math.min(this.game.config.height, enemy.predictedPos.y));
        
        // Mover hacia posición predicha
        const dx = enemy.predictedPos.x - enemy.x;
        const dy = enemy.predictedPos.y - enemy.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        if (distance > 5) {
            const moveX = (dx / distance) * enemy.type.speed;
            const moveY = (dy / distance) * enemy.type.speed;
            
            enemy.velocity.x = moveX;
            enemy.velocity.y = moveY;
        }
        
        enemy.lastPlayerPos.x = player.x;
        enemy.lastPlayerPos.y = player.y;
    }
    
    updateGuardianBehavior(enemy, deltaTime) {
        // Guardian: Patrulla área y persigue si el jugador se acerca
        const player = this.game.player;
        const dx = player.x - enemy.x;
        const dy = player.y - enemy.y;
        const distanceToPlayer = Math.sqrt(dx * dx + dy * dy);
        
        // Si el jugador está cerca, perseguir
        if (distanceToPlayer < 100) {
            enemy.state = 'chasing';
            const moveX = (dx / distanceToPlayer) * enemy.type.speed;
            const moveY = (dy / distanceToPlayer) * enemy.type.speed;
            
            enemy.velocity.x = moveX;
            enemy.velocity.y = moveY;
        } else {
            // Patrullar
            enemy.state = 'patrolling';
            
            // Crear objetivo de patrulla si no existe
            if (!enemy.patrolTarget || 
                Math.abs(enemy.x - enemy.patrolTarget.x) < 10 && 
                Math.abs(enemy.y - enemy.patrolTarget.y) < 10) {
                
                enemy.patrolTarget = {
                    x: Math.random() * this.game.config.width,
                    y: Math.random() * this.game.config.height
                };
            }
            
            // Mover hacia objetivo de patrulla
            const pdx = enemy.patrolTarget.x - enemy.x;
            const pdy = enemy.patrolTarget.y - enemy.y;
            const patrolDistance = Math.sqrt(pdx * pdx + pdy * pdy);
            
            if (patrolDistance > 5) {
                const moveX = (pdx / patrolDistance) * enemy.type.speed * 0.5;
                const moveY = (pdy / patrolDistance) * enemy.type.speed * 0.5;
                
                enemy.velocity.x = moveX;
                enemy.velocity.y = moveY;
            }
        }
    }
    
    updateGhostBehavior(enemy, deltaTime) {
        // Ghost: Puede atravesar territorio conquistado y es más impredecible
        const player = this.game.player;
        
        // Alternar entre visible e invisible (phasing)
        enemy.specialTimer += deltaTime;
        if (enemy.specialTimer > 2000) { // Cada 2 segundos
            enemy.isPhasing = !enemy.isPhasing;
            enemy.specialTimer = 0;
        }
        
        // Movimiento errático hacia el jugador
        if (enemy.stateTimer > 300) { // Cambiar dirección cada 300ms
            const dx = player.x - enemy.x;
            const dy = player.y - enemy.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            
            if (distance > 5) {
                // Añadir algo de aleatoriedad al movimiento
                const randomAngle = (Math.random() - 0.5) * Math.PI * 0.5;
                const angle = Math.atan2(dy, dx) + randomAngle;
                
                enemy.velocity.x = Math.cos(angle) * enemy.type.speed;
                enemy.velocity.y = Math.sin(angle) * enemy.type.speed;
            }
            
            enemy.stateTimer = 0;
        }
    }
    
    applyMovement(enemy, deltaTime) {
        // Aplicar velocidad con suavizado
        enemy.x += enemy.velocity.x;
        enemy.y += enemy.velocity.y;
        
        // Aplicar fricción
        enemy.velocity.x *= 0.95;
        enemy.velocity.y *= 0.95;
    }
    
    constrainToBounds(enemy) {
        const margin = 50;
        
        // Si el enemigo sale muy lejos, teletransportarlo
        if (enemy.x < -margin) enemy.x = this.game.config.width + margin - 10;
        if (enemy.x > this.game.config.width + margin) enemy.x = -margin + 10;
        if (enemy.y < -margin) enemy.y = this.game.config.height + margin - 10;
        if (enemy.y > this.game.config.height + margin) enemy.y = -margin + 10;
    }
    
    updateEnemyTrail(enemy) {
        // Algunos enemigos dejan rastro
        if (enemy.type.name === 'Ghost' || enemy.type.name === 'Hunter') {
            enemy.trail.push({ x: enemy.x, y: enemy.y, time: Date.now() });
            
            // Limitar longitud del trail
            if (enemy.trail.length > 20) {
                enemy.trail.shift();
            }
            
            // Remover puntos viejos
            enemy.trail = enemy.trail.filter(point => Date.now() - point.time < 1000);
        }
    }
    
    updateDifficulty() {
        // Aumentar dificultad basada en puntuación y tiempo
        const scoreLevel = Math.floor(this.game.game.score / 1000);
        const timeLevel = Math.floor(this.game.game.time / 10000);
        this.difficultyLevel = Math.max(1, scoreLevel + timeLevel);
        
        // Ajustar parámetros según dificultad
        this.maxEnemies = Math.min(8, 3 + Math.floor(this.difficultyLevel / 2));
        
        // Aumentar velocidad de enemigos ligeramente
        Object.values(this.enemyTypes).forEach(type => {
            const speedBonus = 1 + (this.difficultyLevel * 0.1);
            type.currentSpeed = type.speed * speedBonus;
        });
    }
    
    checkPlayerCollisions() {
        const player = this.game.player;
        const playerRadius = 8;
        
        this.enemies.forEach(enemy => {
            // Verificar colisión con jugador
            const dx = player.x - enemy.x;
            const dy = player.y - enemy.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            const collisionDistance = playerRadius + enemy.type.size;
            
            if (distance < collisionDistance) {
                this.handlePlayerEnemyCollision(enemy);
            }
            
            // Verificar colisión con trail del jugador
            if (player.isDrawing && player.trail.length > 0) {
                this.checkTrailCollision(enemy, player.trail);
            }
        });
    }
    
    checkTrailCollision(enemy, trail) {
        // Verificar si el enemigo toca el trail del jugador
        for (let i = 0; i < trail.length - 1; i++) {
            const trailPoint1 = trail[i];
            const trailPoint2 = trail[i + 1];
            
            const distanceToLine = this.pointToLineDistance(
                enemy.x, enemy.y,
                trailPoint1.x, trailPoint1.y,
                trailPoint2.x, trailPoint2.y
            );
            
            if (distanceToLine < enemy.type.size + 2) {
                this.handleTrailCollision(enemy);
                break;
            }
        }
    }
    
    pointToLineDistance(px, py, x1, y1, x2, y2) {
        const dx = x2 - x1;
        const dy = y2 - y1;
        const length = Math.sqrt(dx * dx + dy * dy);
        
        if (length === 0) return Math.sqrt((px - x1) ** 2 + (py - y1) ** 2);
        
        const t = Math.max(0, Math.min(1, ((px - x1) * dx + (py - y1) * dy) / (length * length)));
        const projection = { x: x1 + t * dx, y: y1 + t * dy };
        
        return Math.sqrt((px - projection.x) ** 2 + (py - projection.y) ** 2);
    }
    
    handlePlayerEnemyCollision(enemy) {
        // Colisión directa con enemigo - Game Over
        this.createCollisionEffect(enemy.x, enemy.y, enemy.type.color);
        this.game.gameOver();
        
        console.log(`💀 Player hit by ${enemy.type.name}!`);
    }
    
    handleTrailCollision(enemy) {
        // El enemigo cortó el trail - resetear trail y perder energía
        this.game.player.trail = [];
        this.game.player.isDrawing = false;
        this.game.player.energy -= 20;
        
        // Crear efecto visual
        this.createTrailCutEffect(enemy.x, enemy.y);
        
        // Bonus de puntos por esquivar
        this.game.game.score += enemy.type.points;
        
        console.log(`⚡ Trail cut by ${enemy.type.name}! Energy: ${this.game.player.energy}`);
        
        if (this.game.player.energy <= 0) {
            this.game.gameOver();
        }
    }
    
    createSpawnEffect(x, y, color) {
        // Efecto visual de spawn
        for (let i = 0; i < 15; i++) {
            this.game.effects.glowParticles.push({
                x: x + (Math.random() - 0.5) * 20,
                y: y + (Math.random() - 0.5) * 20,
                vx: (Math.random() - 0.5) * 100,
                vy: (Math.random() - 0.5) * 100,
                life: 1.5,
                opacity: 1,
                color: color,
                size: Math.random() * 3 + 2
            });
        }
    }
    
    createCollisionEffect(x, y, color) {
        // Efecto de colisión fatal
        for (let i = 0; i < 30; i++) {
            this.game.effects.glowParticles.push({
                x: x,
                y: y,
                vx: (Math.random() - 0.5) * 200,
                vy: (Math.random() - 0.5) * 200,
                life: 2,
                opacity: 1,
                color: color,
                size: Math.random() * 6 + 2
            });
        }
        
        // Explosion effect
        this.game.effects.explosions.push({
            x: x,
            y: y,
            radius: 0,
            speed: 150,
            life: 1,
            opacity: 1,
            color: color
        });
    }
    
    createTrailCutEffect(x, y) {
        // Efecto cuando se corta el trail
        for (let i = 0; i < 10; i++) {
            this.game.effects.glowParticles.push({
                x: x,
                y: y,
                vx: (Math.random() - 0.5) * 150,
                vy: (Math.random() - 0.5) * 150,
                life: 1,
                opacity: 1,
                color: '#ffff00',
                size: Math.random() * 4 + 1
            });
        }
    }
    
    render(ctx) {
        this.enemies.forEach(enemy => {
            this.renderEnemy(ctx, enemy);
        });
    }
    
    renderEnemy(ctx, enemy) {
        // Renderizar trail si existe
        if (enemy.trail && enemy.trail.length > 1) {
            ctx.lineWidth = 2;
            ctx.strokeStyle = enemy.type.color + '80'; // Semi transparente
            ctx.shadowColor = enemy.type.color;
            ctx.shadowBlur = 5;
            
            ctx.beginPath();
            ctx.moveTo(enemy.trail[0].x, enemy.trail[0].y);
            
            for (let i = 1; i < enemy.trail.length; i++) {
                ctx.lineTo(enemy.trail[i].x, enemy.trail[i].y);
            }
            
            ctx.stroke();
            ctx.shadowBlur = 0;
        }
        
        // Modificar rendering si es Ghost y está phasing
        if (enemy.type.name === 'Ghost' && enemy.isPhasing) {
            ctx.globalAlpha = 0.3;
        }
        
        // Renderizar cuerpo del enemigo
        const pulseSize = enemy.type.size + Math.sin(Date.now() * 0.01) * 1;
        
        // Glow exterior
        ctx.shadowColor = enemy.type.color;
        ctx.shadowBlur = 15;
        ctx.fillStyle = enemy.type.color;
        ctx.beginPath();
        ctx.arc(enemy.x, enemy.y, pulseSize, 0, Math.PI * 2);
        ctx.fill();
        
        // Core interior - color diferente según tipo
        ctx.shadowBlur = 0;
        let coreColor = '#ffffff';
        if (enemy.type.name === 'Hunter') coreColor = '#ff6080';
        else if (enemy.type.name === 'Interceptor') coreColor = '#ffa040';
        else if (enemy.type.name === 'Guardian') coreColor = '#a040ff';
        else if (enemy.type.name === 'Ghost') coreColor = '#f0f0f0';
        
        ctx.fillStyle = coreColor;
        ctx.beginPath();
        ctx.arc(enemy.x, enemy.y, pulseSize * 0.4, 0, Math.PI * 2);
        ctx.fill();
        
        // Indicador de comportamiento
        this.renderEnemyIndicator(ctx, enemy);
        
        // Resetear alpha
        ctx.globalAlpha = 1;
    }
    
    renderEnemyIndicator(ctx, enemy) {
        // Pequeños indicadores visuales del comportamiento
        ctx.font = '12px monospace';
        ctx.textAlign = 'center';
        ctx.fillStyle = enemy.type.color;
        
        let indicator = '';
        switch (enemy.type.name) {
            case 'Hunter': indicator = '🎯'; break;
            case 'Interceptor': indicator = '⚡'; break;
            case 'Guardian': indicator = '🛡️'; break;
            case 'Ghost': indicator = enemy.isPhasing ? '👻' : '😈'; break;
        }
        
        ctx.fillText(indicator, enemy.x, enemy.y - enemy.type.size - 10);
    }
    
    clear() {
        this.enemies = [];
        this.spawnTimer = 0;
        this.difficultyLevel = 1;
    }
    
    getEnemyCount() {
        return this.enemies.length;
    }
    
    getDifficultyLevel() {
        return this.difficultyLevel;
    }
}

// Exportar para uso en el juego principal
window.EnemyManager = EnemyManager;