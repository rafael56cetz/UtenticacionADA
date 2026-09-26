const base = document.querySelector('meta[name="app-base"]')?.content || '';
export const url = path => base + path;
let csrf = null, sessionRequest = null;
export const ApiClient = {
  async getSession() {
    const response = await fetch(url('/api/session'), {credentials:'same-origin',cache:'no-store'});
    const result = await response.json();
    if(!response.ok || !result.ok) throw new Error(result.message || 'No se pudo cargar la sesión.');
    csrf = result.data.csrfToken; return result.data;
  },
  async request(path, {method='GET',body,signal}={}) {
    try {
      if(method!=='GET' && !csrf) {sessionRequest ||= this.getSession().finally(()=>{sessionRequest=null;});await sessionRequest;}
      const headers={Accept:'application/json'};
      if(method!=='GET')headers['X-CSRF-Token']=csrf;
      if(body!==undefined && !(body instanceof FormData)){headers['Content-Type']='application/json';body=JSON.stringify(body);}
      const response=await fetch(url(path),{method,body,signal,headers,credentials:'same-origin',cache:'no-store'});
      const json=await response.json();if(json.data?.csrfToken)csrf=json.data.csrfToken;
      return {...json,ok:response.ok && json.ok===true,status:response.status};
    }catch(error){return {ok:false,code:error.name==='AbortError'?'CANCELLED':'NETWORK_ERROR',message:error.name==='AbortError'?'Operación cancelada.':'No se pudo conectar con el servidor.',data:null,status:0};}
  }
};