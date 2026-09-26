# Certificado para el teléfono

Copiar únicamente `Acceso-ADA-CA.cer` al teléfono, por ejemplo mediante USB. Es el certificado público de la CA de desarrollo de esta instalación; cada instalación debe generar el suyo.

En los ajustes de Android, buscar **Instalar certificado** y seleccionar **Certificado CA**. Elegir este archivo y confirmar con el bloqueo del teléfono. El menú exacto depende de Android/HyperOS. Verificar que el sitio `https://acceso.ada.test:8443` se abre como seguro; no continuar saltando advertencias de TLS.

Esta carpeta no debe contener claves privadas. No transferir `site-key.pem` ni `rootCA-key.pem`. Al terminar la evaluación, retirar de Android la CA de esta prueba desde los certificados de usuario.

El archivo `.cer` es local y está excluido de Git. Las instrucciones generales de red están en `docs/https-y-telefono.md`.

Si recibes el `.cer` de Rafael por Drive, corresponde a la CA de su computadora. Para instalar el proyecto en tu propia computadora, genera tu CA y exporta tu propio `.cer` siguiendo [el manual para otro equipo](../docs/manual-para-otro-equipo.md#6-crear-el-certificado-de-esta-computadora). Instala ese nuevo archivo en el teléfono que probará tu servidor. Descargar el archivo de Drive antes de activar el proxy, o transferirlo por USB: el proxy solo permite la aplicación local.

La URL del teléfono sigue siendo **https://acceso.ada.test:8443/**; el host del proxy es la IP de la computadora que sirve el proyecto, con puerto **8899**. El certificado público por sí solo no configura red, proxy ni Apache. Nunca enviar la clave privada de la CA para reutilizar un `.cer`.
