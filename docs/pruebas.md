# Evidencias y pendientes

Fecha: 26-09-2026. Entorno y rutas en `instalacion.md`. No se atribuye precisión biométrica a las pruebas sintéticas.

## Ejecutado

| Prueba | Resultado |
|---|---|
| `php tests/run.php` con PHP 8.3.28 | 40 comprobaciones contra MySQL 8.4.7 en una base aleatoria creada y eliminada por la prueba |
| `php tests/http-smoke.php --local-fixtures` | 17 comprobaciones contra Apache real en localhost:8088; elimina únicamente las cuentas sintéticas creadas por ese recorrido |
| `biometric-service/.venv/Scripts/python.exe biometric-service/test_service.py` | 14 comprobaciones contra el servicio real, incluidos WebM/Opus, Ogg/Opus y MP4/AAC sintéticos |
| `php tests/concurrent-admin.php` | 2 comprobaciones: dos procesos simultáneos; uno degrada su cuenta y el otro recibe 409; permanece un administrador activo |
| `python tests/test_dns.py` | 7 comprobaciones del protocolo del DNS local; no sustituyen la conectividad con Android |
| `python tests/https-smoke.py` | 8 comprobaciones con TLS y nombre verificados; cookies seguras, restricciones y redirección desde HTTP |
| `python tests/proxy-smoke.py <IP>` | 7 comprobaciones de transporte local: destino limitado y certificado/nombre validados sin depender del DNS del teléfono |
| PHP Apache y CLI | 8.3.28, seis extensiones requeridas disponibles; ini independientes comprobados |
| Carga de modelos | YuNet/SFace y ECAPA reales, integridad de pesos comprobada; CPU |

PHP cubre CSRF, origen, patrón confirmado/hasheado, permisos Administrator/Gestor, estado de cuenta, caducidad y vínculo de retos, replay, límites compartidos, recuperación, revocación, cifrado autenticado/contexto y logout. WebAuthn usa un autenticador **sintético** EC P-256 que firma retos reales: registro/acceso válidos, firma inválida, ausencia UV, otra cuenta y puerto distinto. No representa un lector de huellas.

HTTP cubre páginas/JS, bloqueo de `.env`, login de recuperación, crear usuario, búsqueda con entrada SQL literal, patrón, roles y revocación de sesión. Los nombres potencialmente HTML quedan como texto JSON; el cliente los coloca con textContent. Esta comprobación no sustituye una prueba XSS en navegador.

Python comprueba token, modelos disponibles, medios falsos, imagen vacía, silencio/duración, ausencia de referencia y extracción real de un vector ECAPA de 192 dimensiones de una señal sintética. En esas pruebas automáticas no se grabaron participantes ni se guardaron muestras humanas. La voz se carga mediante el YAML local verificado y Pretrainer, evitando un fallo de `from_hparams` en SpeechBrain 1.0.3 en Windows al buscar el `custom.py` opcional ausente. La inferencia se realiza sin descargas implícitas.

## Pruebas manuales reportadas por el usuario

- Primer administrador creado y acceso al listado administrativo: confirmado con captura de la pantalla.
- Patrón: el titular confirmó registro, cierre de sesión y nuevo acceso correcto con su patrón.
- Rostro: el titular reportó acceso aceptado con su rostro y rechazo del rostro de un familiar contra su cuenta.
- Voz: el titular aclaró que se rechazó la voz del familiar y se aceptó únicamente la suya contra su cuenta.
- Red móvil: tras el fallo DNS y la prueba por IP con certificado inválido para ese nombre, el titular confirmó acceso por el dominio mediante el proxy local y entrada por rostro desde el POCO. No se recomienda autenticar por IP ni omitir advertencias TLS.
- Voz en POCO: el titular reportó rechazo; falta diagnosticar captura, procesamiento, comparación y límites. El éxito anterior en laptop no demuestra compatibilidad completa con el teléfono.
- WebAuthn en POCO: al retomar T1 la consulta de solo lectura mostró cero credenciales. Después de guiar el alta y revisar la conectividad, el titular **confirmó que funciona el acceso con su huella**; también confirmó nuevamente patrón y rostro. Este resultado es un reporte manual, no una inspección automática del sensor. Faltan las pruebas físicas de revocación/cancelación y anotar navegador/versión; véase `tareas-del-equipo.md`.

Estos resultados proceden del reporte del usuario; no se observaron directamente las capturas biométricas ni se recibieron medios aquí. Son pruebas exploratorias satisfactorias, sin número total de repeticiones, requestIds, versiones de navegador o condiciones controladas documentados. No equivalen a estimaciones de precisión ni a calibración final.

## Pendientes que impiden declarar el 100 %

- Prueba visual completa en Brave/Opera/Zen, laptop y tamaño móvil. La herramienta de navegador disponible falló al iniciar su proceso; no hay evidencia visual automatizada.
- WebAuthn físico en POCO X7: ampliar el acceso correcto reportado con revocación, cancelación y casos negativos documentados. La resolución DNS directa y la portabilidad de la configuración siguen pendientes; el proxy permitió el acceso por dominio.
- Diagnóstico y corrección de voz en teléfono, asignados a la compañera como T2 y T4.
- Ampliar y documentar la evaluación facial/vocal con consentimiento: más intentos genuinos/ajenos, múltiples rostros, mala luz, ruido y reproducción. Los primeros resultados reportados están arriba.
- Calibración y evaluación separadas: umbrales iniciales **experimentales** rostro 0.45 y voz 0.65, sobre similitud coseno media contra tres referencias. No hay estimaciones de FAR/FRR ni prueba de vida.
- Reinstalación completa en una segunda máquina siguiendo el manual; disponibilidad sin internet después de reiniciar.

## Guion de evaluación con personas

Obtener consentimiento antes de capturar, asignar cuentas distintas y registrar tres muestras por modalidad. Reservar muestras independientes para calibración y otras para prueba final. Elegir/versionar el umbral usando solo calibración; no reajustarlo mirando el conjunto final. Si cambia, volver a registrar según la versión de plantilla.

Para cada modalidad y navegador: acceso correcto, muestra de otra persona, cuenta inexistente/inactiva, modalidad sin registrar, reto vencido/repetido, cancelación, permiso denegado, servicio caído y logout. Para Gestor: intentar URL y API de administración directamente; ambos deben negar. Revocar credenciales y comprobar que ya no funcionan. En WebAuthn registrar qué diálogo físico apareció.

Usar una hoja de resultados con: ID de prueba, cuenta sintética del participante, modalidad, navegador/dispositivo, condición, esperado, observado, requestId y fecha; sin patrones, contraseñas, medios ni plantillas. Reportar falsos rechazos/total de intentos genuinos y falsas aceptaciones/total de intentos ajenos con denominadores reales. No generalizar una muestra pequeña a la población.

Video: mostrar origen seguro → alta y consentimiento → acceso de cada método → bienvenida/rol → Gestor bloqueado en administración → un caso negativo → revocación y cierre. Ocultar contraseña y patrón al presentar; pedir autorización expresa de participantes para cualquier imagen o voz que aparezca en el video.
