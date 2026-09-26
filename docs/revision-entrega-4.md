# Revisión de la entrega UtenticacionADA4

Fecha: 26 de septiembre de 2026.

Entrega localizada en `C:\wamp64\www\UtenticacionADA4\UtenticacionADA`. También existe `C:\wamp64\www\UtenticacionADA4.zip`.

**Resultado:** contiene una base de navegación y mejoras de frontend, pero no un backend de autenticación operativo. Instalar dependencias, importar SQL y descargar modelos no implementa la lógica que falta. No se integró ni publicó esta entrega durante la revisión; el proyecto original se conserva.

## Aportes aprovechables

- Router de vistas y reescritura de Apache en `public/index.php` y `public/.htaccess`.
- Esquema SQL preliminar, manifiesto Composer y estructura FastAPI.
- Mejoras de presentación, enlace para saltar al contenido y estilos de administración.
- Captura con mensajes de error más específicos y limpieza al salir de la página.
- Extensiones de imagen/audio acordes con MIME en los envíos.
- Función JavaScript de alta WebAuthn que decodifica `excludeCredentials` (todavía no utilizada por la vista de alta).
- Cliente API que obtiene un token antes de las operaciones; el servidor aún no lo almacena ni verifica.

## Bloqueantes comprobados

### 1. El acceso concede Administrator sin validar nada

`public/index.php:52–57` asigna `user_id = 1` y el rol `Administrator` directamente. No comprueba identificador, muestra, patrón, reto ni CSRF. Una petición POST vacía a `/api/auth/verify` produce HTTP 200 y una sesión administrativa.

Debe sustituirse esta rama por verificación real y denegación por defecto. No usarla como autenticación ni exponerla para pruebas con datos reales.

### 2. Rostro y voz son respuestas constantes

`biometric-service/main.py:26–61` devuelve `match: true` y valores fijos. No carga modelos, decodifica muestras ni compara identidades. Las altas de las líneas 63–88 devuelven nombres de plantilla constantes y no extraen ni guardan representaciones.

`verify_service_token` contiene un valor fijo y no está conectada a ninguna ruta mediante dependencias. Falta autenticación efectiva PHP–Python, extracción y comparación reales, validación de medios, límites de recursos, versionado de modelos y cifrado de plantillas.

### 3. CRUD, patrón y WebAuthn de servidor no existen

El router solo implementa respuestas para sesión, capacidades, begin, verify y logout. Las rutas de usuarios, altas/revocaciones e intentos caen en HTTP 404 `NOT_IMPLEMENTED` (`public/index.php:66–67`). No hay servicios PDO ni llamadas PHP al servicio Python. Tampoco hay uso de `password_hash`/`password_verify` ni validación criptográfica WebAuthn en PHP.

El autoload de Composer está comentado. Declarar `lbuchs/webauthn` en `composer.json` no integra su protocolo. `/api/auth/begin` no devuelve opciones WebAuthn, por lo que el flujo de navegador tampoco puede completarse.

### 4. Retos, sesiones y permisos incompletos

- `begin` genera un identificador, pero no persiste el reto ni lo vincula a cuenta, modalidad, propósito o sesión; no devuelve `expiresAt`.
- `/api/session` genera un token CSRF nuevo sin almacenarlo. No hay validación CSRF en las mutaciones.
- No hay rotación de sesión al autenticar, expiración explícita, revalidación de cuenta/rol ni revocación de sesiones.
- No hay limitación compartida de intentos ni protección del último administrador activo.
- Logout acepta GET y no realiza el flujo JSON/limpieza de cookie exigido.
- Las vistas administrativas redirigen al inicio en vez de entregar el 403 requerido; no existen todavía las operaciones administrativas protegidas.

### 5. El SQL no cubre el diseño acordado

`database/schema.sql` contiene cuatro tablas y un administrador fijo `admin01`. Faltan o requieren rediseño:

- Hash de recuperación, versión de sesión y actualización de usuarios.
- Consentimientos y auditoría administrativa.
- Cifrado de plantillas con nonce, versión de llave/modelo/umbral y revocación.
- Unicidad de credenciales y metadatos WebAuthn: contadores, transportes y estado de respaldo.
- Propósito, vínculo de sesión y consumo atómico de retos; el usuario del reto no admite NULL para cuentas inexistentes.
- Digests HMAC de origen/identificador en registros; actualmente se proponen valores directos.

El primer administrador debe crearse con el comando local interactivo solicitado, no mediante un INSERT fijo sin credenciales. No se ejecutó este SQL contra una base existente durante la revisión.

## Integración frontend pendiente

1. El cliente nuevo entrega errores en `res.message`, mientras las vistas conservadas leen `res.error?.message`; se pierden los mensajes del servidor.
2. `PatternInput` selecciona puntos con teclado, pero no llama al callback en ese recorrido. La vista no habilita Verificar ni completa el registro por teclado. Comprobado con seis selecciones: cero notificaciones.
3. El `AbortController` de `auth-flow.js` no pasa su señal a `fetch`; crear/abortar el controlador no cancela las peticiones.
4. El alta no envía `challengeId`, consentimiento verificable ni confirmación de patrón al servidor.
5. La revocación de la vista pasa el nombre de modalidad donde el contrato requiere el ID de credencial.
6. La tabla de usuarios conserva la interpolación de datos del servidor mediante `innerHTML`; debe utilizar texto seguro.
7. El detector de micrófono solo comprueba WebM/Ogg aunque el capturador admite MP4: puede deshabilitar dispositivos compatibles con MP4.
8. Si falla la consulta de capacidades, el cliente habilita todos los métodos; el patrón ignora la configuración global.
9. La función nueva de registro WebAuthn no está conectada al alta existente; esta mantiene su implementación anterior.
10. Falta la pantalla/ruta `/admin/attempts`, además de completar filtros, paginación y recuperación.

## Comprobaciones ejecutadas

| Comprobación | Resultado |
|---|---|
| Sintaxis PHP con ejecutable WAMP 8.4.15 | Los nueve archivos pasan `php -l`. |
| Sintaxis de ocho módulos JavaScript | Pasan `node --check`. |
| Sintaxis Python | `ast.parse` correcto; no demuestra inferencia ni compatibilidad de dependencias. |
| POST vacío a `/api/auth/verify` | HTTP 200, `ok: true`, sesión `Administrator`: fallo bloqueante. |
| POST vacío a `/api/auth/begin` | HTTP 200, reto sin estado persistido en sesión. |
| GET `/api/users` y `/api/access-attempts` | HTTP 404 `NOT_IMPLEMENTED`. |
| GET `/api/session` | Token entregado pero ninguna clave guardada en sesión. |
| Patrón por teclado, seis puntos | Seis puntos internos, cero callbacks. |
| Error sintético del cliente API | Existe `message`; `error.message`, esperado por las vistas, está ausente. |

Las pruebas del router se ejecutaron por CLI con variables de petición controladas y un almacén de sesión en memoria. No abrieron puertos ni tocaron MySQL. Las pruebas JavaScript aislaron métodos con datos sintéticos; no sustituyen pruebas de navegador. No se probaron Apache, HTTPS, teléfono, huella física, inferencia, calidad biométrica ni instalación de dependencias.

No hay `composer.lock`, configuración de ejemplo ni pruebas automatizadas entregadas. `torch`, `torchaudio` y `numpy` no tienen versiones fijadas. El README conserva el estado inicial y no documenta fielmente los archivos nuevos.

## Orden recomendado para completar la entrega

1. Preservar la entrega original y aprovechar sus cambios de interfaz por separado; retirar los éxitos simulados del recorrido de autenticación.
2. Completar esquema/configuración y el núcleo PHP: PDO, altas, roles, sesiones, CSRF, retos, patrón, recuperación, límites y auditoría; probar casos negativos.
3. Integrar la biblioteca WebAuthn en PHP y probar origen, RP ID, firma, usuario y retos de un uso.
4. Implementar el servicio biométrico real y su comunicación autenticada; fijar dependencias/modelos y registrar consentimiento/cifrado/borrado.
5. Corregir los contratos entre módulos y vistas, configurar WAMP/HTTPS y ejecutar recorridos completos con el teléfono y participantes autorizados.

Los pasos de configuración que indicó el compañero siguen siendo necesarios, pero la programación pendiente es sustancial y debe completarse antes de afirmar que la aplicación funciona.

## Identificación de los archivos revisados

SHA-256:

```text
public/index.php
EA17CA7FD3B341B59C0CFEE45DACADA34C06A997BED6B2F297E6A60022EDE562
biometric-service/main.py
6F7E1070622DAD2F9EB78E7FF503030EA7B1F603C459B08E940EB4B79F755103
database/schema.sql
36302EB879FFAD03AB6F6E9E1D2B2149B256D48053D9DDD67727C7DA8B9E02C3
```
