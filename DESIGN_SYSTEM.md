# Luminous Design System
*Documento Base ("Source of Truth") para el desarrollo UI/UX del SaaS.*

Este documento define las reglas de diseño, componentes y medidas fundamentales extraídas de las referencias visuales (Luminous Enterprise Analytics) para asegurar consistencia en todo el sistema.

## 1. Tipografía
- **Fuente Principal:** `Inter` o `Roboto` (sans-serif moderno y limpio).
- **Pesos:**
  - Regular (400) para texto base y descripciones.
  - Medium (500) para botones, enlaces y estados.
  - SemiBold (600) / Bold (700) para encabezados y cifras métricas (KPIs).

## 2. Paleta de Colores

### Colores Principales (Brand)
- **Primary Green (Éxito / CTA Principal):** `#22c55e` (Aprox) - Usado para botones principales, badges de incremento, gráficos de tráfico y el menú activo.
  - Hover: `#16a34a`
  - Active: `#15803d`
- **Dark Slate (Secundario / Botones oscuros):** `#1e293b` o `#334155` - Usado para botones secundarios y textos principales.
- **Red (Peligro / Decremento):** `#ef4444` - Usado para métricas en negativo y botones destructivos.
- **Yellow/Warning:** `#f59e0b` - Usado para estados "Pending" o gráficos secundarios.
- **Teal/Blue:** `#0ea5e9` o `#0f766e` - Usado para variantes en gráficos.

### Fondos y Superficies
- **Fondo General (Body):** `#f8fafc` (Gris muy claro/azulado).
- **Superficies (Cards, Topbar):** `#ffffff` (Blanco puro).
- **Fondo de Menú Lateral (Sidebar):** Gris ultra claro casi blanco `#fcfcfc` o similar, con bordes sutiles.

### Textos
- **Texto Principal (Encabezados, Cifras):** `#0f172a` (Casi negro).
- **Texto Secundario (Descripciones, Labels):** `#64748b` (Gris medio).

## 3. Espaciado (Spacing System)
El sistema utiliza una **base de 8px** para mantener la consistencia vertical y horizontal:
- `8px` (`0.5rem`) - Separación mínima (ej. entre ícono y texto).
- `16px` (`1rem`) - Padding estándar interno (botones, inputs).
- `32px` (`2rem`) - Padding de secciones pequeñas o cards.
- `48px` (`3rem`) - Separación entre bloques de contenido relacionados.
- `64px` (`4rem`) - Separación mayor entre secciones principales.
- `96px` (`6rem`) - Espaciado macro estructural.

## 4. Layout (Estructura Base)
- **Sidebar (Izquierda):** Ancho fijo (aprox 250px). Fondo claro. El elemento activo (ej. "Overview") tiene un fondo casi transparente y un **borde izquierdo grueso de color verde**.
- **Topbar:** Altura fija (aprox 64px - 72px). Contiene el buscador global, notificaciones, ayuda y el perfil de usuario a la derecha.
- **Main Content:** Fondo gris claro (`#f8fafc`). Los elementos principales están contenidos en "Cards" (tarjetas) blancas.

## 5. Componentes Principales

### 5.1 Tarjetas (Cards)
- **Background:** Blanco (`#ffffff`).
- **Border-Radius:** Redondeado generoso (`12px` o `16px`).
- **Border:** Borde sólido muy sutil (ej. `1px solid #e2e8f0`) en lugar de sombras fuertes.
- **Sombra (Shadow):** Opcional o muy suave (`0 1px 3px rgba(0,0,0,0.05)`).

### 5.2 Botones (Buttons)
Todos los botones tienen bordes redondeados (`border-radius: 6px` u `8px`) y un padding cómodo (`8px 16px`).
- **Botón Primario (Verde):** Fondo verde sólido, texto blanco.
- **Botón Secundario (Dark):** Fondo azul/gris oscuro, texto blanco.
- **Botón Peligro (Rojo):** Fondo rojo sólido, texto blanco.
- **Botón Outline/Ghost:** Fondo transparente, texto gris oscuro, cambia de color al hacer hover.

### 5.3 Badges / Etiquetas de Estado
Se usan en las tablas (ej. "Active", "Pending") y en los KPIs (ej. "+12.5%").
- **Positivo (Verde):** Fondo verde súper claro, texto verde oscuro.
- **Neutro/Pendiente (Amarillo):** Fondo amarillo/naranja claro, texto amarillo oscuro.
- **Negativo (Rojo):** Fondo rojo claro, texto rojo oscuro.
- **Info (Azul/Gris):** Fondo gris claro, texto gris oscuro (ej. badges de "Enterprise Plus").

### 5.4 Gráficos y Tablas
- **Tablas (Tables):** 
  - Deben estar envueltas en un contenedor `.table-responsive` con `overflow-x: auto;`.
  - Estructura limpia: `border-bottom` simple para separar filas, sin bordes verticales gruesos.
  - Textos secundarios en gris (`var(--text-muted)`).
  - **Responsive (Mobile):** En pantallas pequeñas (`< 768px`), las tablas se transforman en "Tarjetas" (Cards). Las filas (`<tr>`) se muestran como bloques individuales (display: block) con borde, fondo y sombra (`box-shadow`). Cada celda (`<td>`) utiliza un pseudo-elemento `::before` alimentado por el atributo HTML `data-label="..."` para mostrar dinámicamente el nombre de la columna a la izquierda del valor, logrando una experiencia 100% "App-like" sin scroll horizontal.
  - Cabeceras con texto pequeño (`0.75rem`), en mayúsculas, espaciado de letras amplio (tracking) y color gris. Filas con padding generoso (`16px` vertical).
- **Gráficos:** Curvas suaves (splines), gradientes sutiles debajo de la línea principal del gráfico.

### 5.5 Modales (Dialogs)
- **Estructura Interna:** Configurados con flexbox (`flex-direction: column`) y una altura máxima (`max-height: 90vh`). 
- **Cuerpo con Scroll:** Si el contenido es largo, el `.modal-body` hace scroll interno (`overflow-y: auto`), manteniendo el Header y Footer siempre visibles.
- **Footer Pegajoso (Sticky):** Los botones de acción se mantienen fijos en la parte inferior del modal, asegurando acceso constante.
- **Border-Radius:** Muy pronunciado (`28px`) para dar una sensación fluida.
- **Responsividad (Móvil):** En pantallas pequeñas (`<768px`), el modal se ancla en la parte inferior de la pantalla (Bottom Sheet), eliminando el padding del overlay y usando `margin: 0` en el contenido para que ocupe el 100% del ancho y se pegue completamente al borde inferior. Solo muestra bordes redondeados en la parte superior (`28px 28px 0 0`). La animación cambia para deslizarse desde abajo.
- **Cajas de Información (Callouts):** Cajas internas con fondo azul/gris extra claro y un borde izquierdo grueso (aprox `3px` a `4px`) que hace de acento visual.
- **Botones de Acción (Footer):** Alineados a la derecha, con forma de píldora (`border-radius: 9999px`). Botón secundario con fondo gris claro y texto oscuro; botón principal con color sólido.

## 6. Iconografía
Íconos de trazo fino o tipo "Solid" limpios (ej. Phosphor Icons, Heroicons). Usar colores apagados (`#64748b`) por defecto, y colorear el ícono con el color principal de la tarjeta cuando actúa como indicador principal.

---
*Este documento debe ser actualizado conforme se creen nuevos componentes y se tomen decisiones de diseño globales.*
