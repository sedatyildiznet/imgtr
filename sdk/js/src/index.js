import { prepareInput, createEvents, publishEvents } from './upload.js';

export class ImgtrError extends Error {
  constructor(message,{code='request_failed',status=0,requestId=null,details=null}={}){super(message);this.name='ImgtrError';this.code=code;this.status=status;this.requestId=requestId;this.details=details}
}

export class ImgtrClient {
  constructor({apiKey,baseUrl='https://imgtr.xyz',fetch:fetchImplementation=globalThis.fetch,timeout=30000}={}){
    if(!apiKey)throw new TypeError('apiKey is required.');if(typeof fetchImplementation!=='function')throw new TypeError('A Fetch API implementation is required.');
    this.apiKey=apiKey;this.baseUrl=baseUrl.replace(/\/$/,'');this.fetch=fetchImplementation;this.timeout=timeout;
  }
  async request(path,{method='GET',body,query}={}){
    const url=new URL(this.baseUrl+path);for(const [key,value] of Object.entries(query||{}))if(value!==undefined&&value!==null&&value!=='')url.searchParams.set(key,String(value));
    let response;try{response=await this.fetch(url,{method,headers:{Authorization:`Bearer ${this.apiKey}`,Accept:'application/json',...(body?{'Content-Type':'application/json'}:{})},body:body?JSON.stringify(body):undefined,signal:AbortSignal.timeout(this.timeout)})}catch(error){throw new ImgtrError(error.name==='TimeoutError'?'IMGTR request timed out.':'IMGTR could not be reached.',{code:error.name==='TimeoutError'?'request_timeout':'network_error'})}
    const text=await response.text();let payload;try{payload=JSON.parse(text)}catch{throw new ImgtrError(`IMGTR returned a non-JSON response (${response.status}).`,{status:response.status})}
    if(!response.ok||payload.success===false){const error=payload.error||{};throw new ImgtrError(error.message||payload.message||`IMGTR request failed (${response.status}).`,{code:error.code||payload.code||'request_failed',status:response.status,requestId:payload.request_id||response.headers.get('x-request-id'),details:error.details})}
    const data=payload.data??payload;if(payload.meta&&data&&typeof data==='object')data.meta=payload.meta;return data;
  }
  me(){return this.request('/api/v1/user').then(data=>data.user)}
  listImages({limit=25,cursor}={}){return this.request('/api/v1/images',{query:{limit,cursor}})}
  getImage(id){return this.request('/api/v1/image',{query:{id}}).then(data=>data.image)}
  deleteImage(id){return this.request('/api/v1/image',{method:'DELETE',query:{id}})}
  deletionStatus(id){return this.request('/api/v1/deletion',{query:{id}}).then(data=>data.relay_deletion)}
  retryDeletion(id){return this.request('/api/v1/deletion',{method:'POST',query:{id}}).then(data=>data.relay_deletion)}
  relays(){return this.request('/api/v1/relays')}
  async upload(input,options={}){
    const prepared=await prepareInput(input,options);options.onProgress?.({phase:'prepare',completed:1,total:1});
    const initiated=await this.request('/api/v1/uploads',{method:'POST',body:{filename:prepared.filename,mime:prepared.mime,size:prepared.size,width:prepared.width,height:prepared.height,sha256:prepared.sha256,chunks:prepared.chunkHashes}});
    const upload=initiated.data??initiated;if(upload.duplicate)return {...upload,replicas:0,distribution:Promise.resolve({replicas:0,total:0})};
    const events=createEvents(upload,prepared),publication=publishEvents(upload,events,progress=>options.onProgress?.({phase:'publish',...progress}));
    const initialResults=await publication.ready;options.onProgress?.({phase:'verify',completed:initialResults.length,total:publication.total});
    const verified=await this.request('/api/v1/uploads-verify',{method:'POST',body:{id:upload.id,verification_token:upload.verification_token,relays:initialResults}});
    const distribution=(async()=>{const all=await publication.complete;let replicas=Number(verified.replicas||upload.minimum||0);const successful=all.filter(result=>result.ok);for(let offset=0;offset<successful.length;offset+=2){const checks=await Promise.allSettled(successful.slice(offset,offset+2).map(relay=>this.request('/api/v1/uploads-verify',{method:'POST',body:{id:upload.id,verification_token:upload.verification_token,relays:[relay]}})));for(const check of checks)if(check.status==='fulfilled')replicas=Math.max(replicas,Number(check.value.replicas||0));options.onProgress?.({phase:'verify',completed:Math.min(offset+2,successful.length),total:successful.length})}return {replicas,total:all.length,results:all}})();
    if(options.waitForDistribution){const final=await distribution;return {...upload,replicas:final.replicas,distribution:Promise.resolve(final)}}
    return {...upload,replicas:Number(verified.replicas||upload.minimum||0),distribution};
  }
}

export { prepareInput } from './upload.js';
