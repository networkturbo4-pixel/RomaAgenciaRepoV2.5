# ROMA SaaS - UI Design Guidelines & Design System
> Especificaciones de diseño visual, componentes modernos y temas para **Romita AI** y la plataforma.

---

## 1. Tipografía y Fundamentos
- **Tamaño base de fuente:** `13px` (0.8125rem). Todos los cálculos de espaciado y tamaños relativos derivan de esta base para mantener alta densidad de información y elegancia profesional.
- **Familia tipográfica principal:** `'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`.
- **Pesos tipográficos:**
  - Regular: `400` (texto general y párrafos)
  - Medium: `500` (labels, badges, botones secundarios)
  - Semi-Bold: `600` (títulos de tarjetas, subtítulos, botones primarios)
  - Bold: `700` (títulos principales y encabezados)
- **Escala modular:**
  - `xs`: 11px (badges pequeños, metadatos, shortcuts)
  - `sm`: 12px (textos secundarios, descripciones en tarjetas)
  - `base`: 13px (cuerpo de texto, inputs, botones estándar)
  - `md`: 15px (subtítulos, títulos de modales)
  - `lg`: 18px (títulos de sección, encabezados de chat)
  - `xl`: 22px (título hero de bienvenida)
  - `2xl`: 28px (titulares destacados)

---

## 2. Paleta de Colores y Tokens de Tema

### Modo Oscuro (Dark Mode - Default para Romita AI)
- **Fondo principal (App Canvas):** `#0b0f19` (azul petróleo muy profundo, sin caer en negro plano)
- **Fondo de Superficie (Header, Sidebar):** `#111827` con `rgba(255, 255, 255, 0.05)` de borde
- **Superficie de Tarjetas / Composer:** `#1a2234` con sutil resplandor
- **Texto Principal:** `#f8fafc` (blanco con tinte frío)
- **Texto Secundario / Muted:** `#94a3b8`
- **Bordes:** `rgba(255, 255, 255, 0.08)`
- **Acento Primario (Romita Glow):** Linear gradient `135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%`
- **Brillo de Foco (Glow):** `rgba(99, 102, 241, 0.25)`

### Modo Claro (Light Mode)
- **Fondo principal (App Canvas):** `#f8fafc`
- **Fondo de Superficie:** `#ffffff`
- **Superficie de Tarjetas / Composer:** `#ffffff` con sombra suave `0 8px 30px rgba(0, 0, 0, 0.04)`
- **Texto Principal:** `#0f172a`
- **Texto Secundario / Muted:** `#64748b`
- **Bordes:** `rgba(0, 0, 0, 0.08)`
- **Acento Primario:** `#4f46e5` con gradiente secundario `#7c3aed`

---

## 3. Principios de Componentes Modernos

### A. Botones e Interacciones
- Bordes redondeados modernos: `radius-md: 8px`, `radius-full: 9999px` para pills.
- Efectos de micro-interacción: elevación de 1px a 2px en `hover`, transiciones suaves de `0.2s cubic-bezier(0.16, 1, 0.3, 1)`.
- Estados activos y de carga con spinners o puntos pulsantes.

### B. Modales y Drawers (Glassmorphism)
- Fondo desenfocado (`backdrop-filter: blur(16px)`).
- Bordes ultra-finos translúcidos (`1px solid rgba(255, 255, 255, 0.1)` en modo oscuro).
- Animación de entrada suave tipo scale + fade.

### C. Chat Feed & Burbujas de Conversación
- **Burbuja de Usuario:** Alineada a la derecha, gradiente moderno violeta-índigo, texto blanco, sombra difusa.
- **Burbuja de Romita (Asistente):** Alineada a la izquierda, fondo de tarjeta suave, borde sutil, soporte completo para Markdown renderizado (títulos, listas con viñetas coloreadas, bloques de código con botón de copiado, tablas estilizadas con hover).
- **Acciones en Mensaje:** Botones flotantes discretos para "Copiar respuesta", "Regenerar" o "Ver formato crudo".

### D. Empty State & Quick Prompt Cards
- Tarjetas de inicio rápido con iconos coloridos, micro-título y descripción que al hacer clic llenan automáticamente el prompt o inician la tarea.
- Aura ambiental suave en el centro para dar sensación viva de IA de última generación.

### E. Composer / Input Dock
- Estilo "Floating Dock" centrado en la parte inferior.
- Textarea auto-ajustable con límite de altura máximo de 180px.
- Indicador visual de tecla rápida (`Shift + Enter` para nueva línea, `Enter` para enviar).
- Botón de envío dinámico que pulsa en `hover` y muestra estado deshabilitado/cargando.

---

## 4. Responsividad (Mobile First)
- **Desktop (>= 1024px):** Historial lateral visible o colapsable con un clic, chat centrado con ancho óptimo de lectura (max 880px para mensaje), layout fluido sin doble scroll.
- **Tablet (768px - 1023px):** Barra superior simplificada, historial en drawer off-canvas.
- **Mobile (< 768px):** Drawer flotante lateral para historial, inputs compactos pero accesibles con el pulgar, tarjetas de sugerencia en carrusel deslizable horizontal.
