import { createHash, randomBytes } from 'node:crypto';
import { basename, extname } from 'node:path';
import { readFile } from 'node:fs/promises';
import sharp from 'sharp';
import WebSocket from 'ws';
import { finalizeEvent, getPublicKey } from 'nostr-tools/pure';

const SUPPORTED=new Set(['image/jpeg','image/png','image/webp','image/gif']);
const sha=value=>createHash('sha256').update(value).digest('hex');
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));

function mimeFromName(name){const extension=extname(name).toLowerCase();return extension==='.jpg'||extension==='.jpeg'?'image/jpeg':extension==='.png'?'image/png':extension==='.webp'?'image/webp':extension==='.gif'?'image/gif':null}

export async function prepareInput(input,options={}){
  const source=Buffer.isBuffer(input)?input:await readFile(input);
  const sourceName=options.filename||(typeof input==='string'?basename(input):'image');
  const sourceMime=options.mime||mimeFromName(sourceName);
  if(!SUPPORTED.has(sourceMime))throw new Error('Only JPG, PNG, WEBP and GIF images are supported.');
  let data=source,mime=sourceMime,filename=sourceName,metadata;
  if(sourceMime!=='image/gif'){
    const pipeline=sharp(source,{limitInputPixels:options.maxPixels||40000000}).rotate();metadata=await pipeline.metadata();
    if(sourceMime==='image/png'){data=await pipeline.png().toBuffer();filename=sourceName.replace(/\.[^.]+$/,'.png')}
    else if(sourceMime==='image/webp'){data=await pipeline.webp({quality:92}).toBuffer();filename=sourceName.replace(/\.[^.]+$/,'.webp')}
    else{data=await pipeline.jpeg({quality:92,mozjpeg:true}).toBuffer();filename=sourceName.replace(/\.[^.]+$/,'.jpg')}
  }
  metadata=await sharp(data,{animated:mime==='image/gif',limitInputPixels:options.maxPixels||40000000}).metadata();
  if(!metadata.width||!metadata.height)throw new Error('The image dimensions could not be read.');
  if(data.length>(options.maxSize||10485760))throw new Error('The processed image exceeds the 10 MB upload limit.');
  const chunkSize=options.chunkSize||28000,chunks=[];for(let offset=0;offset<data.length;offset+=chunkSize)chunks.push(data.subarray(offset,offset+chunkSize));
  return {data,filename,mime,size:data.length,width:metadata.width,height:metadata.height,sha256:sha(data),chunks,chunkHashes:chunks.map(sha)};
}

export function createEvents(upload,prepared){
  const secret=randomBytes(32),pubkey=getPublicKey(secret),createdAt=Math.floor(Date.now()/1000);
  const meta={filename:prepared.filename,mime:prepared.mime,size:prepared.size,width:prepared.width,height:prepared.height,sha256:prepared.sha256,chunks:prepared.chunkHashes};
  const manifest=finalizeEvent({kind:30303,created_at:createdAt,tags:[['d',upload.id],['t','imgtr'],['sha256',prepared.sha256],['mime',prepared.mime]],content:JSON.stringify({protocol:'imgtr',version:2,...meta,chunks:prepared.chunks.length,pubkey})},secret);
  const chunks=prepared.chunks.map((chunk,index)=>finalizeEvent({kind:30304,created_at:createdAt,tags:[['d',`${upload.id}:${index}`],['chunk',String(index)],['sha256',prepared.chunkHashes[index]],['e',manifest.id]],content:chunk.toString('base64')},secret));
  const published=[manifest,...chunks],deletions=[];for(let offset=0;offset<published.length;offset+=50){const batch=published.slice(offset,offset+50),tags=[...batch.map(event=>['e',event.id]),['k','30303'],['k','30304']];if(offset===0)tags.push(['a',`30303:${pubkey}:${upload.id}`]);deletions.push(finalizeEvent({kind:5,created_at:createdAt+1,tags,content:'IMGTR deletion request'},secret))}
  secret.fill(0);
  return {manifest,chunks,deletions,all:published};
}

async function relayAttempt(url,events,windowSize=8,timeout=30000){
  return new Promise(resolve=>{let socket,next=0,acknowledged=0,finished=false,reason='',timer;const pending=new Map();
    const finish=ok=>{if(finished)return;finished=true;clearTimeout(timer);try{socket?.close()}catch{}resolve({ok,reason})};
    const pump=()=>{while(socket?.readyState===WebSocket.OPEN&&next<events.length&&pending.size<windowSize){const event=events[next++];pending.set(event.id,event);socket.send(JSON.stringify(['EVENT',event]))}};
    timer=setTimeout(()=>{reason='timeout';finish(false)},timeout);
    try{socket=new WebSocket(url)}catch{reason='connection';finish(false);return}
    socket.on('open',pump);socket.on('message',raw=>{try{const response=JSON.parse(String(raw));if(response[0]!=='OK'||!pending.has(response[1]))return;if(!response[2]){reason=String(response[3]||'rejected').slice(0,160);finish(false);return}pending.delete(response[1]);acknowledged++;if(acknowledged===events.length){finish(true);return}pump()}catch{}});
    socket.on('error',()=>{reason='connection';finish(false)});socket.on('close',()=>{if(!finished){reason='closed';finish(acknowledged===events.length)}});
  });
}

async function sendRelay(url,events){const first=await relayAttempt(url,events,8);if(first.ok)return first;await sleep(400);return relayAttempt(url,events,3)}

export function publishEvents(upload,events,onProgress=()=>{}){
  const results=[];let completed=0,accepted=0,readyDone=false,resolveReady,resolveComplete;
  const threshold=Math.min(upload.relays.length,(upload.minimum||2)+1);
  const ready=new Promise(resolve=>{resolveReady=resolve}),complete=new Promise(resolve=>{resolveComplete=resolve});
  const settle=()=>{if(!readyDone&&(accepted>=threshold||completed===upload.relays.length)){readyDone=true;resolveReady([...results])}if(completed===upload.relays.length)resolveComplete([...results])};
  for(const relay of upload.relays)(async()=>{const sent=await sendRelay(relay.url,events.all);completed++;if(sent.ok)accepted++;results.push({relay_id:relay.id,ok:sent.ok,reason:sent.reason,manifest_event_id:events.manifest.id,chunk_event_ids:events.chunks.map(event=>event.id),deletion_events:events.deletions});onProgress({completed,total:upload.relays.length,accepted,relay:relay.url,ok:sent.ok});settle()})();
  if(upload.relays.length===0)settle();
  return {ready,complete,total:upload.relays.length,get completed(){return completed}};
}
