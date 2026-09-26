# Revisión inicial del proyecto

Fecha: 25 de septiembre de 2026. Alcance de esta revisión: lectura de los cuatro documentos y del código recibido, preparación del repositorio compartido e inventario local. No constituye una prueba funcional ni una auditoría de seguridad completa.

## Decisiones

1. Conservar el frontend recibido y su diseño; realizar después los ajustes necesarios para cumplir el contrato.
2. Usar `04-prompt-para-codex.md` como encargo y `02-contrato-de-integracion.md` como contrato común. No modificar sus decisiones sin documentarlo.
3. Publicar el código y documentación en un repositorio público, según la indicación del propietario. Excluir secretos y datos biométricos.
4. Compartir primero esta base. El primer commit no declara terminado el backend ni la integración.

## Inventario

Se encontraron ocho vistas PHP, ocho módulos JavaScript, dos hojas CSS y un catálogo estático de componentes. No existían repositorio Git, router PHP, servicios internos, migraciones, manifiestos de dependencias ni pruebas automatizadas.

Directorios de versiones encontrados en WAMP:

| Componente | Versiones en carpetas locales |
|---|---|
| PHP | 8.0.30, 8.1.33, 8.2.29, 8.3.28, 8.4.15, 8.5.0 |
| MySQL | 8.4.7, 9.5.0 |
| MariaDB | 11.4.9 |
| Apache | 2.4.65 |
| Git ejecutado | 2.55.0.windows.5 |

La presencia de una carpeta no demuestra qué versión utiliza Apache, qué servicio está activo ni qué extensiones están habilitadas. Falta comprobar por separado PHP CLI y PHP de Apache, seleccionar la base y ejecutar las migraciones. Estas versiones son observaciones del equipo inicial, no requisitos ya probados para los demás integrantes.

## Ajustes de integración identificados por lectura

| Archivo o área | Trabajo necesario antes de aceptar el recorrido final |
|---|---|
| `views/layout.php` | Cambiar el enlace GET de logout a POST con CSRF; obtener y renovar el token de sesión. |
| `public/assets/js/api-client.js` | Inicializar sesión antes de mutaciones, respetar `ok` del JSON, manejar cancelación y construir URLs según la configuración del despliegue. |
| `views/auth-select.php`, `views/auth-verify.php` | Coordinar reto, caducidad y cancelación; descartar respuestas tardías y consultar sesión real tras autenticar. |
| `public/assets/js/device-capabilities.js` | No concluir que falta cámara/micrófono solo por `enumerateDevices` antes de permisos; consultar capacidades globales del servidor. |
| `public/assets/js/pattern-input.js` | Completar el recorrido por teclado y clic: el callback actual depende de `pointerup` y cada `pointerdown` reinicia la secuencia. |
| `views/admin-users.php` | Insertar nombres e identificadores como texto seguro; completar búsqueda, filtros y paginación. |
| `views/admin-user-form.php` | Separar los campos permitidos de edición/estado, comprobar el resultado de ambas operaciones y usar el identificador real de credencial para revocar. |
| `views/admin-enroll.php`, `public/assets/js/admin-users.js` | Enviar el reto de alta, consentimiento y confirmación de patrón; detener capturas al salir. |
| Alta WebAuthn | Decodificar también los IDs de `excludeCredentials`, conservar metadatos necesarios y validar la ceremonia en PHP. |
| Captura de voz | Conservar extensión y MIME reales: Ogg/MP4 no deben etiquetarse WAV. El indicador actual es una animación, no medición de señal. |
| Intentos y recuperación | Crear los recorridos pendientes con autorización del servidor. |

Estos hallazgos se registran para integrar el frontend existente; no se han aplicado correcciones funcionales en esta entrega inicial.

## Orden del trabajo posterior

1. Verificar PHP/Apache/base y dependencias disponibles; estabilizar HTTPS, origen y RP ID.
2. Probar viabilidad WebAuthn en teléfono y modelos de rostro/voz con participantes que consientan.
3. Implementar usuarios, roles, sesiones, patrón, CSRF, límites, recuperación y auditoría.
4. Implementar ceremonias WebAuthn y servicio biométrico real con cifrado, consentimiento y borrado.
5. Integrar las vistas y ejecutar la matriz positiva/negativa del encargo.
6. Documentar instalación probada, licencias, resultados y pruebas físicas pendientes.

Ningún acceso real, sensor físico, modelo biométrico ni instalación completa se ha verificado en esta revisión inicial.
