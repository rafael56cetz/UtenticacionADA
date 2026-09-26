# Plan de implementacion

Fecha de inicio: 26 de septiembre de 2026. Base: frontend original, revision de UtenticacionADA4 y contrato version 1.

1. Preparar configuracion privada, Composer, MySQL 8.4 y migracion. Validar PHP 8.3 CLI y Apache por separado.
2. Implementar nucleo PHP: sesiones, CSRF, PDO, limites compartidos, retos de un uso, recuperacion y patron. Probar accesos correctos y negativos contra base aislada.
3. Implementar administracion con permisos, ultimo administrador protegido, revocacion, consentimiento e historial.
4. Integrar WebAuthn con biblioteca y pruebas de protocolo. Mantener pendientes explicitos para el sensor fisico.
5. Implementar servicio local de rostro/voz con inferencia real, modelos fijados y cifrado. Nunca aceptar muestras si falta un modelo o dependencia.
6. Integrar vistas preservando estilos; verificar en WAMP y navegador. Preparar HTTPS y manual de instalacion.
7. Entregar evidencias de lo ejecutado y separar pruebas fisicas, calibracion y consentimiento que requieren participantes.

La entrega del companero se conserva fuera del proyecto principal. Sus respuestas de exito simulado no se incorporaran. Las mejoras de captura se incorporaran selectivamente.

## Avance comprobado al retomar

Esta sección conserva el hito anterior a las pruebas físicas. **Estado vigente de la entrega:** el titular ya creó su administrador y confirmó patrón, rostro y huella en el teléfono mediante HTTPS/proxy. Voz en Android continúa pendiente; instalación independiente y matriz negativa completa también. Seguir [tareas-del-equipo.md](tareas-del-equipo.md) y [manual-para-otro-equipo.md](manual-para-otro-equipo.md) para continuar; no repetir el setup ni crear otro primer administrador en la instalación original.

- Pasos 1–5 implementados: base real, dependencias instaladas, núcleo PHP, administración, WebAuthn y servicio biométrico en CPU. Las pruebas no sustituyen validación con personas.
- Paso 6: vistas integradas; Apache responde en localhost:8088 y Python en 127.0.0.1:8000. Modelos de rostro y voz disponibles. Pendientes la revisión visual, el primer administrador del titular y HTTPS/POCO; guía preparada en `https-y-telefono.md`.
- Paso 7: manual, contrato implementado, esquema/diagrama, procedencia de modelos y guion de evaluación escritos. 40 comprobaciones PHP + 17 HTTP + 14 de servicio + 2 de concurrencia + 7 de DNS = 80 comprobaciones automáticas; sin evidencia de precisión biométrica humana. Sintaxis correcta en 25 archivos PHP y 13 módulos JavaScript; Python compila y pip check no detecta incompatibilidades declaradas.

Siguiente acción del titular: ejecutar `bin/create-admin.ps1`, entrar por Recuperación y probar registro/acceso por patrón. Después conectar laptop y POCO al hotspot y activar el origen HTTPS estable con su CA confiable para probar WebAuthn físico. Los cambios están en el proyecto local; esta fase aún no se ha publicado en GitHub.
