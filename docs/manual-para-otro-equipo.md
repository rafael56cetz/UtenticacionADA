# Instalar el proyecto en otra computadora y probarlo con un teléfono

Entrega del 26 de septiembre de 2026. Esta guía instala el código integrado en una computadora Windows nueva, con una base propia y una red distinta. No copia las cuentas, contraseñas, llaves ni datos biométricos de Rafael. No ejecutar `setup` sobre una instalación que ya funciona.

## 1. Qué recibir y qué falta

Descargar el código de [GitHub](https://github.com/rafael56cetz/UtenticacionADA). Leer después [tareas y punto de entrega](tareas-del-equipo.md) y [prompt para continuar](prompt-companera.md). El nombre histórico de ese último archivo no limita quién puede usarlo.

El titular confirmó patrón, huella y rostro en su instalación/teléfono. La voz funcionó en laptop y falló en Android; sigue pendiente diagnosticarla. La reinstalación de esta guía en una segunda máquina aún debe comprobarse. No cambiar el reconocimiento ni los umbrales durante la instalación.

Se necesitan WAMP, Git (o el ZIP), Composer, Python 3.13 de 64 bits y mkcert. La instalación de referencia usa Apache 2.4.65, PHP 8.3.28 y MySQL 8.4.7. Usar MySQL 8: el instalador utiliza `utf8mb4_0900_ai_ci`; no asumir que funciona sin adaptación en MariaDB. PHP requiere pdo_mysql, openssl, mbstring, sodium, fileinfo y curl, tanto en consola como en Apache.

Hace falta internet para obtener herramientas, dependencias y pesos. Una vez descargados, rostro y voz se procesan localmente. El proveedor de llaves de Android puede tener sus propios requisitos de conexión; comprobarlo en el dispositivo, sin prometer funcionamiento totalmente sin internet.

## 2. Descargar y crear la base

Abrir PowerShell normal. Si no existe todavía la carpeta:

```powershell
cd C:\wamp64\www
git clone https://github.com/rafael56cetz/UtenticacionADA.git
cd UtenticacionADA
```

Alternativa: GitHub → Code → Download ZIP; extraer de modo que `composer.json` esté directamente en `C:\wamp64\www\UtenticacionADA`, no en una carpeta anidada. Si ya hay una copia Git con trabajo propio, revisar `git status` y conservarlo antes de actualizar; con copia limpia, `git pull --ff-only`.

Iniciar WAMP desde su icono y seleccionar PHP 8.3. Comprobar las rutas reales; sustituir las versiones de estos ejemplos si difieren:

```powershell
$php = 'C:\wamp64\bin\php\php8.3.28\php.exe'
& $php -v
& $php -m
& $php 'C:\ProgramData\ComposerSetup\bin\composer.phar' install --no-dev
& $php bin/console.php setup
```

Si Composer está en otro lugar, usar la ruta de su `composer.phar` y ejecutar con ese mismo PHP. `setup` pregunta:

| Dato | Qué poner |
|---|---|
| Host y puerto MySQL | Generalmente `127.0.0.1` y `3306`; confirmar en WAMP. |
| Usuario que puede crear base/usuario | El administrador local de MySQL, generalmente `root`. |
| Contraseña MySQL | La de esa computadora; solo dejar vacía si realmente no tiene. Se introduce en terminal, no en un chat. |
| Base nueva | Por ejemplo `acceso_ada`; debe empezar por `acceso_` y no existir. |

El comando crea base, tablas desde `database/schema.sql`, usuario de aplicación con permisos limitados y `.env` con secretos aleatorios. **No crear primero la base en phpMyAdmin ni importar el esquema por separado si se usa este procedimiento.** No copiar `.env.example` a `.env` antes de `setup`, porque el instalador se detiene si `.env` ya existe. Si falla a mitad, inspeccionar qué se creó; no borrar bases ni volver a ejecutar a ciegas.

## 3. Preparar Python y los modelos

Desde la raíz del proyecto:

```powershell
& 'C:\Python313\python.exe' -m venv biometric-service/.venv
& .\biometric-service\.venv\Scripts\python.exe -m pip install -r biometric-service/requirements.txt
& .\biometric-service\.venv\Scripts\python.exe biometric-service/download_models.py
```

Ajustar la ruta a Python 3.13 si está en otra ubicación. Esperar a que terminen las descargas y verificaciones. No usar `pip install --upgrade` indiscriminadamente ni copiar la `.venv` de otra computadora. Los modelos grandes no vienen en Git; el descargador obtiene los pesos fijados y comprueba su integridad.

En el `.env` recién generado, conservar los secretos y cambiar únicamente:

```dotenv
FACE_ENABLED=1
VOICE_ENABLED=1
```

En una segunda ventana PowerShell, mantener en ejecución:

```powershell
cd C:\wamp64\www\UtenticacionADA
.\biometric-service\start-service.ps1
```

El servicio debe escuchar solo en `127.0.0.1:8000`. No abrir ese puerto en el firewall. Si PowerShell bloquea el script, usar una ejecución puntual: `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\biometric-service\start-service.ps1`. No iniciar dos instancias en el mismo puerto.

## 4. Primera prueba local y administrador

En WAMP activar `rewrite_module`. Abrir la configuración de VirtualHosts del Apache activo, normalmente `C:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-vhosts.conf`. Conservar los sitios existentes y agregar una sola vez:

```apache
Include "C:/wamp64/www/UtenticacionADA/config/wamp-http.conf"
```

Comprobar que el archivo principal de Apache incluye ese archivo de VirtualHosts. Si la carpeta del proyecto es distinta, ajustar también las rutas dentro de `config/wamp-http.conf`. Su DocumentRoot es `public/`, no la raíz del repositorio. Con la ruta del Apache realmente activo:

```powershell
& 'C:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe' -t
```

Solo si indica `Syntax OK`, reiniciar Apache desde el icono de WAMP. Abrir `http://localhost:8088` en la laptop. Después:

```powershell
cd C:\wamp64\www\UtenticacionADA
.\bin\create-admin.ps1 -Php 'C:\wamp64\bin\php\php8.3.28\php.exe'
& 'C:\wamp64\bin\php\php8.3.28\php.exe' bin/console.php doctor
```

Elegir identificador propio y contraseña de recuperación de 12–128 caracteres. Entrar por **Recuperación**. No usar la cuenta de Rafael: esta base es nueva. El script solo crea el primer usuario. Registrar patrón si se desea; dejar WebAuthn para después de configurar HTTPS.

## 5. Anotar la IP y subred de la nueva red

Conectar laptop y teléfono al mismo router Wi-Fi o hotspot que permita comunicación entre clientes. En PowerShell:

```powershell
Get-NetIPAddress -AddressFamily IPv4 | Format-Table InterfaceAlias,IPAddress,PrefixLength
ipconfig
```

Elegir la IPv4 de la interfaz que comparte la red con el teléfono. No elegir VPN, VirtualBox, loopback ni copiar `192.168.1.79` de la instalación original. En esta ventana definir los valores propios; este ejemplo **debe adaptarse**:

```powershell
$lanIp = '192.168.50.20'
$prefix = 24
$lanCidr = '192.168.50.0/24'
```

Para `/24`, la subred termina en `.0/24`. Si el prefijo es diferente, calcular la dirección de red correcta; no asumir `/24`. El helper admite prefijos de 16 a 30. Anotar también la puerta de enlace real. El teléfono debe tener su propia IP: nunca ponerle la de la laptop. Preferir DHCP automático; una reserva DHCP en el router, si se dispone de ella, evita que cambie la IP de la laptop.

| Campo | Ejemplo adaptable | ¿Cambia de una red a otra? |
|---|---|---|
| IP de la laptop | `192.168.50.20` | Sí. |
| Subred | `192.168.50.0/24` | Sí. |
| Host del proxy en el teléfono | La IP de esa laptop | Sí. |
| Puerto del proxy | `8899` | Mantener. |
| Dirección de la aplicación | `https://acceso.ada.test:8443` | Mantener. |
| RP ID WebAuthn | `acceso.ada.test` | Mantener, sin puerto ni https. |

## 6. Crear el certificado de esta computadora

El `.cer` que Rafael tiene en Drive es el certificado público de **su** autoridad local. Instalarlo permite confiar en certificados firmados por esa autoridad; no configura Apache ni vuelve confiable cualquier certificado con el mismo nombre. Para un servidor independiente, este manual crea otra autoridad y exporta **su propio `.cer`**. No compartir `rootCA-key.pem` ni `site-key.pem` para intentar reutilizar el archivo de Drive. Véase el [manual oficial de mkcert](https://github.com/FiloSottile/mkcert).

Descargar el ejecutable Windows de [mkcert](https://github.com/FiloSottile/mkcert/releases) adecuado al equipo. La instalación original usó 1.4.4 Windows amd64. Guardarlo como `.tools\mkcert.exe` dentro del proyecto; crear `.tools` si falta. No viene incluido en Git.

Desde la raíz del proyecto, en la misma sesión PowerShell:

```powershell
New-Item -ItemType Directory -Force storage/private/tls/ca | Out-Null
$env:CAROOT = Join-Path (Get-Location).Path 'storage\private\tls\ca'
& .\.tools\mkcert.exe -install
& .\.tools\mkcert.exe -cert-file storage/private/tls/site.pem -key-file storage/private/tls/site-key.pem acceso.ada.test
certutil.exe -decode storage/private/tls/ca/rootCA.pem storage/private/tls/Acceso-ADA-CA.cer
```

Aceptar la instalación de la CA cuando Windows lo solicite; si mkcert devuelve un error de permisos, repetir con los permisos necesarios y el mismo `CAROOT`. Comprobar que cada comando termina correctamente. `certutil -decode` convierte el certificado público PEM a DER; no cambia ni exporta una clave privada. El `.cer` resultante está en `storage\private\tls\Acceso-ADA-CA.cer`.

Estos comandos son para la primera instalación: no recrear una CA ni sobrescribir certificados para resolver cualquier error de conexión. Conservar de forma privada el directorio TLS. Si se abre una consola nueva para operar sobre esa CA, volver a definir `CAROOT` con la misma ruta.

## 7. HTTPS, hosts y firewall

Copiar `config/wamp-https.example.conf` a `config/wamp-https.local.conf`. En la copia sustituir:

- `LAPTOP_IP` por la IP real (ejemplo: `192.168.50.20`).
- `LAN_CIDR` por la subred real (ejemplo: `192.168.50.0/24`).
- Todas las rutas `C:/wamp64/www/UtenticacionADA` si se instaló en otro directorio.

En los módulos de Apache de WAMP habilitar `ssl_module` y `socache_shmcb_module`, además de `rewrite_module`. No duplicar sus líneas LoadModule ni el Listen 8443. En el archivo de VirtualHosts agregar una sola vez:

```apache
Include "C:/wamp64/www/UtenticacionADA/config/wamp-https.local.conf"
```

En `.env`, conservar base, secretos y demás ajustes; editar estas tres líneas:

```dotenv
APP_ORIGIN=https://acceso.ada.test:8443
APP_BASE_PATH=
WEBAUTHN_RP_ID=acceso.ada.test
```

Abrir PowerShell **como administrador**, volver a la raíz del proyecto y usar la IP/prefijo propios (las variables de otra ventana no se heredan):

```powershell
cd C:\wamp64\www\UtenticacionADA
.\bin\enable-https-windows.ps1 -LanIp 192.168.50.20 -PrefixLength 24 -EnableSiteProxy
```

Este helper agrega la entrada `IP acceso.ada.test` en hosts y limita las reglas a la IP/subred indicadas. Habilita HTTPS TCP 8443, proxy TCP 8899 y también DNS UDP 53; la ruta con proxy de esta guía no necesita ejecutar el servidor DNS. No abre MySQL ni Python. Si ya existe una entrada hosts diferente, se detiene: abrir hosts como administrador y corregir únicamente la línea de `acceso.ada.test`, conservando lo demás; después repetir el helper.

Validar y reiniciar Apache desde WAMP:

```powershell
& 'C:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe' -t
```

Abrir **https://acceso.ada.test:8443** en la laptop y comprobar que no hay advertencia de certificado. El HTTP anterior ahora redirige a ese origen. Si el navegador usa otro almacén de confianza, importar en él solo la CA pública de esta instalación; no omitir advertencias.

## 8. Arrancar el proxy y configurar el teléfono

En otra ventana PowerShell normal, sustituir los ejemplos por IP/subred propias y dejarla abierta:

```powershell
cd C:\wamp64\www\UtenticacionADA
.\biometric-service\.venv\Scripts\python.exe bin/site_proxy.py --bind 192.168.50.20 --subnet 192.168.50.0/24 --status-file storage/private/tls/proxy-status.json
```

El proxy solo conecta con esta aplicación local y no descifra TLS. No es un proxy de internet. Primero transferir el `.cer` nuevo al teléfono por USB o un canal privado. Si se usa Drive, descargarlo **antes** de activar el proxy, porque el proxy bloquea otros sitios.

En Android, buscar en Ajustes **Instalar certificado** → **Certificado CA** y elegir el `.cer` nuevo. Confirmar con el bloqueo del teléfono. En algunos equipos aparece dentro de Seguridad/Privacidad → Más ajustes de seguridad → Cifrado y credenciales. Los nombres cambian según Android/HyperOS; referencia general: [ayuda de Android sobre certificados](https://support.google.com/pixelphone/answer/2844832?hl=es). Seleccionar CA, no certificado Wi-Fi de cliente ni VPN.

En los detalles de la Wi-Fi conectada:

| Ajuste | Valor |
|---|---|
| Configuración IP | DHCP/automática, salvo que la red requiera valores estáticos conocidos. |
| Proxy | Manual. |
| Nombre del host del proxy | IP de la laptop, por ejemplo `192.168.50.20`, sin `http://`. |
| Puerto | `8899`. |
| Excepciones / omitir proxy | Vacío, para que el nombre local pase por el proxy. |
| DNS | El normal de esa red; no se requiere DNS manual para esta ruta. |

Guardar. Si Android avisa «Sin internet», mantener la conexión para esta prueba local. Abrir en la barra del navegador la dirección **completa**:

**https://acceso.ada.test:8443/**

No buscarla en Google ni poner la IP de la computadora como URL. El certificado y WebAuthn corresponden al nombre `acceso.ada.test`. El enlace es el mismo en la nueva red porque el proxy lo dirige a la laptop local.

## 9. Registrar y probar los métodos en la base nueva

Entrar por Recuperación con el administrador creado en esta computadora. Gestionar su cuenta y registrar cada método con el titular presente. Para huella: **Registrar → Aceptar y continuar → Registrar en el dispositivo → completar el diálogo de Android → Finalizar registro**. Después cerrar sesión y probar el acceso de esa cuenta. Si el sistema ofrece PIN, anotarlo; WebAuthn no garantiza un sensor específico.

No hay registros biométricos preinstalados ni credenciales universales. Si el teléfono conserva una llave de la instalación de Rafael para el mismo nombre, esa llave no sirve automáticamente en la base nueva: crear una credencial para la cuenta de esta instalación y distinguirla por su identificador. No borrar otras llaves del teléfono sin saber a qué instalación pertenecen.

Registrar tres fotos para rostro y tres grabaciones de 5–8 segundos para voz. La voz en Android queda como tarea de diagnóstico si se reproduce el fallo. Anotar versión de navegador/Android y resultados según [pruebas](pruebas.md); no compartir contraseñas, patrones ni archivos biométricos.

## 10. Reinicio, cambio de red y problemas frecuentes

Después de reiniciar: abrir WAMP, iniciar `start-service.ps1` y arrancar `site_proxy.py` con la IP vigente. Son procesos locales, no servicios automáticos instalados. No regenerar base ni certificados en cada arranque.

Si cambia la IP o la red, actualizar la copia local de Apache (`Listen`, `VirtualHost`, `Require ip`), la línea hosts de este sitio, las reglas mediante el helper, el comando del proxy y su host en el teléfono. Detener el proxy anterior con Ctrl+C antes de iniciarlo con otra IP; comprobar Apache con `-t` y reiniciarlo desde WAMP. Mantener el dominio, APP_ORIGIN y RP ID: un cambio solo de IP no exige otro certificado si el nombre y la CA se conservan.

| Síntoma | Qué comprobar |
|---|---|
| No carga ni en laptop | WAMP, `httpd -t`, rutas/puerto, hosts y certificado. |
| Carga en laptop pero no en teléfono | Misma red, IP actual del proxy, puerto 8899, proceso abierto, firewall y aislamiento de clientes del router. |
| `DNS_PROBE_POSSIBLE` | Proxy guardado en la Wi-Fi correcta, sin excepción para el dominio; URL completa HTTPS. |
| Error de proxy / conexión rechazada | Host/puerto y proceso `site_proxy.py`; no confundir 8899 (proxy) con 8443 (sitio). |
| Certificado no confiable | Instalar la CA correspondiente a este servidor, fecha/hora y dominio exacto; el `.cer` de otra CA no lo resuelve. |
| «Sin internet», Drive/Google no abren | El proxy solo permite la app. Para navegar fuera, volver a Proxy → Ninguno y después reactivarlo para la prueba. |
| Llave no disponible | Registrar primero en la nueva cuenta y finalizar; no basta tener huella de desbloqueo del teléfono. |
| Error al crear llave | Anotar diálogo/proveedor/navegador y conexión disponible; no relajar la validación del servidor ni abrir el proxy a todo internet. |
| Error biométrico o método no disponible | Servicio Python, modelos descargados y flags del `.env`; no inventar respuestas exitosas. |
| 429 / demasiados intentos | Esperar el intervalo indicado; los fallos se comparten entre métodos y los clientes del proxy comparten IP. |

Comprobaciones opcionales de transporte desde la raíz (con servicios activos):

```powershell
.\biometric-service\.venv\Scripts\python.exe tests/https-smoke.py
.\biometric-service\.venv\Scripts\python.exe tests/proxy-smoke.py 192.168.50.20
```

Sustituir la IP. La prueba HTTPS también requiere servicio biométrico activo, flags habilitados y el VirtualHost HTTP previo. Estas pruebas no acreditan el sensor físico ni la conectividad real del teléfono. No ejecutar `tests/http-smoke.php --local-fixtures` sobre este origen HTTPS: fue preparado para HTTP local y tiene sus propias restricciones.

Al acabar, devolver Proxy a **Ninguno** en el teléfono. Si se retira la instalación de prueba, seguir la restauración de [HTTPS y teléfono](https-y-telefono.md), quitando únicamente sus certificados, entrada hosts y reglas, sin afectar otras aplicaciones. No subir a Git ni Drive claves privadas, `.env`, bases reales o plantillas.
