# Guía de Migraciones Automáticas de Base de Datos

Este sistema permite que cualquier actualización de la plataforma que requiera modificaciones en la base de datos (nuevas tablas, columnas, índices o registros iniciales) **se aplique de forma 100% automática y no destructiva, protegiendo todos los datos y registros existentes**.

---

## 🛡️ Principios de Seguridad y No Destrucción

1. **Nunca borra datos:** Ninguna migración debe ejecutar `DROP TABLE`, `TRUNCATE` o `DELETE` masivo sobre datos de producción.
2. **Idempotencia:** Cada migración debe poder ejecutarse sin fallar si una tabla o columna ya existe (`IF NOT EXISTS`). El gestor de migraciones además detecta y salta de forma segura errores por duplicados (código 1060: Duplicate column name, etc.).
3. **Control de Ejecución Única:** Cada archivo de migración se ejecuta **exactamente una sola vez**. El sistema registra cada migración completada en la tabla `system_migrations`.
4. **Respaldo Automático:** En el centro de actualizaciones (a 1 Clic), el sistema genera un respaldo `.sql` completo antes de aplicar cambios de código o base de datos.
5. **Ultra Rápido (<0.1 ms):** El sistema utiliza un archivo de caché ligero (`database/.migration_cache`) basado en huellas de tiempo y conteo de archivos, evitando sobrecarga de consultas en peticiones normales.

---

## 🚀 ¿Cómo se Aplican las Migraciones?

Existen tres formas en las que una migración se ejecuta:

### 1. Automáticamente al Iniciar la Plataforma (Cero Intervención)
En cuanto descargas o clonas una nueva actualización en el servidor, la primera petición que entra a `index.php` detecta los nuevos archivos en `database/migrations/` o `database/sql/` y los ejecuta de manera silenciosa y segura en segundo plano.

### 2. Desde el Centro de Actualizaciones (Panel Web)
Ve a **Configuración > Actualizaciones**:
- Verás la tarjeta **Base de Datos & Migraciones Automáticas**.
- Puedes ver cuántas migraciones están pendientes o aplicadas.
- Botón **"Comprobar BD"** o **"Aplicar Ahora"** para forzar la verificación y ejecución inmediata con registro en vivo en la consola.
- Botón **"Historial"** para desplegar todas las migraciones pasadas con su fecha, número de lote y tiempo de ejecución en milisegundos.

### 3. Desde la Línea de Comandos (CLI)
Si prefieres actualizar mediante terminal o SSH:

```bash
# Ver estado actual de migraciones
php database/migrate.php --status

# Ejecutar todas las migraciones pendientes
php database/migrate.php

# Crear una nueva plantilla de migración
php database/migrate.php --create=agregar_nueva_tabla
```

---

## 📁 ¿Dónde Colocar Nuevos Archivos de Migración?

Cuando prepares una nueva funcionalidad o actualización:

### Opción A (Recomendada): En `database/migrations/`
Crea un archivo con la fecha o timestamp para mantener el orden secuencial:
```
database/migrations/2026_10_06_120000_crear_tabla_notificaciones.sql
```

### Opción B: En `database/sql/`
Cualquier archivo `.sql` ubicado en `database/sql/` (por ejemplo `actualizacion_soporte.sql`) es detectado y ordenado alfabéticamente.

---

## 📝 Buenas Prácticas al Escribir Archivos `.sql`

### 1. Creación de nuevas tablas:
Usa siempre `CREATE TABLE IF NOT EXISTS`:
```sql
CREATE TABLE IF NOT EXISTS `sistema_notificaciones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `mensaje` TEXT NULL,
  `leido` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Agregar nuevas columnas a tablas existentes:
```sql
ALTER TABLE `usuarios` ADD COLUMN `telefono_emergencia` VARCHAR(30) NULL AFTER `telefono`;
```
*(Nota: Si la columna ya existe, el gestor de migraciones captura el aviso y no detiene la ejecución).*

### 3. Insertar registros iniciales (semillas/defaults):
Usa `INSERT IGNORE` o verifica existencia con clave única:
```sql
INSERT IGNORE INTO `configuracion` (`clave`, `valor`) 
VALUES ('notificaciones_activas', '1');
```

---

## 🗄️ Tabla de Control `system_migrations`

El sistema crea y gestiona automáticamente la tabla:
- `id`: Identificador único autoincremental.
- `migration_name`: Nombre del archivo (`.sql` o `.php`).
- `batch`: Número de lote (agrupación de ejecuciones simultáneas).
- `checksum`: Hash sha256 del contenido del archivo para validar integridad.
- `execution_time_ms`: Milisegundos que tomó la ejecución.
- `applied_at`: Fecha y hora exacta de aplicación.
