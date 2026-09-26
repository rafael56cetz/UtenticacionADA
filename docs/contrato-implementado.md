# Precisiones del contrato implementado

Complementa `02-contrato-de-integracion.md`; mantiene métodos, roles y formato `{ok,code,message,data,requestId}`. Los ejemplos son sintéticos.

- `GET /api/capabilities`: `data={face:true,voice:true,webauthn:true,pattern:true}`. Rostro/voz requieren configuración habilitada **y** salud del modelo. No consulta cuentas. El navegador añade disponibilidad de APIs/dispositivo.
- `GET /api/session`: `user` añade `method_used`; null si no hay sesión. Token CSRF renovado después del login.
- `POST /api/auth/recover`: JSON `{identifier,password}`; éxito devuelve redirectTo y csrfToken igual que verify. Contraseña de recuperación, mismos límites entre métodos. Página `/recovery`.
- `PUT /api/users/{id}/recovery`: Administrator, JSON `{password,confirmation}`, 12–128 caracteres; hash y revocación de sesiones. No se devuelve la contraseña.
- Listado de usuarios: filtros `search`, `role`, `active=0|1`, `page`. Respuesta `data={items:[],page:1,total:0,pages:1}`; 20 registros por página.
- Crear usuario: JSON `{identifier,name,role,active?}` con active booleano, true por defecto. Devuelve 201 y `{id}`. Identifier es inmutable; edición acepta solo name/role. El estado se cambia por su ruta separada.
- Detalle añade `hasRecovery`, `modalities` (cuatro booleanos) y `credentials:[{id:"pattern-1",method:"pattern"}]`. credentialId es el ID opaco de esa lista; no es el rawId WebAuthn.
- Enroll finish: multipart con challengeId, payload JSON y, para rostro/voz, exactamente tres `samples[]`. Patrón usa `{sequence:[...],confirmation:[...]}`. Biometría usa `{consent:true,noticeVersion:"academic-v1"}`. WebAuthn usa la credencial serializada de creación. No incluir método redundante: está en ruta y reto.
- Verify: `sample` único (8 MB máximo). Imágenes JPEG/PNG/WebP entre 160 y 4096 por eje, hasta 4 millones de píxeles, un rostro; audio decodificable de 3–12 segundos, mono 16 kHz internamente. La interfaz recomienda 5–8 segundos y limita la grabación a 10 segundos.
- Los retos vencen en 120 segundos. Un intento de verificación/registro consume su reto incluso cuando la evidencia falla; obtener uno nuevo para reintentar. Cancelar no concede sesión.
- WebAuthn de acceso usa credencial residente sin revelar allowCredentials de la cuenta. userHandle y la credencial deben pertenecer a la cuenta indicada. userVerification required; preferencia de plataforma. El servidor acepta contadores cero/sincronizados según la biblioteca y metadatos.
- `GET /api/access-attempts`: filtros method, outcome y page; mismo contenedor paginado. No devuelve reason_code privado, hashes ni IP. Página `/admin/attempts`.
- Las rutas de páginas protegidas responden 401/403 con una página de error, no fabrican una sesión ni redirigen al panel.

Errores relevantes: 401 ACCESS_DENIED (mensaje uniforme), 401 SESSION_REQUIRED, 403 CSRF_INVALID/FORBIDDEN, 409 LAST_ADMIN/INVALID_CHALLENGE/CONFLICT, 422 INVALID_INPUT/INVALID_SAMPLE, 429 RATE_LIMITED y 503 BIOMETRIC_UNAVAILABLE/SERVICE_UNAVAILABLE. El cliente decide por `ok` y estado HTTP conjuntamente. El único éxito biométrico aceptado proviene de inferencia del servicio con referencia cifrada de esa cuenta.
