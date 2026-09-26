import {ApiClient} from './api-client.js';
export async function capabilities(){
 const r=await ApiClient.request('/api/capabilities');if(!r.ok)throw new Error(r.message);
 const capture=!!navigator.mediaDevices?.getUserMedia;
 const voice=capture && !!window.MediaRecorder && ['audio/webm','audio/ogg','audio/mp4'].some(x=>MediaRecorder.isTypeSupported(x));
 let webauthn=false;try{webauthn=!!window.PublicKeyCredential && await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();}catch{}
 return {face:{enabled:capture&&r.data.face,reason:r.data.face?'Cámara no disponible en este contexto.':'Servicio facial no disponible.'},voice:{enabled:voice&&r.data.voice,reason:r.data.voice?'Grabación no compatible.':'Servicio de voz no disponible.'},webauthn:{enabled:webauthn&&r.data.webauthn,reason:'Configura el autenticador de plataforma o prueba en el teléfono.'},pattern:{enabled:r.data.pattern,reason:'Método deshabilitado.'}};
}