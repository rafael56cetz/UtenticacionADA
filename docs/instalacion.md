# Instalación y primera prueba

## Estado comprobado el 26-09-2026

WAMP: Apache 2.4.65, MySQL 8.4.7 y PHP 8.3.28. Se comprobó PHP de Apache por HTTP (`apache2handler`, `C:\wamp64\bin\apache\apache2.4.65\bin\php.ini`) y PHP CLI por separado (`C:\wamp64\bin\php\php8.3.28\php.ini`). Ambos tienen pdo_mysql, openssl, mbstring, sodium, fileinfo y curl. El PHP predeterminado del PATH era 8.0: usar la ruta explícita de 8.3.

Servicio probado con Python 3.13, torch/torchaudio 2.8.0+cpu, SpeechBrain 1.0.3, OpenCV 4.13.0.92, FastAPI 0.128.0 y PyAV 16.1.0. Las versiones transitivas están en `biometric-service/requirements.txt`. CPU, sin necesidad de CUDA.

La instalación actual está preparada en `C:\wamp64\www\UtenticacionADA` y ahora sirve en **https://acceso.ada.test:8443**. La dirección anterior http://localhost:8088 redirige al sitio seguro. El servicio Python escucha en **127.0.0.1:8000**. No volver a ejecutar setup sobre esta instalación. La guía siguiente conserva el HTTP local como primera etapa de una instalación nueva; la configuración HTTPS específica está en `https-y-telefono.md`.

## Instalar en otra computadora

Para el recorrido completo, incluida una red diferente, usar [manual para otro equipo](manual-para-otro-equipo.md). Crear certificados y cuentas propios; no copiar el `.env`, base real ni claves de la instalación original. El instalador usa MySQL 8 y su collation `utf8mb4_0900_ai_ci`; no asumir compatibilidad directa con MariaDB.

Se requiere internet durante la descarga inicial de dependencias y modelos. Después la inferencia usa archivos locales; no envía muestras a Hugging Face ni OpenCV. No se ha ensayado todavía una reinstalación completa en una segunda computadora.

```powershell
git clone https://github.com/rafael56cetz/UtenticacionADA.git
cd UtenticacionADA
$php = 'C:\wamp64\bin\php\php8.3.28\php.exe'
& $php C:\ProgramData\ComposerSetup\bin\composer.phar install --no-dev
& $php bin/console.php setup
```

Ajustar las rutas a las versiones instaladas. Iniciar WAMP desde su icono. `setup` pide la conexión MySQL y crea una base **nueva** `acceso_*`, el esquema y una cuenta privada limitada a SELECT/INSERT/UPDATE/DELETE. Genera `.env` con claves aleatorias; no crea usuarios de aplicación ni publica un instalador web. Si falla a mitad, revisar los objetos que alcanzó a crear antes de reintentar; no borra bases existentes. La contraseña MySQL del setup se lee en la terminal; ejecutar en privado.

```powershell
C:\Python313\python.exe -m venv biometric-service/.venv
biometric-service/.venv/Scripts/python.exe -m pip install -r biometric-service/requirements.txt
biometric-service/.venv/Scripts/python.exe biometric-service/download_models.py
```

La descarga verifica revisiones/checksums fijados y escribe `models.lock.json`. No sustituir pesos ni actualizar librerías de manera aislada. Un cambio de modelo/preprocesamiento/umbral requiere nueva versión y volver a registrar las plantillas.

Copiar/adaptar `config/wamp-http.conf`: DocumentRoot debe ser `public/`. Añadir al archivo de VirtualHosts activo de Apache:

```apache
Include "C:/wamp64/www/UtenticacionADA/config/wamp-http.conf"
```

Verificar que mod_rewrite esté activo. Ejecutar `httpd.exe -t` desde la carpeta de Apache y **reiniciar Apache desde WAMP**. El sitio 8088 está limitado a la laptop. No se debe abrir el directorio raíz del proyecto desde el teléfono.

## Arrancar y crear el primer administrador

En otra ventana de PowerShell, mantener abierto:

```powershell
.\biometric-service\start-service.ps1
```

En `.env`, establecer `FACE_ENABLED=1` y `VOICE_ENABLED=1` después de descargar y cargar los modelos. `/api/capabilities` solo los anuncia cuando también responde el servicio. Para diagnosticar:

```powershell
& 'C:\wamp64\bin\php\php8.3.28\php.exe' bin/console.php doctor
biometric-service/.venv/Scripts/python.exe biometric-service/test_service.py
```

La instalación actual ya tiene ambas modalidades habilitadas. El proceso iniciado por Codex no es un servicio automático de Windows: tras reiniciar la laptop hay que arrancarlo de nuevo. No iniciar una segunda instancia si el puerto 8000 ya está ocupado.

```powershell
.\bin\create-admin.ps1
```

Pide identificador, nombre y contraseña oculta de 12–128 caracteres. Invoca el comando local `admin:create`, que solo permite crear el primer Administrator si no existe ningún usuario. No compartir la contraseña en chats. Si la política de PowerShell impide ejecutar archivos, abrir una consola puntual con `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\bin\create-admin.ps1`; no modifica permanentemente la política del equipo.

Abrir http://localhost:8088 → **Recuperación** → entrar con esa cuenta. En Usuarios, abrir la cuenta y registrar patrón, rostro o voz. Para el patrón: seleccionar 6–9 puntos, confirmar selección, repetir, confirmar y finalizar. Para rostro/voz debe estar presente el titular y aceptar el aviso. Tres capturas en el alta; una nueva en el acceso. Cerrar sesión antes de probar cada método. Las credenciales WebAuthn definitivas deben registrarse después de estabilizar el origen HTTPS del teléfono.

## Operación y datos

Los modelos tardan unos segundos en cargar. Si el servicio está caído o una inferencia supera 30 segundos, el acceso biométrico se cierra; después de un timeout hay que reiniciar Python. No hay aprobación simulada de respaldo. Las muestras se procesan en memoria o en el temporal de carga de PHP y se descartan; en MySQL solo quedan las plantillas cifradas. No existe prueba de vida ni defensa garantizada ante reproducciones.

Sesiones: 30 minutos de inactividad / 8 horas absolutas. Retos: 120 segundos y un uso. Límites: 5 fallos en 10 minutos por cuenta u origen compartidos entre métodos; 40 peticiones registradas por minuto/origen. Los equipos detrás de una misma IP comparten el límite de origen. Cambiar de modalidad o recuperación no lo evade.

Desde la cuenta, **Retirar consentimiento y borrar** elimina la plantilla de esa modalidad e invalida sesiones. La contraseña de recuperación y el patrón se guardan con Argon2id cuando está disponible, con fallback `PASSWORD_DEFAULT` de PHP. Las plantillas usan XChaCha20-Poly1305 y contexto de cuenta/modalidad/versión. Perder `TEMPLATE_KEY` obliga a registrar de nuevo; no rotarla sin migración. Respaldar base y claves por separado, fuera de Git.

```powershell
& 'C:\wamp64\bin\php\php8.3.28\php.exe' bin/console.php cleanup
```

El comando elimina retos vencidos de más de un día e intentos de más de 30 días. Ejecutarlo periódicamente; no hay tarea programada instalada. Los registros administrativos se conservan. Al terminar la evaluación, revocar todas las plantillas con los participantes y borrar las copias privadas según lo acordado.

Para el teléfono, seguir [HTTPS y hotspot](https-y-telefono.md). Las evidencias y límites de lo probado están en [pruebas](pruebas.md).
