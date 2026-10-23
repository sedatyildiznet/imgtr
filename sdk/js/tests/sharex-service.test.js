import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { createStartupLauncher, installSharexStartup, readSharexState, removeSharexState, sharexServicePaths, writeSharexState } from '../src/sharex-service.js';

test('Windows startup launcher starts the bridge hidden with quoted paths',()=>{
  const launcher=createStartupLauncher({nodePath:'C:\\Program Files\\nodejs\\node.exe',scriptPath:'C:\\Users\\Test User\\imgtr.js',port:43191});
  assert.match(launcher,/WScript\.Shell/);assert.match(launcher,/, 0, False/);assert.match(launcher,/sharex serve --port 43191 --background/);assert.match(launcher,/""C:\\Program Files\\nodejs\\node\.exe""/);
});

test('service state is written and removed without storing credentials',async()=>{
  const directory=await mkdtemp(join(tmpdir(),'imgtr-service-')),paths={state:join(directory,'state.json'),startup:join(directory,'Startup','IMGTR ShareX Bridge.vbs')};
  try{await writeSharexState({pid:123,port:43191,started_at:'test'},paths);assert.deepEqual(await readSharexState(paths),{pid:123,port:43191,started_at:'test'});assert.equal((await readFile(paths.state,'utf8')).includes('imgtr_'),false);await removeSharexState(paths);assert.equal(await readSharexState(paths),null)}finally{await rm(directory,{recursive:true,force:true})}
});

test('automatic startup installation creates a per-user hidden launcher',async()=>{
  const directory=await mkdtemp(join(tmpdir(),'imgtr-startup-')),paths={state:join(directory,'state.json'),startup:join(directory,'Startup','IMGTR ShareX Bridge.vbs')};
  try{const result=await installSharexStartup({port:43192,nodePath:'C:\\node.exe',scriptPath:'C:\\imgtr.js',paths,start:false});const launcher=await readFile(paths.startup,'utf8');assert.equal(result.port,43192);assert.equal(result.running,false);assert.match(launcher,/43192 --background/)}finally{await rm(directory,{recursive:true,force:true})}
});

test('service paths stay in the current user profile',()=>{
  const paths=sharexServicePaths({LOCALAPPDATA:'C:\\Users\\A\\AppData\\Local',APPDATA:'C:\\Users\\A\\AppData\\Roaming'});assert.match(paths.state,/AppData\\Local\\IMGTR\\sharex-bridge\.json$/);assert.match(paths.startup,/AppData\\Roaming\\Microsoft\\Windows\\Start Menu\\Programs\\Startup/);
});
