import {ApiClient} from './api-client.js';
let controller=null,generation=0;
export const AuthFlow={
  cancel(){generation++;controller?.abort();controller=null;},
  async request(path,options){this.cancel();controller=new AbortController();const current=generation;const result=await ApiClient.request(path,{...options,signal:controller.signal});return current===generation?result:{ok:false,code:'CANCELLED'};},
  begin(identifier,method){return this.request('/api/auth/begin',{method:'POST',body:{identifier,method}});},
  verify(state,payload,sample){const body=new FormData();body.append('challengeId',state.challengeId);body.append('method',state.method);body.append('payload',JSON.stringify(payload));if(sample)body.append('sample',sample,`sample.${extension(sample.type)}`);return this.request('/api/auth/verify',{method:'POST',body});}
};
export function extension(mime){return ({'image/jpeg':'jpg','image/png':'png','image/webp':'webp','audio/webm':'webm','audio/ogg':'ogg','audio/mp4':'mp4','audio/wav':'wav'})[mime.split(';')[0]]||'bin';}