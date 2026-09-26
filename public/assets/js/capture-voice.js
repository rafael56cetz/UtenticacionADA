/** Real MediaRecorder formats; bounded capture and cleanup on every exit. */
const MIMES = ['audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus','audio/mp4'];
let recorder=null,stream=null,chunks=[],tick=null,limit=null,generation=0,result=null,resolveResult=null;
function stopTracks(){stream?.getTracks().forEach(t=>t.stop());stream=null;}
function finish(blob){clearInterval(tick);clearTimeout(limit);tick=limit=null;stopTracks();chunks=[];resolveResult?.(blob);resolveResult=null;}
function cleanup(){generation++;clearInterval(tick);clearTimeout(limit);tick=limit=null;const old=recorder;recorder=null;if(old){old.ondataavailable=old.onstop=old.onerror=null;if(old.state!=='inactive')old.stop();}finish(null);result=null;}
async function startRecording(onTick){
 cleanup();const current=generation;
 if(!window.MediaRecorder||!navigator.mediaDevices?.getUserMedia)return {ok:false,error:'La grabación requiere un navegador compatible y un contexto seguro.'};
 try{
  const acquired=await navigator.mediaDevices.getUserMedia({audio:true,video:false});
  if(current!==generation){acquired.getTracks().forEach(t=>t.stop());return {ok:false,error:'Grabación cancelada.'};}
  stream=acquired;const mime=MIMES.find(m=>MediaRecorder.isTypeSupported(m));
  recorder=new MediaRecorder(stream,mime?{mimeType:mime}:{});const active=recorder;
  result=new Promise(resolve=>{resolveResult=resolve;});chunks=[];
  active.ondataavailable=e=>{if(current===generation&&e.data.size)chunks.push(e.data);};
  active.onerror=()=>{if(current===generation){if(active.state!=='inactive')active.stop();finish(null);}};
  active.onstop=()=>{if(current===generation)finish(new Blob(chunks,{type:active.mimeType||mime||'audio/webm'}));};
  active.start(250);
  if(typeof onTick==='function')tick=setInterval(onTick,500);
  limit=setTimeout(()=>{if(active.state!=='inactive')active.stop();},10000);
  return {ok:true,mimeType:active.mimeType};
 }catch(error){cleanup();return {ok:false,error:error.name==='NotAllowedError'?'Permiso de micrófono denegado. Revisa los permisos del sitio.':'No se pudo iniciar el micrófono. Comprueba que esté disponible.'};}
}
async function stopRecording(){if(!result)return null;if(recorder?.state!=='inactive')recorder.stop();return result;}
window.addEventListener('pagehide',cleanup);
window.addEventListener('beforeunload',cleanup);
export const CaptureVoice={startRecording,stopRecording,cleanup};