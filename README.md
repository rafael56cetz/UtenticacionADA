# Acceso · Autenticación multimodal

Proyecto académico IAW-UNI2 ADA 1. Aplicación prevista en PHP sobre WAMP, con cuatro métodos de acceso: rostro, voz, WebAuthn y patrón. Roles: `Administrator` y `Gestor`.

## Estado de esta primera entrega

Se conserva el frontend recibido: vistas PHP, CSS y módulos JavaScript de captura e integración. **Todavía no se ha implementado el backend, la base de datos ni el servicio biométrico.** Descargar este repositorio no proporciona aún una aplicación de autenticación operativa.

La prioridad inicial es compartir una base común con el equipo. La implementación del backend seguirá [el encargo de Codex](04-prompt-para-codex.md) y [el contrato de integración](02-contrato-de-integracion.md), preservando el diseño existente.

## Descargar y colaborar

En GitHub, seleccionar **Code → Download ZIP** para descargar una copia. Para trabajar con historial y recibir cambios, copiar la URL HTTPS de **Code** y ejecutar:

```powershell
git clone <URL-HTTPS-del-repositorio>
cd UtenticacionADA
git switch -c backend
```

Cada integrante puede crear su rama según el área: `backend`, `frontend`, `biometria` o `docs-qa`. Integrar los cambios mediante pull requests hacia `main`. Un repositorio público permite descargar sin invitación; para subir cambios directamente, el propietario debe agregar a cada integrante como colaborador. También se puede contribuir desde un fork.

Antes de comenzar una nueva rama desde `main`, actualizarla con `git pull --ff-only`. Coordinar cambios en el contrato y evitar editar simultáneamente los mismos archivos.

## Documentación de referencia

- [Investigación, arquitectura y plan del equipo](01-investigacion-y-plan.md).
- [Contrato de rutas, formatos y responsabilidades](02-contrato-de-integracion.md).
- [Encargo de frontend](03-prompt-para-gemini.md).
- [Encargo de backend, biometría e integración](04-prompt-para-codex.md).
- [Revisión inicial y pendientes de integración](docs/estado-inicial.md).

## Archivos actuales

```text
public/assets/css/   Estilos y tokens visuales
public/assets/js/    Cliente API, capturas, patrón y WebAuthn
views/              Plantillas de presentación PHP
design-preview/     Catálogo visual estático, sin autenticación
docs/               Estado y decisiones de integración
```

Para revisar únicamente los componentes visuales, abrir `design-preview/index.html` en el navegador. Sus mensajes y datos son ejemplos estáticos; no comprueban identidad ni representan accesos reales.

La aplicación final deberá usar un VirtualHost cuyo `DocumentRoot` sea `public/`. Aún falta `public/index.php`; las vistas no deben publicarse directamente ni considerarse rutas protegidas hasta integrar el servidor.

## Arquitectura acordada

- PHP controla usuarios, permisos, sesiones, retos, límites y persistencia.
- MySQL o MariaDB almacena los datos; la elección y las migraciones están pendientes.
- Un servicio FastAPI escucha exclusivamente en `127.0.0.1`, autenticado para llamadas desde PHP.
- OpenCV YuNet/SFace verifica rostro y SpeechBrain ECAPA-TDNN verifica hablante.
- WebAuthn registra claves públicas; no almacena huellas. El dispositivo puede ofrecer PIN u otro desbloqueo.
- El patrón tiene de 6 a 9 puntos distintos y se almacena mediante hash.
- Laptop y teléfono necesitan un origen HTTPS estable y confiable para las pruebas finales.

## Datos que no deben subirse

No incluir contraseñas, claves, archivos `.env`, certificados privados, muestras de rostro/voz, plantillas biométricas ni copias reales de bases de datos. `.gitignore` excluye las ubicaciones previstas; revisar siempre `git diff --cached` antes de un commit. El esquema SQL y los ejemplos ficticios sí deben versionarse.

Las dependencias, sus versiones y las licencias de los modelos se documentarán al instalarlas y probarlas. No se atribuye al equipo el entrenamiento de modelos externos. Esta entrega no incorpora todavía esas dependencias ni ofrece resultados de precisión biométrica.
