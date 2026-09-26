# HTTPS en la red local y prueba en el teléfono

Equipo de referencia: MSI Cyborg 15 A13V y POCO X7 Android. Se comenzó con Brave; falta anotar la versión y el navegador exactos de la prueba final. Nombre **acceso.ada.test**, origen exacto **https://acceso.ada.test:8443** y RP ID **acceso.ada.test** (sin esquema ni puerto).

Para instalar en otra computadora, seguir primero [el manual de entrega](manual-para-otro-equipo.md): incluye la exportación del `.cer`, proxy sin DNS manual y valores adaptables a otra red. Cada servidor independiente genera su propia CA; el `.cer` compartido por Rafael corresponde a la CA original y no hace confiable un certificado firmado por otra.

## Estado de esta instalación (26-09-2026)

HTTPS está activado y comprobado en la laptop. Apache pasó `-t` y `-S`; Windows confía en la CA instalada por el usuario. El sitio responde 200, la cookie tiene Secure/HttpOnly/SameSite y localhost:8088 redirige al origen seguro. `tests/https-smoke.py` pasó ocho comprobaciones, incluidos el bloqueo de archivos privados y los alias administrativos. **El titular confirmó navegación por dominio mediante proxy y acceso por patrón, huella y rostro desde el teléfono.** La resolución DNS directa falló; la configuración que permitió continuar usa el proxy. Faltan las pruebas físicas negativas completas y anotar versiones.

El DNS local está iniciado para el nombre de prueba y se verificó mediante una consulta UDP real. Las reglas de firewall `AccesoADA-HTTPS` y `AccesoADA-DNS`, grupo `Acceso ADA prueba local`, permiten solo TCP 8443 y UDP 53 desde la subred configurada hacia la IP Wi-Fi de la laptop. No se abrieron MySQL ni el servicio biométrico.

La configuración de esta máquina está en `config/wamp-https.local.conf` (ignorada por Git); certificados y copias previas en `storage/private/tls/`. La copia pública para el POCO está en `certificado-telefono/Acceso-ADA-CA.cer`. No contiene clave privada. mkcert 1.4.4 se descargó del release oficial Windows amd64; SHA-256 observado del ejecutable: `d2660b50a9ed59eada480750561c96abc2ed4c9a38c6a24d93e30e0977631398` (el API del release no publicó digest; este hash identifica el archivo descargado).

Tras reiniciar la laptop, iniciar de nuevo Python y, para la ruta utilizada en el teléfono, `bin/site_proxy.py` con la IP/subred vigentes. El DNS es una alternativa y no se necesita junto al proxy. Si la IP cambia, actualizar Listen/VirtualHost/Require ip de Apache, hosts y el host del proxy en el teléfono; `bin/enable-https-windows.ps1 -LanIp <IP> -PrefixLength <prefijo> -EnableSiteProxy` configura las reglas y agrega hosts como administrador, pero se detiene ante una entrada hosts anterior distinta. Corregir únicamente esa línea antes de repetirlo. Reiniciar Apache desde WAMP después de cambiar Listen. El nombre y RP permanecen estables.

## 1. Comprobar la red

Conectar laptop y POCO a la misma red local, sea router Wi-Fi o hotspot de un tercer teléfono. Internet hace falta para descargar dependencias y herramientas; las muestras se procesan en la laptop. El proveedor de llaves del teléfono puede tener requisitos propios de conexión. Algunos hotspots aíslan clientes. Si el POCO no alcanza la laptop aun con los puertos permitidos, revisar el aislamiento o usar otro hotspot/router; no resolverlo con un túnel público.

En PowerShell:

```powershell
Get-NetIPAddress -AddressFamily IPv4 | Format-Table InterfaceAlias,IPAddress,PrefixLength
```

Anotar la IP privada real del Wi-Fi de la laptop y su subred. No usar las interfaces VPN/virtuales ni copiar una IP de ejemplo. Si cambian al reconectar, actualizar DNS, hosts, Listen y firewall. Mantener el nombre estable conserva el RP; cambiar de localhost a ese nombre requiere registrar nuevamente WebAuthn.

## 2. Certificado local

Instalar la herramienta desde las [publicaciones oficiales de mkcert](https://github.com/FiloSottile/mkcert/releases), usando su binario Windows amd64. Seguir su [manual oficial](https://github.com/FiloSottile/mkcert). En una consola local con permisos apropiados:

```powershell
mkcert -install
New-Item -ItemType Directory -Force storage/private/tls
mkcert -cert-file storage/private/tls/site.pem -key-file storage/private/tls/site-key.pem acceso.ada.test
mkcert -CAROOT
```

`-install` añade una CA de desarrollo al almacén de confianza de la laptop. En esta instalación se usó CAROOT `storage/private/tls/ca` y se importó la CA en `Cert:\CurrentUser\Root` mediante Import-Certificate, con confirmación del usuario. Transferir **solo el certificado público** al POCO mediante USB: `certificado-telefono/Acceso-ADA-CA.cer` es la versión DER de rootCA.pem. Usar la opción de Android para instalar un certificado CA de usuario; las etiquetas del menú dependen de HyperOS. Nunca transferir rootCA-key.pem ni site-key.pem. Comprobar en Brave que el sitio se reconoce como seguro **sin saltar advertencias**. La aceptación del CA por cada navegador se valida en el dispositivo; no se ha supuesto a partir de su nombre. Si Zen no usa el almacén de Windows, importar únicamente la CA de esta prueba en su administrador de certificados.

## 3. Resolver el nombre sin internet

En el archivo hosts de Windows, con permisos de administrador, agregar una única línea con la IP real:

```text
IP_DE_LA_LAPTOP acceso.ada.test
```

Para Android se incluye `bin/lan_dns.py`, un DNS UDP mínimo que solo responde por `acceso.ada.test`; no es un DNS de uso general ni reenvía consultas a internet. Lanzarlo con la IP y subred reales:

```powershell
biometric-service/.venv/Scripts/python.exe bin/lan_dns.py --bind IP_DE_LA_LAPTOP --subnet SUBRED_CIDR
```

En las opciones avanzadas de esa Wi-Fi del POCO, conservar los datos correctos de dirección, puerta de enlace y prefijo del hotspot y establecer DNS de la red en la IP de la laptop. Puede ser necesario seleccionar configuración IP estática; evitar una dirección duplicada. Desactivar temporalmente **DNS privado** de Android y **DNS seguro** del navegador para esta prueba si impiden utilizar el DNS local; esto no desactiva TLS. Otros dominios no se resolverán con este DNS. Si el hotspot permite configurar su propio registro DNS, usarlo en lugar del script.

Si aparece `DNS_PROBE_POSSIBLE`, todavía no se ha establecido la conexión TLS: no reinstalar certificados como primer paso. Poner la IP de la laptop en **ambos campos DNS**, guardar explícitamente y reconectar la Wi-Fi. Apagar temporalmente los datos móviles para evitar que Android cambie de conexión cuando detecta esta red como sin internet, y reiniciar el navegador. Restaurar los ajustes de datos/DNS al acabar.

El DNS admite `--status-file storage/private/tls/dns-status.json` para diagnóstico: contadores recibidos/respondidos y último cliente que preguntó por el sitio. No registra nombres consultados de otras páginas, contenidos ni muestras. `received=0` después de una prueba desde el teléfono indica que no llegaron consultas al proceso; revisar la selección de red/DNS, firewall y aislamiento entre clientes. Una consulta correcta desde la propia laptop no demuestra acceso desde Android.

Permitir únicamente UDP 53 y TCP 8443 desde la subred de la prueba en Firewall de Windows. No abrir 3306, 8000 ni phpMyAdmin. Ejemplo parametrizado, ejecutar como administrador después de definir `$lanIp` y `$lanCidr` con los valores reales:

```powershell
New-NetFirewallRule -DisplayName 'ADA prueba HTTPS' -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8443 -LocalAddress $lanIp -RemoteAddress $lanCidr
New-NetFirewallRule -DisplayName 'ADA prueba DNS' -Direction Inbound -Action Allow -Protocol UDP -LocalPort 53 -LocalAddress $lanIp -RemoteAddress $lanCidr
```

## 4. Apache y origen

Copiar `config/wamp-https.example.conf` a `config/wamp-https.local.conf`. Sustituir LAPTOP_IP, LAN_CIDR y las rutas si el proyecto está en otro directorio. Comprobar que estén cargados `ssl_module` y `socache_shmcb_module`; no duplicar un Listen ya existente. Incluir el archivo local en la configuración de VirtualHosts de Apache. El puerto dedicado limita acceso a la subred y niega los alias administrativos habituales de WAMP.

Guardar una copia privada de `.env`; luego cambiar:

```dotenv
APP_ORIGIN=https://acceso.ada.test:8443
WEBAUTHN_RP_ID=acceso.ada.test
APP_BASE_PATH=
```

Comprobar la configuración con `httpd.exe -t` y reiniciar Apache desde WAMP. A partir de ese cambio usar el nuevo origen también en la laptop: la cookie Secure no funciona entrando por el HTTP anterior. No desactivar Secure para compensarlo.

## Alternativa cuando Android no resuelve el dominio: proxy local limitado

En la prueba del POCO se confirmó acceso a la laptop por su IP, pero persistió `DNS_PROBE_POSSIBLE` para el nombre. La captura por IP mostraba un certificado inválido para ese nombre: es evidencia de conectividad, no un acceso HTTPS válido para la demostración. No usar la IP para autenticar ni omitir advertencias TLS.

Se implementó `bin/site_proxy.py`, que acepta únicamente `CONNECT acceso.ada.test:8443` y conecta con el Apache de la misma laptop. No descifra TLS, no envía muestras a terceros y rechaza otros destinos/puertos y HTTP sin cifrar. El navegador conserva el nombre, el origen y el RP ID configurados. Este funcionamiento de CONNECT está descrito en la [documentación de Chromium](https://chromium.googlesource.com/chromium/src/+/HEAD/net/docs/proxy.md#http-proxy-scheme).

Arranque (sustituir IP/subred por las vigentes):

```powershell
biometric-service/.venv/Scripts/python.exe bin/site_proxy.py --bind IP_DE_LA_LAPTOP --subnet SUBRED_CIDR --status-file storage/private/tls/proxy-status.json
```

Habilitar su regla desde PowerShell administrador:

```powershell
.\bin\enable-https-windows.ps1 -LanIp IP_DE_LA_LAPTOP -PrefixLength 24 -EnableSiteProxy
```

Usar el prefijo real de la interfaz. La regla `AccesoADA-SiteProxy` solo permite TCP 8899 en la IP LAN de la laptop desde la subred local. El proceso limita concurrencia (12), tamaño de cabeceras (8 KB), inactividad y duración; los diagnósticos solo contienen contadores y el último cliente que conectó con este sitio. Apache ve la IP del relé, por lo que los clientes que lo usan comparten el límite por origen; permanece también el límite por cuenta.

En la Wi-Fi del POCO: **Proxy → Manual**, host = IP de la laptop, puerto = **8899**, sin excepciones. Guardar, cerrar la pestaña abierta por IP y abrir **https://acceso.ada.test:8443**. El DNS manual no es necesario para este recorrido: el relé tiene un destino fijo. Se puede restaurar la configuración IP/DNS original del teléfono una vez comprobado el acceso; mantener el proxy durante la demostración. Este proxy rechaza otros sitios, por lo que debe volver a **Ninguno** al acabar.

`tests/proxy-smoke.py <IP>` pasó 7 comprobaciones: destinos/puertos ajenos rechazados, HTTP rechazado, destino permitido, certificado y hostname verificados de extremo a extremo sin consulta DNS del cliente, cookie Secure. El titular reportó navegación y acceso por huella en el POCO; queda documentar la matriz física completa. Para cerrar esta alternativa, detener su proceso (PID en `storage/private/tls/proxy.pid` si lo arrancó Codex, verificándolo antes), devolver Proxy a Ninguno y retirar únicamente la regla `AccesoADA-SiteProxy`.

## 5. Prueba presencial

1. Abrir https://acceso.ada.test:8443 en ambos equipos y confirmar certificado válido, cámara/micrófono disponibles y sesión real.
2. Entrar como administrador en el POCO, abrir la cuenta del titular y registrar WebAuthn. El titular completa el diálogo del sistema; cerrar sesión administrativa al terminar.
3. Entrar con esa cuenta mediante Dispositivo. Registrar si el sistema pidió huella, PIN o rostro. La web valida WebAuthn y verificación de usuario, no puede certificar cuál sensor se usó.
4. Probar otra cuenta, cancelación y credencial revocada. No deben conceder acceso.
5. Probar rostro y voz con capturas nuevas de cada participante conforme a `docs/pruebas.md`.

Anotar versión Android/HyperOS, navegador, certificado, red y resultados. **Tener desbloqueo facial en el teléfono no demuestra que WebAuthn vaya a ofrecerlo.**

## Restaurar al acabar

Cerrar el proceso DNS (Ctrl+C si se inició en una terminal; para el proceso en segundo plano revisar el PID guardado en `storage/private/tls/dns.pid` y verificar que corresponde a este script). Devolver DNS del teléfono a automático y restaurar las preferencias DNS previas. Eliminar solo las reglas de esta prueba —en esta instalación, `AccesoADA-HTTPS` y `AccesoADA-DNS`—, la línea hosts añadida y el Include HTTPS. Restaurar `.env` desde `storage/private/tls/env.before-https` si se regresa a localhost y reiniciar Apache. Retirar del teléfono y navegadores la CA instalada. Para la laptop, usar `mkcert -uninstall` con el mismo CAROOT de esta instalación o retirar únicamente ese certificado desde el almacén de certificados de usuario. Conservar o eliminar las claves privadas según el plan de cierre; nunca subirlas a Git.
