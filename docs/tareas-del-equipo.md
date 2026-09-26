# Trabajo por fases y reparto

Actualizado: 26 de septiembre de 2026. Codex y el titular atendieron T1 y T3 (WebAuthn). El integrante que recibe el proyecto continuará T2, T4, T5, la comprobación de T6 y T7. El orden numérico no implica ejecutar todas las tareas a la vez.

## Punto exacto de entrega

Último avance funcional: el titular confirmó **patrón, huella y rostro** después del registro y la revisión de conectividad del teléfono. No se modificó el algoritmo ni la validación WebAuthn para conceder el acceso. Último trabajo de esta entrega: documentar la instalación en otra computadora/red y preparar la publicación del estado integrado en GitHub, sin nuevas correcciones funcionales.

**Siguiente paso para quien recibe:** instalar y registrar sus propias cuentas siguiendo [manual para otro equipo](manual-para-otro-equipo.md). Después reproducir y diagnosticar voz en Android (T2). No empezar bajando umbrales ni rehaciendo huella.

## Estado confirmado

| Parte | Evidencia y límite |
|---|---|
| Administrador y patrón | El titular confirmó alta, entrada al panel y nuevo acceso por patrón. |
| Rostro en laptop | Reportó aceptación propia y rechazo de un familiar. |
| Voz en laptop | Reportó aceptación propia y rechazo de un familiar. |
| HTTPS y rostro en POCO X7 | Reportó navegación por el dominio usando proxy local y acceso facial con su rol. |
| Voz en POCO X7 | Reportó que no pudo entrar; causa pendiente de diagnóstico. |
| WebAuthn en POCO X7 | El titular confirmó que funciona con su huella. Faltan cancelación, revocación y otros casos negativos físicos documentados. |

Las pruebas humanas son reportes exploratorios, sin denominadores para estimar precisión. No hay prueba de vida ni defensa garantizada ante reproducciones.

## T1 — Diagnóstico de WebAuthn (Codex)

Consulta histórica de solo lectura, realizada antes del registro exitoso reportado:

- La cuenta activa del titular tenía **cero credenciales WebAuthn**.
- Había un inicio de autenticación WebAuthn, sin consumo del reto.
- No había eventos de inicio ni de finalización de alta WebAuthn en los registros conservados en ese momento.
- Origen: `https://acceso.ada.test:8443`. RP ID: `acceso.ada.test`.
- Apache HTTPS y proxy local están escuchando en la interfaz LAN.

Conclusión inicial: se intentó usar una llave todavía no registrada en la aplicación. Se indicaron los pasos de alta y se comprobó la conectividad. Posteriormente el titular reportó acceso correcto por huella. El error intermedio de Android no quedó caracterizado; no atribuirlo a una causa no verificada.

Estado: T1 cerrada para el bloqueo observado, con acceso posterior confirmado por el titular. No se demostró un defecto del backend que justificara cambiar su validación.

## T3 — Completar registro y acceso físico (Codex y titular)

1. En el POCO, usar el mismo navegador y el dominio HTTPS válido. No entrar por IP ni omitir avisos de certificado.
2. Entrar como administrador por rostro o patrón.
3. Administración de usuarios → Gestionar la cuenta del titular → Huella / dispositivo → Registrar.
4. Aceptar y continuar → Registrar en el dispositivo. El titular completa el diálogo de Android. Registrar qué proveedor y método de desbloqueo ofrece, sin compartir PIN ni secretos.
5. Pulsar **Finalizar registro** inmediatamente y comprobar que el panel muestra **Registrado**. Crear la llave en Android por sí solo no completa su guardado en el servidor.
6. Cerrar sesión e iniciar con Huella / dispositivo y el identificador de esa misma cuenta.
7. Comprobar bienvenida y rol. Documentar cancelación sin acceso. Coordinar una revocación controlada y verificar que la llave revocada no permite entrar; conservar otro método de acceso y volver a registrar si se necesita para la demostración.

Si falla, registrar en qué paso ocurrió y el texto exacto del diálogo. No repetir indiscriminadamente: los límites de intentos se comparten entre métodos. Los retos vencen a los 120 segundos; volver a comenzar si caducan.

El proxy solo admite el sitio local. Una posible necesidad de conexión del proveedor de llaves sigue siendo una hipótesis; no ampliar el proxy ni desactivar controles antes de obtener evidencia. La web exige verificación del usuario, pero no puede garantizar que Android use huella en lugar de PIN.

Estado: objetivo de acceso por huella confirmado por el titular. T3 no se considera una validación exhaustiva: faltan el recorrido negativo físico de cancelación/revocación, las versiones y la repetición independiente; se transfieren a T7. No hay una nueva lectura de credenciales en esta entrega documental, ni se atribuye al código información sobre el sensor más allá del reporte del titular.

## Tareas del integrante que continúa

| Tarea | Alcance | Criterio de cierre |
|---|---|---|
| T2 | Diagnosticar voz en Android: permisos, captura, duración, formato, petición a PHP, respuesta de Python, comparación y límites. | Causa sustentada con evidencia sin divulgar muestras ni secretos. |
| T4 | Corregir únicamente la causa encontrada en T2. | Pruebas repetidas genuinas y ajenas, resultados documentados en laptop y teléfono. |
| T5 | Aclarar disponibilidad, registro, fin de captura y errores recuperables. Coordinar cualquier cambio en WebAuthn con el responsable de T3. | Instrucciones entendibles, sin revelar a personas anónimas si una cuenta existe o tiene credenciales. |
| T6 | Código integrado y manual preparados en esta entrega. Falta ejecutar y corregir, si procede, los pasos de instalación en la segunda computadora/red. | Base y administrador propios, modelos, HTTPS y teléfono funcionando según el manual; sin secretos, base real ni biometría en la entrega. |
| T7 | Validación independiente de cuatro métodos, permisos, cierre de sesión, revocación y errores. | Matriz con aprobado/fallido/bloqueado, evidencia y reproducción. |

Orden recomendado: descargar e instalar la versión integrada (T6), T2, T4, T5 y T7. Documentar cualquier corrección del manual como parte de T6. No sustituir diagnóstico por bajar umbrales. No ejecutar restauraciones o reinicializaciones sobre la base con usuarios reales.

### Detalle de lo que falta y qué entregar

1. **T6, instalación:** anotar versiones efectivas, IP/prefijo, URL, resultado de los servicios y dificultades del manual. Usar CA/base/cuentas propios; el `.cer` de Rafael no firma los certificados de otra CA. No publicar esos datos privados ni exportar su base.
2. **T2, diagnóstico:** reproducir voz en Android con una cuenta local, registrar navegador, duración/formato, respuesta HTTP y código interno pertinente. Distinguir captura inválida, procesamiento, comparación o límite de intentos. Entregar evidencia y propuesta antes de editar.
3. **T4, corrección:** cambiar solo la causa demostrada; verificar en laptop y teléfono. Documentar número de intentos genuinos y ajenos con condiciones controladas. Si hacen falta otras muestras o calibración, explicarlo y mantener evaluación separada.
4. **T5, interfaz:** aclarar disponibilidad frente a registro, fin automático de captura y errores recuperables. No revelar por API pública si una cuenta existe o tiene credenciales. Coordinar cambios en archivos compartidos.
5. **T7, comprobación:** repetir cuatro accesos, cuenta incorrecta/inactiva, credencial ausente/revocada, cancelación, permisos denegados, roles, logout, límites, servicio caído y retos caducados. Registrar esperado/observado, aprobado/fallido/bloqueado y pasos de reproducción; no guardar secretos o muestras en el informe.

Pendientes transversales: calibración facial/vocal, métricas con denominadores reales, prueba tras reiniciar y operación con/sin internet. La inferencia es local; no se ha demostrado prueba de vida ni defensa contra reproducciones. No afirmar «100 %» antes de completar la matriz.

## Verificación repetida durante T1

- `tests/run.php`: 40 comprobaciones correctas en base aleatoria aislada. Incluye WebAuthn con autenticador sintético, firma, origen, titularidad y verificación de usuario.
- `tests/https-smoke.py`: 8 comprobaciones correctas de HTTPS local.
- `tests/proxy-smoke.py`: 7 comprobaciones correctas del proxy restringido y TLS de extremo a extremo.

Esto valida protocolo y transporte en la laptop; no certifica el sensor ni el proveedor de llaves de Android.

Referencias técnicas: [Google: soporte de passkeys en Android y Chrome](https://developers.google.com/identity/passkeys/supported-environments) explica que el proveedor de llaves es independiente del sitio; [WebAuthn nivel 2](https://www.w3.org/TR/webauthn-2/) define las ceremonias y errores. Ninguna referencia diagnostica por sí sola el error concreto de este teléfono.

## Entrega

El prompt para quien continúa está en [prompt-companera.md](prompt-companera.md). Esta entrega incluye el código integrado y el [manual de instalación](manual-para-otro-equipo.md). Tras clonar o actualizar, anotar `git rev-parse --short HEAD` en el reporte para identificar la versión evaluada. Las cuentas reales, configuración privada, certificados y pesos descargados no forman parte del repositorio.
