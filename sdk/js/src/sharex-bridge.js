import { createServer } from 'node:http';

const DEFAULT_LIMIT=12*1024*1024;

function send(response,status,payload){
  const body=JSON.stringify(payload);
  response.writeHead(status,{'Content-Type':'application/json; charset=utf-8','Content-Length':Buffer.byteLength(body),'Cache-Control':'no-store','X-Content-Type-Options':'nosniff'});
  response.end(body);
}

function loopback(address){return address==='127.0.0.1'||address==='::1'||address==='::ffff:127.0.0.1'}

async function readBody(request,limit){
  const declared=Number(request.headers['content-length']||0);
  if(declared>limit){const error=new Error('The screenshot exceeds the local bridge limit.');error.status=413;throw error}
  const chunks=[];let size=0;
  for await(const chunk of request){size+=chunk.length;if(size>limit){const error=new Error('The screenshot exceeds the local bridge limit.');error.status=413;throw error}chunks.push(chunk)}
  return Buffer.concat(chunks,size);
}

export function createSharexBridge({upload,host='127.0.0.1',port=43191,maximumBody=DEFAULT_LIMIT}={}){
  if(typeof upload!=='function')throw new TypeError('upload callback is required.');
  const server=createServer(async(request,response)=>{
    try{
      if(!loopback(request.socket.remoteAddress||'')){send(response,403,{success:false,error:{code:'loopback_required',message:'IMGTR ShareX bridge accepts local requests only.'}});return}
      if(request.headers.origin){send(response,403,{success:false,error:{code:'browser_request_rejected',message:'Browser-originated requests are not accepted.'}});return}
      const url=new URL(request.url||'/','http://127.0.0.1');
      if(request.method==='GET'&&url.pathname==='/health'){send(response,200,{success:true,data:{service:'imgtr-sharex-bridge',status:'running',pid:process.pid,port:Number(server.address()?.port||port)}});return}
      if(request.method!=='POST'||url.pathname!=='/upload'){send(response,404,{success:false,error:{code:'not_found',message:'Local IMGTR upload endpoint not found.'}});return}
      const authorization=String(request.headers.authorization||'');
      const match=authorization.match(/^Bearer\s+(imgtr_[a-f0-9]{48})$/i);
      if(!match){send(response,401,{success:false,error:{code:'authentication_required',message:'The IMGTR ShareX configuration is missing a valid API key.'}});return}
      const contentType=String(request.headers['content-type']||'');
      if(!/^multipart\/form-data;\s*boundary=/i.test(contentType)){send(response,415,{success:false,error:{code:'multipart_required',message:'ShareX must send the image as multipart form data.'}});return}
      const body=await readBody(request,maximumBody);
      const formRequest=new Request('http://127.0.0.1/upload',{method:'POST',headers:{'Content-Type':contentType},body});
      const form=await formRequest.formData(),file=form.get('file');
      if(!file||typeof file.arrayBuffer!=='function'){send(response,422,{success:false,error:{code:'file_required',message:'The multipart file field must be named file.'}});return}
      const binary=Buffer.from(await file.arrayBuffer());
      const result=await upload({apiKey:match[1],buffer:binary,filename:file.name||'sharex.png',mime:file.type||undefined});
      if(!result?.url)throw new Error('IMGTR did not return a viewer URL.');
      send(response,200,{success:true,data:{id:result.id,url:result.url,direct_url:result.direct_url,replicas:result.replicas}});
    }catch(error){const status=Number.isInteger(error.status)&&error.status>=400&&error.status<600?error.status:502;send(response,status,{success:false,error:{code:error.code||'sharex_upload_failed',message:error.message||'The IMGTR upload failed.'},request_id:error.requestId||null})}
  });
  return {server,listen:()=>new Promise((resolve,reject)=>{server.once('error',reject);server.listen(port,host,()=>{server.off('error',reject);resolve(server.address())})}),close:()=>new Promise((resolve,reject)=>server.close(error=>error?reject(error):resolve()))};
}
