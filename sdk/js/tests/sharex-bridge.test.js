import test from 'node:test';
import assert from 'node:assert/strict';
import { createSharexBridge } from '../src/sharex-bridge.js';

const key='imgtr_'+('a'.repeat(48));

test('ShareX bridge exposes a loopback health check for background management',async()=>{
  const bridge=createSharexBridge({port:0,upload:async()=>{throw new Error('not expected')}}),address=await bridge.listen();
  try{const response=await fetch(`http://127.0.0.1:${address.port}/health`),payload=await response.json();assert.equal(response.status,200);assert.equal(payload.data.service,'imgtr-sharex-bridge');assert.equal(payload.data.status,'running');assert.equal(payload.data.port,address.port)}finally{await bridge.close()}
});

test('ShareX bridge accepts an authenticated local multipart image and returns the IMGTR URL envelope',async()=>{
  let received;
  const bridge=createSharexBridge({port:0,upload:async input=>{received=input;return {id:'Ab12Cd',url:'https://imgtr.xyz/Ab12Cd',direct_url:'https://imgtr.xyz/Ab12Cd.png',replicas:3}}});
  const address=await bridge.listen();
  try{
    const form=new FormData();form.append('file',new Blob([Buffer.from('png-test')],{type:'image/png'}),'capture.png');
    const response=await fetch(`http://127.0.0.1:${address.port}/upload`,{method:'POST',headers:{Authorization:`Bearer ${key}`},body:form});
    const payload=await response.json();
    assert.equal(response.status,200);assert.equal(payload.success,true);assert.equal(payload.data.url,'https://imgtr.xyz/Ab12Cd');assert.equal(received.apiKey,key);assert.equal(received.filename,'capture.png');assert.equal(received.mime,'image/png');assert.equal(received.buffer.toString(),'png-test');
  }finally{await bridge.close()}
});

test('ShareX bridge rejects missing credentials without invoking upload',async()=>{
  let invoked=false;const bridge=createSharexBridge({port:0,upload:async()=>{invoked=true}});const address=await bridge.listen();
  try{const form=new FormData();form.append('file',new Blob(['x'],{type:'image/png'}),'x.png');const response=await fetch(`http://127.0.0.1:${address.port}/upload`,{method:'POST',body:form});const payload=await response.json();assert.equal(response.status,401);assert.equal(payload.error.code,'authentication_required');assert.equal(invoked,false)}finally{await bridge.close()}
});

test('ShareX bridge rejects browser-originated requests',async()=>{
  const bridge=createSharexBridge({port:0,upload:async()=>{throw new Error('not expected')}});const address=await bridge.listen();
  try{const response=await fetch(`http://127.0.0.1:${address.port}/upload`,{method:'POST',headers:{Origin:'https://example.com',Authorization:`Bearer ${key}`}});const payload=await response.json();assert.equal(response.status,403);assert.equal(payload.error.code,'browser_request_rejected')}finally{await bridge.close()}
});

test('ShareX bridge preserves a revoked API-key response without exposing the key',async()=>{
  const bridge=createSharexBridge({port:0,upload:async()=>{const error=new Error('API key is invalid or revoked.');error.status=401;error.code='invalid_api_key';error.requestId='req-test';throw error}});const address=await bridge.listen();
  try{const form=new FormData();form.append('file',new Blob(['x'],{type:'image/png'}),'x.png');const response=await fetch(`http://127.0.0.1:${address.port}/upload`,{method:'POST',headers:{Authorization:`Bearer ${key}`},body:form});const raw=await response.text(),payload=JSON.parse(raw);assert.equal(response.status,401);assert.equal(payload.error.code,'invalid_api_key');assert.equal(payload.request_id,'req-test');assert.equal(raw.includes(key),false)}finally{await bridge.close()}
});

test('ShareX bridge requires the configured multipart field name',async()=>{
  let invoked=false;const bridge=createSharexBridge({port:0,upload:async()=>{invoked=true}});const address=await bridge.listen();
  try{const form=new FormData();form.append('image',new Blob(['x'],{type:'image/png'}),'x.png');const response=await fetch(`http://127.0.0.1:${address.port}/upload`,{method:'POST',headers:{Authorization:`Bearer ${key}`},body:form});const payload=await response.json();assert.equal(response.status,422);assert.equal(payload.error.code,'file_required');assert.equal(invoked,false)}finally{await bridge.close()}
});
