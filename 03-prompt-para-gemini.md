# Instrucciones para Gemini: interfaz y experiencia

Copiar el texto a partir de “Encargo” y adjuntar `02-contrato-de-integracion.md`. La arquitectura PHP con servicio local Python y WebAuthn está confirmada por el equipo. Usar el contrato como base común de integración; este documento no es código ya construido.

Sobre el nombre del modelo: Google documenta `gemini-3.1-pro-preview` con nivel de razonamiento `high`. El nombre visible y los ajustes dependen del editor utilizado. Seleccionar el modelo disponible y nivel alto desde su configuración; escribir “high” en el prompt no garantiza modificar ese ajuste. [Documentación oficial](https://ai.google.dev/gemini-api/docs/thinking).

---

## Encargo

Actúa como diseñador de producto y desarrollador frontend. Construye la interfaz funcional de **“Acceso · Autenticación multimodal”**, una aplicación académica en PHP servida por WAMP. La interfaz está en español y debe servir para demostrar cuatro métodos reales: reconocimiento facial, verificación de voz, huella mediante WebAuthn y patrón de acceso. Hay dos roles: `Administrator` y `Gestor`.

Lee primero el contrato adjunto. Trabaja en las vistas de presentación, CSS y JavaScript de interfaz. PHP, SQL, sesiones, permisos y verificación biométrica son responsabilidad del backend que construirá Codex. Propón cualquier cambio de contrato antes de implementarlo de forma incompatible.

### 1. Tecnología y límites

- HTML semántico, CSS propio con variables y JavaScript nativo en módulos.
- Plantillas `.php` de presentación, integrables en WAMP; sin React, Next.js ni servidor de desarrollo Node obligatorio.
- Recursos locales para que la interfaz no dependa de CDN, fuentes remotas o una conexión externa durante la demostración. Si añades un recurso de terceros, documenta licencia y versión.
- Se permite SVG sencillo para iconos. Los iconos acompañan texto; no reemplazan etiquetas.
- No introducir Supabase, Firebase, bases de datos en el navegador ni autenticación propia del cliente.
- `fetch` al mismo origen, siguiendo las rutas y formatos del contrato. No inventar endpoints.
- No guardar contraseñas, patrones, muestras, plantillas o tokens de sesión en localStorage/sessionStorage.
- No modificar código de autorización para facilitar la vista previa.

### 2. Dirección visual

Crear una interfaz universitaria sobria, precisa y legible, centrada en seleccionar un método y completar una tarea. Nombre provisional: “Acceso”. Usar marca tipográfica y un símbolo simple de cuatro opciones; no inventar un escudo institucional.

Propuesta de tokens, ajustable solo para mejorar contraste:

| Uso | Valor |
|---|---|
| Fondo | `#F5F7FA` |
| Superficie | `#FFFFFF` |
| Texto principal | `#172033` |
| Texto secundario | `#526078` |
| Acción principal | `#174EA6` |
| Éxito | `#176B45` |
| Error | `#B42318` |
| Bordes | `#D8DEE8` |

Tipografía de sistema compatible con Windows y teléfonos, cuerpo mínimo de 16 px, escala clara de títulos y ancho de lectura moderado. Espaciado coherente de 4/8/12/16/24/32 px, botones cómodos y foco visible. Bordes suaves de aproximadamente 10–14 px donde ayuden a agrupar controles. Reservar el color fuerte para acciones y selección.

La pantalla debe verse como una aplicación para autenticarse y administrar usuarios. Priorizar instrucciones, estados y navegación; evitar llenar el espacio con estadísticas ficticias, publicidad, porcentajes de confianza inventados o efectos de escaneo sin relación con una captura real.

### 3. Pantallas obligatorias

**A. Entrada y selección de método.** Encabezado breve, aviso “Proyecto académico” y cuatro tarjetas: Reconocimiento facial, Reconocimiento de voz, Huella digital y Patrón de acceso. Cada tarjeta tiene icono, descripción de una línea, estado de disponibilidad y acción. En laptop, cuadrícula 2×2; en móvil, una columna. Seleccionar el método antes de pedir el identificador, respetando el flujo de evaluación.

**B. Verificación.** Mostrar método seleccionado, campo de identificador, controles necesarios, estado y acciones “Verificar”, “Cancelar” y “Cambiar método”. Mantener una acción principal clara. Solicitar permiso de cámara/micrófono solo cuando el usuario lo inicia. Explicar qué se capturará y con qué finalidad antes de activar el dispositivo.

**C. Bienvenida.** Texto exacto “¡Bienvenido, [Nombre del usuario]!” y “Rol: Administrator” o “Rol: Gestor”, usando datos del servidor. Mostrar el método utilizado solo si backend lo proporciona. Incluir “Cerrar sesión”. Para Administrator, acceso a usuarios e intentos; para Gestor, panel personal sencillo. No añadir módulos de negocio que no exige la tarea.

**D. Administración de usuarios.** Tabla con nombre, identificador, rol, estado, modalidades registradas y acciones. Búsqueda, filtros por rol/estado y paginación. Crear, consultar, editar, activar/desactivar y registrar/revocar métodos. Estado vacío con acción “Dar de alta usuario”. En móvil, lista de tarjetas o tabla contenida, sin desbordar toda la pantalla.

**E. Alta y edición.** Nombre, identificador, rol permitido y estado. Separar datos de cuenta de registro de credenciales. Mostrar errores junto al campo; conservar datos no sensibles después de un fallo. El alta no significa que rostro/voz/huella ya estén registrados. Usar una sección de modalidades con estado Pendiente/Registrado/Revocado basado en servidor.

**F. Registro de modalidades.** Flujo guiado para consentimiento y captura del titular. Rostro: tres capturas y guía de iluminación. Voz: tres grabaciones y progreso. Patrón: dibujar y confirmar. Huella: botón que abre el diálogo nativo del dispositivo. Informar éxito únicamente cuando lo confirme PHP.

**G. Intentos de acceso.** Para Administrator: fecha, cuenta cuando esté disponible, método y resultado; filtros y paginación. No mostrar muestras, secretos, cookies o hashes. Diferenciar rechazo y error técnico sin convertir el panel en una consola de infraestructura.

**H. Errores.** Vistas de sesión expirada, 403, servicio temporalmente no disponible y acceso rechazado. Ofrecer acción concreta: volver, reintentar o elegir otro método.

### 4. Comportamiento de cada modalidad

**Rostro:** vista previa de cámara con encuadre; botones iniciar, capturar y repetir. Mostrar imagen real capturada. El frontend no declara una identidad ni una puntuación. Detener cámara al cancelar, navegar o completar. Puede funcionar en laptop o celular si hay dispositivo y permiso.

**Voz:** iniciar y detener grabación, duración visible, indicador de actividad basado en señal real si se implementa y botón repetir. Comprobar soporte de MIME mediante `MediaRecorder.isTypeSupported`. Enviar el Blob con su formato real; no cambiar extensiones para fingir conversión. Texto explicativo: “Compararemos esta grabación con tu registro de voz”. Una transcripción no es la prueba de identidad.

**Huella:** conservar el título requerido y aclarar “Mediante el dispositivo; puede ofrecer otro método de desbloqueo”. Usar WebAuthn real. La interfaz del sistema operativo no puede reemplazarse por una animación que termina en éxito. Si no hay autenticador compatible, explicar la limitación y permitir cambiar de método. Un resultado positivo de disponibilidad no prueba que exista huella; no mostrar esa afirmación.

**Patrón:** 3×3, numeración interna 1–9, seis a nueve puntos sin repetición, sin autoinsertar puntos intermedios. Implementar entrada por arrastre, selección con clic y teclado, borrado y confirmación de alta. Limpiar la secuencia tras enviarla o cancelar. No revelar permanentemente el patrón registrado.

### 5. Estados y accesibilidad

Implementar explícitamente: disponibilidad sin comprobar, listo, solicitando permiso, capturando, procesando, correcto, rechazado, permiso denegado, dispositivo no encontrado, dispositivo ocupado, cancelado, red interrumpida y límite de intentos.

No fingir porcentajes de procesamiento. Usar “Verificando…” mientras haya petición. Evitar doble envío. Si el usuario cambia de método, cancelar y descartar respuestas antiguas.

Etiquetas reales, navegación completa por teclado, foco visible, texto además de color, mensajes con `aria-live`, gestión de foco en diálogos y devolución del foco al cerrarlos. Respetar `prefers-reduced-motion`. Revisar contraste WCAG AA y documentar cualquier limitación, sin afirmar conformidad completa solo por usar colores sugeridos.

Probar anchos de 360, 390, 768 y 1366 px, orientación horizontal y zoom al 200 %. Los controles táctiles deben tener un área cómoda, preferiblemente alrededor de 44 px. Asegurar que el patrón también puede completarse sin arrastre.

### 6. Componentes y archivos

Separar componentes reutilizables: tarjeta de método, alerta, indicador de estado, campo, botón con carga, diálogo, lista/tabla de usuarios, etiqueta de rol y estado de credencial.

Archivos sugeridos: `tokens.css`, `app.css`, `api-client.js`, `auth-flow.js`, `device-capabilities.js`, `capture-face.js`, `capture-voice.js`, `webauthn-client.js`, `pattern-input.js` y `admin-users.js`. Conservar este reparto si encaja en el contrato; evitar un archivo monolítico con todas las pantallas y lógica.

Las vistas PHP reciben variables preparadas por backend. Escapar valores según contexto; no concatenar nombres de usuario mediante `innerHTML`. Si necesitas datos ficticios para evaluar diseños, aislarlos bajo `design-preview/`, con aviso de vista previa y sin rutas que abran una sesión real. Entregar también la integración real o declarar con precisión qué endpoints faltan.

### 7. Orden de trabajo y entrega

1. Leer el contrato y entregar un mapa breve de pantallas con dos esquemas: entrada y administración.
2. Construir tokens, componentes y navegación básica.
3. Implementar selección y las cuatro capturas/interacciones, sin falsos éxitos.
4. Construir administración, alta y estados de registro.
5. Conectar el cliente API y manejar todas las respuestas previstas.
6. Revisar visualmente en escritorio y móvil; corregir errores y entregar capturas si tu entorno lo permite.

Entregar archivos completos, instrucciones de vista previa/integración, lista de endpoints utilizados, comprobaciones realizadas y pendientes reales. La pantalla no está terminada si el único camino disponible es una demostración con respuestas inventadas.

**Criterios de aceptación:** los cuatro métodos tienen estado y errores; la interfaz responde a capacidades reales; el patrón funciona con teclado; no hay éxitos automáticos; los datos de bienvenida vienen de PHP; la administración respeta las respuestas del servidor; no hay desbordamiento móvil; cámara y micrófono se apagan al salir.
