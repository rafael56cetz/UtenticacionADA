# Contrato de integración propuesto · versión 1

Este documento acompaña los prompts de Codex y Gemini. Define decisiones de proyecto; todavía no describe endpoints implementados. Una modificación requiere actualizar este archivo y ambos lados de la integración.

**Alcance confirmado:** el equipo permite PHP en WAMP con un servicio biométrico local Python y huella mediante WebAuthn. No hace falta solicitar nuevamente aprobación de estas dos decisiones; su funcionamiento en los dispositivos reales debe verificarse durante la implementación.

## Tecnología y responsabilidad

- PHP sobre WAMP: fuente de verdad para autenticación, sesión, roles y datos.
- MySQL o MariaDB: elegir uno para la instalación final y probar el SQL allí.
- HTML, CSS y módulos JavaScript nativos: interfaz. No requiere React, Next.js ni un servidor Node.
- Servicio Python local: extracción/comparación facial y vocal. Solo PHP tiene acceso.
- Origen único HTTPS, configurable mediante `APP_ORIGIN`; rutas API relativas al mismo origen. Si la app vive bajo una subruta, definir `APP_BASE_PATH` y un constructor único de URL. Preferir VirtualHost con raíz `public/`.
- Valores internos de método: `face`, `voice`, `webauthn`, `pattern`.
- Roles: exactamente `Administrator` y `Gestor`.

## Regla de autenticación

Flujo: elegir método → introducir identificador → solicitar reto → obtener evidencia → verificar en PHP → renovar sesión → consultar sesión → mostrar bienvenida.

El cliente nunca asigna un rol ni crea un usuario autenticado. El éxito únicamente procede del servidor. No guardar tokens de sesión en localStorage. Usar cookie de sesión HttpOnly y solicitudes del mismo origen con credenciales.

Una petición en curso lleva identificador local. Si la persona cambia de método o cancela, abortar solicitudes/capturas y descartar respuestas tardías. Detener todos los tracks al cerrar el flujo. Mantener la cámara encendida después de navegar es un defecto.

## Formato común

Respuestas JSON, incluido error, con este esquema:

```json
{
  "ok": false,
  "code": "ACCESS_DENIED",
  "message": "Acceso no autorizado",
  "data": null,
  "requestId": "identificador-generado-por-el-servidor"
}
```

`requestId` sirve para diagnóstico, no para autenticar. PHP controla el código y el mensaje público. No enviar trazas SQL, rutas privadas, plantillas, hashes, llaves, puntuaciones de referencia o motivos que revelen la existencia de una cuenta.

| HTTP | Uso |
|---|---|
| 200 | Consulta o verificación correcta. |
| 201 | Alta de un recurso. |
| 400 / 422 | Formato o datos inválidos. |
| 401 | Autenticación rechazada o sesión ausente. |
| 403 | Operación no autorizada / CSRF inválido. |
| 409 | Conflicto, como identificador duplicado en administración. |
| 429 | Límite de intentos; usar `Retry-After`. |
| 503 | Dependencia biométrica no disponible. |

Las distinciones internas entre cuenta inexistente, inactiva y muestra incorrecta deben permanecer en el registro. El alta autenticada sí puede mostrar “Identificador ya registrado”.

## Sesión y capacidades

### GET /api/session

Devuelve `data.authenticated`, `data.user` y `data.csrfToken`. Antes del login puede crear una sesión anónima restringida para CSRF y retos. Si no hay autenticación, `user` es null. Si la hay:

```json
{
  "authenticated": true,
  "user": {"id": 7, "name": "Usuario de prueba", "role": "Gestor"},
  "csrfToken": "valor-aleatorio"
}
```

Enviar el token como `X-CSRF-Token` en todas las operaciones de cambio. Actualizarlo tras autenticar o renovar sesión. `POST /api/logout` invalida la sesión y limpia su cookie.

### GET /api/capabilities

Informa métodos habilitados globalmente por configuración; no recibe identificador ni revela registro de un usuario. La capacidad del dispositivo se determina en el navegador.

| Método | Detección y estados |
|---|---|
| Rostro | API disponible + solicitud de permiso al pulsar; procesar errores de cámara ausente, ocupada o denegada. |
| Voz | API disponible + permiso de micrófono; comprobar formatos de MediaRecorder. |
| Huella/dispositivo | WebAuthn disponible + comprobación de autenticador de plataforma. Resultado positivo no demuestra sensor de huella. |
| Patrón | Disponible mediante ratón, tacto y teclado. |

Estado inicial `unknown`: no anunciar “sin cámara” antes de comprobarlo. `enumerateDevices` puede estar limitado antes del permiso. Mostrar opciones no disponibles con explicación y posibilidad de reintentar; no decidir por user-agent.

## Autenticación

### POST /api/auth/begin

Entrada JSON: `{ "identifier": "cuenta", "method": "face" }`.

Salida de éxito estructural: `data.challengeId`, `data.expiresAt` y, para WebAuthn, `data.publicKeyOptions` serializadas con base64url. El servidor liga el reto a cuenta, método, propósito y sesión y aplica los controles de intentos. Una propuesta inicial es caducidad de dos minutos, ajustable tras pruebas.

La forma de responder para cuentas inexistentes no debe convertirse en un buscador de usuarios. Documentar y probar esa condición también para `allowCredentials` de WebAuthn. No devolver mensajes públicos de “huella no registrada para esa cuenta”.

### POST /api/auth/verify

Usar `multipart/form-data` construido mediante `FormData` —sin fijar manualmente su Content-Type— para unificar medios y datos pequeños:

| Campo | Contenido |
|---|---|
| `challengeId` | Identificador devuelto por begin. |
| `method` | Método seleccionado; debe coincidir con el reto. |
| `payload` | Texto JSON: patrón o credencial WebAuthn; `{}` en rostro/voz. |
| `sample` | Archivo de imagen para rostro o audio para voz; ausente en los otros métodos. |

Patrón: `payload = {"sequence":[1,2,5,4,7,8]}`. Numeración 1–9 de izquierda a derecha y de arriba abajo. De 6 a 9 puntos distintos; ninguna inserción automática de puntos intermedios. Validar en ambos lados.

WebAuthn: `payload` contiene la credencial serializada, incluidos `id`, `rawId`, `type`, `response.clientDataJSON`, `response.authenticatorData`, `response.signature`, `response.userHandle` si existe y extensiones pertinentes. Binarios como base64url; decodificar y validar en PHP con la biblioteca. No inventar una firma ni aceptar un booleano de validación del cliente.

Rostro: imagen realmente capturada, con límites de tamaño y resolución. Voz: formato soportado realmente por el navegador. El servicio normaliza tras decodificar. No cambiar una extensión `.webm` por `.wav` para fingir conversión. Evaluar FFmpeg u otro decodificador según la combinación probada; fijar la dependencia si se necesita.

Una verificación correcta consume el reto de forma atómica, comprueba que la cuenta sigue activa, regenera la sesión y devuelve `data.redirectTo` con ruta local permitida. La pantalla siguiente carga la sesión desde PHP. Un fallo nunca crea sesión. Un reto no evita por sí solo reenvío de una muestra biométrica grabada.

## Administración y registro de credenciales

Todas estas rutas comprueban el rol en PHP; los botones ocultos no son el control de acceso.

| Ruta | Propósito |
|---|---|
| GET /api/users | Listar y buscar con paginación; sin secretos. |
| POST /api/users | Crear identifier, name, role y estado inicial; validar lista de campos permitidos. |
| GET /api/users/{id} | Detalle y estados de modalidades, sin plantillas ni hashes. |
| PATCH /api/users/{id} | Editar nombre y rol; cambios auditados. |
| PATCH /api/users/{id}/status | Activar/desactivar e invalidar sesiones según corresponda. |
| POST /api/users/{id}/enroll/{method}/begin | Crear una ceremonia de registro autorizada. |
| POST /api/users/{id}/enroll/{method}/finish | Registrar evidencia y vincularla al usuario del reto. |
| DELETE /api/users/{id}/credentials/{credentialId} | Revocar/borrar una credencial autorizada. |
| GET /api/access-attempts | Consultar intentos con paginación y filtros. |

Enrolamiento de rostro/voz: varias muestras, consentimiento por modalidad y control de calidad. Usar campos `samples[]` en lugar de una muestra de autenticación. En patrón, exigir confirmación concordante y guardar únicamente el hash. En WebAuthn, `begin` genera opciones de creación y `finish` valida `attestationObject` y `clientDataJSON`.

El administrador inicia el alta, pero la captura corresponde al titular. Para registrar huella desde el celular puede usarse la sesión administrativa supervisada en ese equipo; cerrarla al terminar. Una alternativa de enlace temporal de alta solo debe implementarse con token de un uso, alcance limitado y expiración, nunca con un `user_id` público como autorización.

Las contraseñas de recuperación se provisionan por un flujo protegido definido por backend, con hash y sin devolverlas en consultas. Si se incorpora entrada de recuperación, debe estar documentada, limitada y separada visualmente de los cuatro métodos principales.

## Organización de archivos y propiedad

```text
public/                  # raíz servida por Apache
  index.php              # entrada de PHP / router
  assets/css/            # Gemini
  assets/js/             # Gemini para UI; integración revisada por Codex
views/                   # plantillas de presentación, fuera de raíz pública
src/                     # PHP: Codex/backend
config/                  # configuración sin secretos versionados
database/                # SQL y migraciones
biometric-service/       # servicio local y pruebas de modelos
storage/private/         # fuera de la raíz pública; no versionar datos reales
docs/                    # manual, evidencias, licencias y este contrato
tests/                   # pruebas funcionales y de seguridad pertinentes
design-preview/          # opcional: ejemplos ficticios aislados, no desplegados
```

Regla de entrega: UI revisada → integración con API → pruebas con datos autorizados → evidencias → documentación final. Una pantalla que funciona únicamente en `design-preview/` no satisface la autenticación funcional.
