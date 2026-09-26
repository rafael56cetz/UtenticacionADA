# Acceso · Autenticación multimodal

Proyecto académico IAW-UNI2 ADA 1: PHP/WAMP, MySQL, JavaScript nativo y un servicio biométrico local. Métodos: patrón, rostro, voz y WebAuthn. Roles: Administrator y Gestor.

## Estado de la integración

El backend real ya está implementado e integrado con las vistas existentes: usuarios, permisos, sesiones, retos, CSRF, límites de intentos, recuperación, patrón con hash y WebAuthn criptográfico. Python carga YuNet/SFace y SpeechBrain ECAPA; PHP conserva las plantillas cifradas. Se retiraron del recorrido las aprobaciones simuladas de la entrega inicial.

Comprobado en la laptop con WAMP: 40 pruebas PHP, 17 HTTP, 14 del servicio y 2 de concurrencia; además, 8 comprobaciones HTTPS, 7 del proxy y 7 de DNS. El titular confirmó acceso por **patrón, huella y rostro en el teléfono**. La voz funcionó en laptop y falló en Android: su diagnóstico es el siguiente trabajo. **Aún no es una entrega validada al 100 %:** faltan la reinstalación en otro equipo, la matriz completa de pruebas físicas y la calibración. Los umbrales biométricos son experimentales. No hay garantía de prueba de vida.

**Para el compañero que recibe el proyecto:** empezar por el [manual de instalación en otra computadora y otra red](docs/manual-para-otro-equipo.md). Incluye base nueva, IP, proxy, certificado propio, enlace del teléfono y solución de problemas. El `.cer` de otra instalación no reemplaza el certificado correspondiente a tu nuevo servidor.

## Probar en la instalación actual

Abrir **https://acceso.ada.test:8443** en la instalación actual. La dirección anterior http://localhost:8088 redirige al sitio seguro. Mantener WAMP y el servicio Python activos. Para crear el primer administrador, desde PowerShell en la carpeta del proyecto:

```powershell
.\bin\create-admin.ps1
```

El comando pide una contraseña oculta; entrar luego mediante **Recuperación**, gestionar la cuenta y registrar sus modalidades. No hay contraseña universal. Si la cuenta ya existe, usarla: el comando de primera alta no reemplaza usuarios.

Después de reiniciar la laptop, levantar Python en otra terminal:

```powershell
.\biometric-service\start-service.ps1
```

## Descargar

```powershell
git clone https://github.com/rafael56cetz/UtenticacionADA.git
cd UtenticacionADA
```

Para una copia existente sin cambios locales: `git pull --ff-only`. Un ZIP es una copia de archivos, no conserva historial. El repositorio público permite descargar; subir cambios requiere ser colaborador o enviar un pull request desde un fork. Coordinar ramas y el contrato antes de editar archivos compartidos.

Una descarga nueva requiere configuración, Composer, MySQL, Python y modelos; no contiene claves, base real ni pesos. Seguir el manual completo, no copiar el .env de un compañero.

## Documentación

- [Manual para otra computadora: base, red, proxy y certificado del teléfono](docs/manual-para-otro-equipo.md).
- [Tareas, último avance y pendientes detallados](docs/tareas-del-equipo.md).
- [Prompt para el integrante que continúa el trabajo](docs/prompt-companera.md).
- [Instalación, arranque y primer administrador](docs/instalacion.md).
- [HTTPS, hotspot y teléfono](docs/https-y-telefono.md).
- [Pruebas ejecutadas y pendientes](docs/pruebas.md).
- [Esquema, diccionario y diagrama](docs/base-de-datos.md).
- [Contrato implementado y precisiones](docs/contrato-implementado.md).
- [Dependencias y modelos](docs/dependencias-y-modelos.md).
- [Encargo de backend](04-prompt-para-codex.md) y [contrato original](02-contrato-de-integracion.md).
- [Revisión de la entrega del compañero](docs/revision-entrega-4.md).

## Estructura

```text
public/               Única raíz pública; router y recursos del frontend
views/                Plantillas fuera de la raíz pública
src/                  Servicios PHP, sesiones, PDO y autorización
bin/                  Configuración local, administrador y DNS de prueba
database/schema.sql   Esquema MySQL
biometric-service/    FastAPI, modelos descargables y pruebas
config/               Ejemplos de VirtualHost
tests/                Pruebas con datos sintéticos
docs/                 Manuales y evidencias
storage/private/      Datos privados locales; excluidos de Git
```

`design-preview/` es un catálogo estático anterior, no la aplicación autenticada. No servir directamente las vistas. El DocumentRoot de Apache debe apuntar a public/.

No subir .env, contraseñas, claves de CA/certificados, plantillas, muestras de rostro/voz ni copias reales de bases. Revisar el contenido preparado para cada commit; los archivos lock y el esquema sí deben versionarse.
