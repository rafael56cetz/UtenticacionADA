import {ApiClient,url} from './api-client.js';
import {alertMessage} from './ui.js';
import {extension} from './auth-flow.js';
import {CaptureFace} from './capture-face.js';
import {CaptureVoice} from './capture-voice.js';
import {PatternInput} from './pattern-input.js';
import {WebAuthnClient} from './webauthn-client.js';
export async function init(){
 const main=document.querySelector('main'),id=Number(main.dataset.userId),method=main.dataset.method;
 const consent=document.getElementById('consent-container'),capture=document.getElementById('capture-container'),accept=document.getElementById('btn-accept-consent'),finish=document.getElementById('btn-finish'),progress=document.getElementById('progress-container');
 let disposed=false,challenge=null,payload={},samples=[];
 window.addEventListener('pagehide',()=>{disposed=true;CaptureFace.stopCamera();CaptureVoice.cleanup();samples=[];payload={};});
 const user=await ApiClient.request(`/api/users/${id}`);if(!user.ok){alertMessage(user.message);accept.disabled=true;return;}
 document.getElementById('enroll-desc').textContent=`Titular: ${user.data.name}. La captura debe realizarla esa persona.`;
 consent.querySelector('.form-desc').textContent=['face','voice'].includes(method)?'Uso voluntario para esta demostración académica. Las muestras se procesan en esta computadora y se descartan; las plantillas se conservan cifradas hasta revocarlas o finalizar la evaluación. El administrador puede retirar el consentimiento y borrarlas desde la cuenta. No hay garantía contra fotos, reproducciones o voz clonada. Versión academic-v1.':'Registra la credencial del titular. No compartas el patrón. El dispositivo puede usar huella, PIN u otro desbloqueo.';
 accept.addEventListener('click',async()=>{accept.disabled=true;const r=await ApiClient.request(`/api/users/${id}/enroll/${method}/begin`,{method:'POST',body:{}});if(disposed)return;if(!r.ok){alertMessage(r.message);accept.disabled=false;return;}challenge=r.data;consent.classList.add('hidden');capture.classList.remove('hidden');document.getElementById('action-container').classList.remove('hidden');setup();});
 function setup(){
  if(method==='pattern'){
   capture.innerHTML='<div id="enroll-pattern"></div><p id="pattern-instruction">Selecciona de 6 a 9 puntos y pulsa Confirmar selección.</p><button type="button" class="btn btn-secondary" id="pattern-confirm" disabled>Confirmar selección</button>';
   let first=null,sequence=[];const confirmButton=document.getElementById('pattern-confirm'),instruction=document.getElementById('pattern-instruction');const pattern=new PatternInput(document.getElementById('enroll-pattern'),s=>{sequence=s;confirmButton.disabled=s.length<6;finish.disabled=true;});
   confirmButton.onclick=()=>{if(!first){first=[...sequence];pattern.clear();instruction.textContent='Repite el patrón y confirma otra vez.';}else if(JSON.stringify(first)===JSON.stringify(sequence)){payload={sequence:[...first],confirmation:[...sequence]};finish.disabled=false;confirmButton.disabled=true;instruction.textContent='Confirmación concordante. Pulsa Finalizar registro.';}else{first=null;pattern.clear();instruction.textContent='Los patrones no coinciden. Inicia una nueva selección.';}};
  }else if(method==='webauthn'){
   capture.innerHTML='<p>Se abrirá el diálogo nativo. El registro se confirma cuando lo valida el servidor.</p><button type="button" class="btn btn-primary" id="device-register">Registrar en el dispositivo</button>';
   const button=document.getElementById('device-register');button.onclick=async()=>{button.disabled=true;const r=await WebAuthnClient.register(challenge.publicKeyOptions);if(disposed)return;if(r.ok){payload=r.payload;finish.disabled=false;alertMessage('Credencial capturada. Pulsa Finalizar registro.','info');}else{alertMessage(r.error);button.disabled=false;}};
  }else{
   capture.innerHTML=method==='face'?'<p>Captura tres fotos con buena luz y un solo rostro, variando ligeramente la posición.</p><video class="camera-preview" autoplay playsinline muted></video><button type="button" class="btn btn-secondary" id="take-sample">Iniciar cámara</button>':'<p>Graba tres muestras independientes de 5 a 8 segundos hablando con naturalidad.</p><p id="record-time">Sin grabación</p><button type="button" class="btn btn-secondary" id="take-sample">Iniciar grabación</button>';
   payload={consent:true,noticeVersion:'academic-v1'};progress.classList.remove('hidden');progress.textContent='Muestras: 0 / 3';let active=false,start=0;const button=document.getElementById('take-sample');
   button.onclick=async()=>{button.disabled=true;try{if(!active){const r=method==='face'?await CaptureFace.startCamera(capture.querySelector('video')):await CaptureVoice.startRecording(()=>{document.getElementById('record-time').textContent=`Grabando: ${Math.floor((Date.now()-start)/1000)} s`;});if(disposed){CaptureFace.stopCamera();CaptureVoice.cleanup();return;}if(!r.ok)throw new Error(r.error);start=Date.now();active=true;button.textContent=method==='face'?'Tomar foto':'Detener grabación';}else{const sample=method==='face'?await CaptureFace.capture():await CaptureVoice.stopRecording();if(!sample)throw new Error('No se capturó una muestra.');samples.push(sample);progress.textContent=`Muestras: ${samples.length} / 3`;if(method==='voice'){active=false;button.textContent='Siguiente grabación';}if(samples.length===3){CaptureFace.stopCamera();CaptureVoice.cleanup();finish.disabled=false;button.classList.add('hidden');}}}catch(e){alertMessage(e.message);}finally{button.disabled=false;}};
  }
 }
 finish.addEventListener('click',async()=>{finish.disabled=true;accept.disabled=true;const body=new FormData();body.append('challengeId',challenge.challengeId);body.append('payload',JSON.stringify(payload));samples.forEach((blob,i)=>body.append('samples[]',blob,`sample-${i}.${extension(blob.type)}`));const r=await ApiClient.request(`/api/users/${id}/enroll/${method}/finish`,{method:'POST',body});samples=[];payload={};if(disposed)return;if(r.ok)location.assign(url(`/admin/users/${id}`));else{alertMessage(r.message+' Reinicia el registro para obtener un reto nuevo.');const restart=document.createElement('button');restart.className='btn btn-secondary';restart.textContent='Reiniciar registro';restart.onclick=()=>location.reload();capture.append(restart);}});
}
