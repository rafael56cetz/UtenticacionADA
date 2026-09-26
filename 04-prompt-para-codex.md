# Instrucciones para Codex: backend, biometría e integración

Copiar el encargo y adjuntar `01-investigacion-y-plan.md` y `02-contrato-de-integracion.md`. El alcance académico ya permite los servicios auxiliares descritos. Este archivo prepara la siguiente fase; la aplicación no se ha creado durante la investigación.

---

## Encargo

Construye una aplicación académica PHP llamada **“Acceso · Autenticación multimodal”** para la actividad IAW-UNI2 ADA 1. Usa WAMP en Windows, MySQL o MariaDB, HTML/CSS y JavaScript nativo. La interfaz la desarrollará Gemini conforme al contrato adjunto. Necesitamos reconocimiento real, no una simulación que conceda acceso automáticamente.

Primero inspecciona el proyecto y el entorno. Preserva trabajo existente. Documenta versiones efectivamente disponibles y no supongas que PHP de consola y PHP de Apache usan la misma configuración. Respeta las instrucciones del repositorio y el contrato; las decisiones nuevas deben quedar escritas.

**Decisión de alcance confirmada por el equipo:** se permite un servicio auxiliar Python local para rostro/voz y la demostración de huella mediante WebAuthn. Implementa esa arquitectura sin volver a solicitar confirmación de estas decisiones. Verifica por separado su funcionamiento real en WAMP y en el teléfono disponible.

### 1. Objetivo funcional

Seleccionar método → indicar cuenta → capturar prueba → validar usuario en servidor → mostrar “¡Bienvenido, [Nombre del usuario]!” y rol → permitir acciones según permisos.

Los métodos internos son `face`, `voice`, `webauthn` y `pattern`. Usar verificación 1:1, no búsqueda de personas en toda la base. Los roles son exactamente `Administrator` y `Gestor`.

Administrator puede crear, consultar y editar usuarios, asignar rol, activar/desactivar y gestionar sus credenciales. Gestor tiene acceso a su bienvenida y panel personal, sin permisos de administración.

### 2. Estructura y base PHP

- Raíz pública `public/`; secretos, código interno y almacenamiento privado fuera de ella.
- Composer para dependencias, autoload y archivo lock.
- Separar controladores, servicios, acceso a datos y autorización de las vistas.
- Elegir una versión PHP 8.x con soporte y disponible para el equipo; verificar extensiones `pdo_mysql`, OpenSSL, mbstring, sodium, fileinfo y cURL según el código utilizado.
- Elegir MySQL o MariaDB según WAMP; probar migraciones y restricciones en esa versión concreta.
- PDO y consultas parametrizadas, sin SQL formado con valores del usuario. Validar mediante listas permitidas los identificadores SQL que no pueden parametrizarse.
- Configuración de ejemplo sin claves. Usuario de base con permisos limitados.
- Crear primer Administrator mediante comando local interactivo; no publicar instalador ni contraseñas universales.

Implementar el esquema del documento de investigación y producir SQL, diccionario y diagrama consistentes con la versión final. Verificar claves foráneas, unicidad del identificador/credencial y manejo de intentos contra cuentas inexistentes.

### 3. Seguridad transversal

- Validar datos y límites en PHP, además de la validación del navegador.
- Verificar permisos en todas las rutas protegidas. Un Gestor que construya la petición manualmente debe recibir 403.
- Consultar estado/rol vigente y versión de sesión de la cuenta; desactivar debe invalidar accesos ya iniciados.
- Impedir eliminar, desactivar o degradar al último Administrator activo, también ante operaciones concurrentes.
- Usar `password_hash` y `password_verify`, preferir Argon2id disponible y documentar alternativa. Hashes en columnas de 255 caracteres; nunca secretos en claro.
- Incluir un flujo protegido de contraseña de recuperación/administración para cumplir el requisito de protección de contraseñas. No añadir una vía de bypass con credenciales fijas.
- Sesiones estrictas por cookie; renovar ID tras autenticar, HttpOnly, SameSite y Secure en HTTPS. Definir expiración por inactividad y absoluta; logout invalida cookie y estado.
- Token CSRF en operaciones de cambio y autenticación. Escape contextual de salida para prevenir XSS.
- Limitación de intentos por cuenta y origen, compartida entre modalidades. No permitir eludir el bloqueo cambiando de método. Separar cancelaciones/errores de dispositivo de fallos biométricos cuando sea pertinente, manteniendo límites generales de peticiones.
- Mensaje público uniforme ante cuenta inexistente, inactiva o autenticación incorrecta: “Acceso no autorizado”. Guardar motivos privados para diagnóstico.
- Registrar éxitos, fallos, límites y acciones administrativas sin medios, plantillas, contraseñas, patrones, cookies ni claves.
- Toda dependencia caída o respuesta malformada debe cerrar el acceso; nunca degradarse a aprobación automática.

### 4. Servicio biométrico local

Implementar **un solo servicio FastAPI en Python**, en un entorno virtual, accesible únicamente en `127.0.0.1`. Usar una credencial de servicio fuera del repositorio para las llamadas de PHP. No exponer el puerto al celular ni implementar acceso anónimo.

El navegador entrega medios a PHP por HTTPS; PHP controla retos, límites, cuenta y consentimiento, y envía al servicio solo lo necesario. Las plantillas de referencia nunca se devuelven al navegador. Mantener clara la responsabilidad por persistencia/cifrado y evitar que Python escriba arbitrariamente en la base.

El servicio no gestiona roles ni sesiones. Valida tipos, tamaños, duración y resolución; usa nombres temporales generados y borra medios tras procesarlos. Evitar rutas o comandos de sistema tomados de entrada del cliente. Limitar concurrencia/tiempo de inferencia y cargar modelos una vez para no reiniciarlos en cada solicitud.

**Rostro:** OpenCV con YuNet y SFace de `opencv/opencv_zoo`. Exigir un solo rostro, alinear y extraer representación en servidor. Registro inicial con tres capturas. Verificación con muestra nueva, puntuación de similitud y umbral configurado/versionado. Conservar qué modelo y preprocesamiento produjeron cada plantilla. No presentar detección de rostro como identidad ni afirmar prueba de vida inexistente.

**Voz:** SpeechBrain con `speechbrain/spkrec-ecapa-voxceleb`. Captura real con MediaRecorder, decodificación del formato recibido y conversión a mono de 16 kHz. Probar la compatibilidad conjunta de Python, PyTorch, torchaudio y SpeechBrain antes de fijar versiones. Si se necesita FFmpeg, documentar versión y licencia de la distribución utilizada. No renombrar archivos para simular conversión.

Usar varias grabaciones de registro y una nueva para verificar hablante. No sustituir la verificación por texto reconocido, volumen o frecuencia fundamental. Evaluar silencio, duración y señal inválida. El modelo no garantiza defensa ante voz reproducida/clonada; documentarlo. Una frase aleatoria no cuenta como control si no se verifica su contenido.

**Persistencia:** cifrado autenticado de plantillas, llave separada de base/repositorio, nonce y versión de llave. Revocación y borrado por modalidad. La baja de consentimiento revoca el uso de la plantilla correspondiente. No afirmar que un embedding es anónimo.

No entrenar modelos desde cero. Fijar versiones y revisión/checksum de los pesos, con licencias y procedencia. No copiar ramas de desarrollo sin probarlas solo porque aparezcan en un README.

### 5. Huella mediante WebAuthn

Integrar `lbuchs/webauthn`, o justificar una alternativa si una prueba concreta encuentra incompatibilidad. Conservar el lock y verificar disponibilidad de sus extensiones PHP. Reutilizar la biblioteca para el protocolo criptográfico.

- Registro: ceremonia autorizada de creación, ligada al titular y a una operación de alta concreta.
- Acceso: reto impredecible, un uso, expiración, vínculo a sesión/cuenta/método y validación atómica.
- Verificar origen exacto, RP ID, tipo de ceremonia, reto, firma, verificación de usuario y pertenencia de la credencial a la cuenta.
- Solicitar verificación de usuario requerida. Para el flujo dedicado a huella del equipo, preferir autenticador de plataforma compatible sin confundirlo con garantía de sensor.
- Guardar identificador, clave pública y metadatos requeridos, nunca huellas ni claves privadas.
- Manejar base64url y binarios correctamente. Seguir la biblioteca para contadores y credenciales sincronizadas, sin rechazar todo contador cero.
- No permitir registro público sobre un `user_id` arbitrario.

La web no puede garantizar que se usó huella en vez de PIN. La demostración debe realizarse en un equipo físico con sensor y dejar constancia de lo observado. Las pruebas automatizadas con autenticador virtual validan protocolo, no hardware biométrico.

### 6. Patrón

Cuadrícula 3×3, valores 1–9. Secuencia explícita de 6–9 puntos diferentes, sin inserción de puntos intermedios. Canonicalización única en servidor. Alta con confirmación concordante; `password_hash`/`password_verify` para almacenamiento y comprobación. Nunca devolver el patrón registrado. Compartir los límites de intento con otras modalidades.

### 7. HTTPS, WAMP y celular

Preparar instrucciones específicas para el WAMP instalado. Un dominio de prueba estable debe resolver desde laptop y teléfono. Configurar el sitio con HTTPS confiable y `DocumentRoot` en `public/`. No asumir que `localhost` de la laptop funciona desde el celular ni que una IP HTTP habilita cámara y WebAuthn.

Si se utiliza mkcert, explicar confianza del certificado en cada dispositivo, protección de claves privadas y restauración al acabar la prueba. No desactivar controles TLS. Mantener phpMyAdmin y el servicio Python fuera del acceso de la red. Documentar origen exacto y RP ID; registrar credenciales solo después de estabilizarlos.

No publicar mediante túneles ni enviar biometría a proveedores externos sin que el equipo haya elegido expresamente esa alternativa. La propuesta por defecto procesa en la laptop y usa la red local.

### 8. Integración con Gemini

Implementar `02-contrato-de-integracion.md`, con respuestas JSON consistentes y estado de sesión real. Entregar pronto al frontend ejemplos sintéticos de respuestas, no datos biométricos. Revisar juntos cambios de rutas/campos.

No rehacer innecesariamente el diseño visual. Integrar las vistas preservando HTML semántico y módulos de captura. Retirar del recorrido final los adaptadores ficticios de vista previa. Verificar que no existe ningún botón que conceda acceso únicamente desde JavaScript.

### 9. Fases y criterios de salida

1. **Prueba de viabilidad:** PHP/DB listos; registro y acceso WebAuthn en teléfono; prueba exploratoria de rostro/voz entre dos personas con consentimiento. Informar fallos concretos y no confundirla con validación final.
2. **Núcleo:** usuarios, roles, sesiones, patrón, CSRF, límites, recuperación y logs. Casos negativos efectivos.
3. **Biometría:** registros y verificaciones reales, plantillas cifradas y borrado.
4. **Integración visual:** recorridos completos en laptop y celular con errores manejados.
5. **Cierre:** instalación reproducible, documentación fiel, licencias, evidencias y guion de video.

No marcar terminado un módulo que solo tiene interfaz o datos simulados. Si un dispositivo/permiso/entorno impide comprobarlo, indicar exactamente qué se implementó y qué prueba física falta.

### 10. Pruebas requeridas

Cubrir la matriz del informe: cuatro accesos correctos, muestras ajenas, cuenta inexistente/inactiva, modalidad sin registro, Gestor accediendo directamente a administración, revocación de sesión, límites entre métodos, CSRF, entradas SQL/XSS, retos caducados/reutilizados/de otra cuenta, cargas falsas, servicio caído y logout.

Separar datos de inscripción, calibración y prueba. Fijar umbral antes de evaluación final y reportar falsos rechazos/aceptaciones con denominadores reales. Una muestra pequeña no demuestra precisión poblacional. Probar reinstalación siguiendo el manual y documentar equipo/navegador real.

Entregar código, esquema/migraciones, diagrama, instrucciones de instalación probadas, `.env.example` o equivalente sin secretos, dependencias fijadas, licencias/procedencia, pruebas ejecutadas y pendientes honestos. El paquete de entrega no debe contener muestras biométricas del equipo ni claves privadas.
