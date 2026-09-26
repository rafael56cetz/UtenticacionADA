import {ApiClient,url} from './api-client.js';
import {AuthFlow} from './auth-flow.js';
import {capabilities} from './device-capabilities.js';
import {CaptureFace} from './capture-face.js';
import {CaptureVoice} from './capture-voice.js';
import {WebAuthnClient} from './webauthn-client.js';
import {PatternInput} from './pattern-input.js';
import {alertMessage,navigate} from './ui.js';
let disposed=false;
const cleanup=()=>{disposed=true;AuthFlow.cancel();CaptureFace.stopCamera();CaptureVoice.cleanup();};
window.addEventListener('pagehide',cleanup);
export async function init(page){
 try{await ApiClient.getSession();}catch(e){alertMessage(e.message);return;}
 if(page==='auth-select')return selection();
 if(page==='recovery'){
  document.getElementById('recovery-form').addEventListener('submit',async e=>{
   e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;
   const r=await AuthFlow.request('/api/auth/recover',{method:'POST',body:{identifier:document.getElementById('identifier').value,password:document.getElementById('password').value}});
   document.getElementById('password').value='';if(disposed)return;
   if(r.ok){await ApiClient.getSession();navigate(r.data.redirectTo);}else{alertMessage(r.message);button.disabled=false;}
  });return;
 }
 return verification();
}
async function selection(){
 let method=null;const cards=[...document.querySelectorAll('.method-card')],next=document.getElementById('btn-next');
 cards.forEach(card=>{card.disabled=true;card.addEventListener('click',()=>{method=card.dataset.method;cards.forEach(c=>{c.classList.toggle('selected',c===card);c.setAttribute('aria-checked',String(c===card));});next.disabled=false;});});
 try{const caps=await capabilities();for(const card of cards){const cap=caps[card.dataset.method];card.disabled=!cap.enabled;const label=card.querySelector('.method-status');label.textContent=cap.enabled?'Listo para iniciar':cap.reason;label.className='method-status '+(cap.enabled?'status-ready':'status-error');}}catch(e){alertMessage(e.message);}
 document.getElementById('auth-form').addEventListener('submit',async e=>{e.preventDefault();if(!method)return;next.disabled=true;const identifier=document.getElementById('identifier').value;const r=await AuthFlow.begin(identifier,method);if(disposed)return;if(r.ok){sessionStorage.setItem('ada.challenge',JSON.stringify({...r.data,method,identifier}));location.assign(url('/verify'));}else{alertMessage(r.message);next.disabled=false;}});
}
async function verification(){
 let state;try{state=JSON.parse(sessionStorage.getItem('ada.challenge'));}catch{}
 if(!state?.challengeId||!['face','voice','webauthn','pattern'].includes(state.method)){location.replace(url('/'));return;}
 const labels={face:'Reconocimiento facial',voice:'Reconocimiento de voz',webauthn:'Huella / dispositivo',pattern:'Patrón de acceso'};
 document.getElementById('verify-title').textContent=labels[state.method];
 const container=document.getElementById('interactive-container'),verify=document.getElementById('btn-verify');
 let sample=null,payload={},pattern=null,objectUrl=null,busy=false;
 const cancel=()=>{sessionStorage.removeItem('ada.challenge');if(objectUrl)URL.revokeObjectURL(objectUrl);cleanup();location.assign(url('/'));};
 document.getElementById('btn-cancel').addEventListener('click',cancel);
 const submit=async()=>{
  if(busy)return;busy=true;verify.disabled=true;alertMessage('Verificando…','info');
  const r=await AuthFlow.verify(state,payload,sample);if(disposed)return;
  pattern?.clear();payload={};sample=null;
  if(r.ok){sessionStorage.removeItem('ada.challenge');cleanup();await ApiClient.getSession();navigate(r.data.redirectTo);return;}
  alertMessage(r.message);busy=false;
  busy=true;const fresh=await AuthFlow.begin(state.identifier,state.method);if(disposed)return;
  if(fresh.ok){busy=false;state={...state,...fresh.data};sessionStorage.setItem('ada.challenge',JSON.stringify(state));if(state.method==='webauthn')verify.disabled=false;}else{alertMessage(fresh.message+' Vuelve a la selección para intentarlo otra vez.');verify.disabled=true;}
 };
 if(state.method==='pattern'){
  document.getElementById('verify-desc').textContent='Selecciona de 6 a 9 puntos distintos. Puedes usar clic, arrastre o flechas y Enter.';
  verify.classList.remove('hidden');verify.disabled=true;pattern=new PatternInput(container,sequence=>{payload={sequence};verify.disabled=busy||sequence.length<6;});verify.addEventListener('click',submit);
 }else if(state.method==='webauthn'){
  document.getElementById('verify-desc').textContent='Mediante el dispositivo; puede ofrecer huella, PIN u otro desbloqueo. Usa la credencial de esta cuenta.';
  verify.classList.remove('hidden');verify.textContent='Verificar con el dispositivo';verify.addEventListener('click',async()=>{verify.disabled=true;const r=await WebAuthnClient.authenticate(state.publicKeyOptions);if(disposed)return;if(r.ok){payload=r.payload;await submit();}else{alertMessage(r.error);verify.disabled=false;}});
 }else{
  document.getElementById('verify-desc').textContent=state.method==='face'?'Compararemos una foto nueva con el registro de esta cuenta. No se ofrece prueba de vida.':'Graba entre 5 y 8 segundos de voz. Compararemos el hablante; no hay defensa garantizada ante reproducciones.';
  container.innerHTML=state.method==='face'?'<video class="camera-preview" autoplay playsinline muted></video><img class="capture-result hidden" alt="Captura para verificar"><button class="btn btn-secondary" type="button" id="capture">Iniciar cámara</button>':'<p id="duration">Sin grabación</p><audio controls class="hidden"></audio><button class="btn btn-secondary" type="button" id="capture">Iniciar grabación</button>';
  let capturing=false,start=0;const action=document.getElementById('capture');verify.classList.remove('hidden');verify.disabled=true;verify.addEventListener('click',submit);
  action.addEventListener('click',async()=>{
   action.disabled=true;try{
    if(!capturing){verify.disabled=true;sample=null;const r=state.method==='face'?await CaptureFace.startCamera(container.querySelector('video')):await CaptureVoice.startRecording(()=>{document.getElementById('duration').textContent=`Grabando: ${Math.floor((Date.now()-start)/1000)} s`;});
     if(disposed){CaptureFace.stopCamera();CaptureVoice.cleanup();return;}if(!r.ok)throw new Error(r.error);start=Date.now();capturing=true;action.textContent=state.method==='face'?'Tomar foto':'Detener grabación';
    }else{sample=state.method==='face'?await CaptureFace.capture():await CaptureVoice.stopRecording();CaptureFace.stopCamera();capturing=false;if(!sample)throw new Error('No se obtuvo una muestra.');if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=URL.createObjectURL(sample);const preview=container.querySelector(state.method==='face'?'img':'audio');preview.src=objectUrl;preview.classList.remove('hidden');verify.disabled=false;action.textContent='Repetir captura';}
   }catch(e){alertMessage(e.message);}finally{action.disabled=false;}
  });
 }
}
