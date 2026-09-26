# Encargo para el integrante que continúa: voz, experiencia de uso y validación

Puedes copiar el siguiente encargo a tu asistente de programación, junto con la versión actualizada del código.

---

Trabaja en el proyecto académico «Acceso · Autenticación multimodal». Usa PHP 8.3, WAMP, MySQL 8, JavaScript nativo y el servicio Python local existente para rostro y voz. Primero inspecciona el código y lee `docs/manual-para-otro-equipo.md`, `docs/tareas-del-equipo.md`, `04-prompt-para-codex.md`, `02-contrato-de-integracion.md`, `docs/contrato-implementado.md`, `docs/instalacion.md`, `docs/pruebas.md` y `docs/https-y-telefono.md`. Respeta las instrucciones AGENTS.md que existan en tu copia.

## Reparto y forma de trabajar

Tus tareas son T2, T4, T5, comprobar T6 y T7. T1 y el acceso positivo de T3 (WebAuthn) ya fueron atendidos y el titular confirmó huella funcionando: no rehagas ese flujo sin un fallo reproducible y coordinación. Las pruebas negativas físicas faltantes quedan en T7. El código y el manual de T6 están preparados; empieza instalando tu copia, base y certificados propios. Trabaja por fases. Presenta el diagnóstico de cada problema antes de su corrección y al cerrar cada fase informa cambios, pruebas y pendientes; espera a que el equipo seleccione la siguiente fase.

Antes de comenzar, comprueba que recibiste el backend integrado, no solamente el frontend antiguo de GitHub. Deben existir `src/AuthService.php`, `src/WebAuthnService.php`, `biometric-service/main.py`, `public/assets/js/pages-auth.js`, `composer.lock` y la documentación de instalación. Si falta la integración, pide al responsable el código actualizado; no la sustituyas por simulaciones. Las dependencias, modelos y configuración privada se preparan siguiendo el manual. No copies cuentas ni plantillas biométricas reales de otra computadora.

## Lo que sabemos

- El titular ya entró al panel de administrador y comprobó el patrón.
- Rostro y voz funcionaron en laptop: aceptaron al titular y rechazaron a su hermano en pruebas exploratorias.
- En POCO X7, el rostro permitió acceder; la voz no permitió entrar. No se conoce aún la causa.
- El teléfono alcanza `https://acceso.ada.test:8443` mediante un proxy LAN restringido. La resolución DNS directa falló. En otra instalación las IP, certificados y reglas deben corresponder a su propia red.
- La cuenta original no tenía ninguna llave WebAuthn al comenzar T1. Después de guiar el registro y comprobar conectividad, el titular confirmó **patrón, huella y rostro funcionando en el teléfono**. Faltan pruebas negativas físicas y versiones; no confundir acceso exitoso reportado con validación exhaustiva.
- Último trabajo: preparar esta entrega documental y publicar el código existente, sin nuevas correcciones funcionales. Siguiente trabajo funcional: diagnóstico de voz en Android, después de instalar tu entorno.
- Crea tu propia CA y exporta su `.cer` para tu teléfono. El `.cer` de Rafael en Drive no hace confiable un certificado firmado por una CA nueva; no pedir ni copiar sus claves privadas. La URL se conserva y la IP del proxy cambia según tu red.
- Las pruebas automáticas no demuestran precisión biométrica ni funcionamiento de un sensor físico.

## Primera fase: T2, diagnóstico de voz

1. Identifica las versiones del teléfono y navegador y reproduce con consentimiento usando una cuenta de prueba local. Usa HTTPS confiable.
2. Revisa `public/assets/js/capture-voice.js`, `pages-auth.js`, `pages-enroll.js`, `src/BiometricClient.php`, `src/AuthService.php` y el servicio Python.
3. Comprueba permiso, inicio/fin de grabación, tamaño, tipo real del audio y duración. Actualmente el cliente detiene a los 10 segundos; el servidor acepta de 3 a 12 y la interfaz recomienda de 5 a 8. Verifica estos valores en la versión recibida.
4. Distingue: audio ausente/inválido, silencio o duración, error de decodificación, servicio no disponible, rechazo de comparación o límite compartido de intentos. Usa códigos internos y requestId sin exponer biometría ni secretos. El mensaje público puede ser genérico y no identifica por sí solo la causa.
5. Compara capturas controladas en laptop y teléfono, con condiciones anotadas. Hablar más tiempo no garantiza una muestra mejor.
6. Entrega causa, evidencia, archivos implicados y propuesta concreta. No bajes el umbral para hacer pasar una prueba ni vuelvas a registrar muestras sin explicar por qué.

## Segunda fase: T4, corrección de voz

Aplica la corrección acordada y prueba formatos reales del teléfono. Comprueba captura y limpieza del micrófono, repetición y cancelación. Separa los datos de inscripción, calibración y evaluación; si se propone cambiar umbrales, necesita su propio protocolo y resultados. Anota números de intentos genuinos/ajenos y rechazos/aceptaciones. No afirmes protección contra grabaciones o voz clonada.

## T5: mensajes

Aclara cuándo el dispositivo está disponible, cuándo termina la grabación y cómo recuperar un error. «Listo para iniciar» no prueba que una cuenta tenga un método registrado. No agregues consultas públicas que revelen existencia de cuentas o credenciales. Coordina cambios de textos o archivos compartidos con T3.

## T6: entrega reproducible

Comprueba instalación en otra computadora con el manual: PHP CLI y Apache correctos, Composer, base local, entorno Python, pesos verificados y servicios. No ejecutes pruebas destructivas sobre la base real ni uses éxitos simulados. `tests/http-smoke.php --local-fixtures` se preparó para HTTP local y no debe ejecutarse sin revisar sus restricciones cuando el origen activo es HTTPS.

Documenta el arranque, HTTPS, conectividad del teléfono y restauración de la configuración de red. El proxy local solo permite el sitio; una posible interferencia con proveedores de llaves requiere evidencia y coordinación con T3. No abras MySQL, el microservicio ni phpMyAdmin a la red. No desactives TLS ni publiques biometría mediante túneles.

Prepara una entrega revisable que excluya `.env`, `storage/`, bases reales, muestras, plantillas, claves privadas y dependencias/modelos descargados. Conserva lockfiles y procedencia de los modelos. No publiques ni sobrescribas trabajo remoto sin coordinar la entrega con el responsable.

## T7: validación independiente

Con cuentas y participantes autorizados, registra para cada caso: ID, dispositivo/navegador, modalidad, precondición, pasos, esperado, observado, aprobado/fallido/bloqueado, requestId cuando exista y fecha. No registres contraseñas, patrones, llaves privadas ni muestras.

Cubre los cuatro accesos correctos, muestras ajenas, método sin registro, cuenta inexistente/inactiva, permisos de Gestor, cierre de sesión, revocación, retos vencidos/repetidos, cancelación, permisos de medios denegados, servicio caído y límites entre métodos. Coordina la prueba física WebAuthn con el responsable de T3 y registra si Android ofreció huella o PIN. Distingue evidencia automática de reportes y pruebas presenciales.

## Entregable al terminar cada fase

- Tarea realizada y causa comprobada.
- Archivos modificados y motivo.
- Pruebas ejecutadas y resultados, con límites de la evidencia.
- Pasos para reproducir cualquier fallo restante.
- Qué falta y qué necesita acción del titular.

No marques una tarea como completada si solamente existen pantallas navegables o pruebas simuladas. Conserva lo que ya funciona y limita los cambios a la fase acordada.
