import { spawn } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import process from 'node:process';

const STARTUP_NAME='IMGTR ShareX Bridge.vbs';
const sleep=milliseconds=>new Promise(resolve=>setTimeout(resolve,milliseconds));

export function sharexServicePaths(env=process.env){
  const dataRoot=env.LOCALAPPDATA||join(homedir(),'AppData','Local');
  const startupRoot=env.APPDATA?join(env.APPDATA,'Microsoft','Windows','Start Menu','Programs','Startup'):null;
  return {state:join(dataRoot,'IMGTR','sharex-bridge.json'),startup:startupRoot?join(startupRoot,STARTUP_NAME):null};
}

export function createStartupLauncher({nodePath,scriptPath,port=43191}){
  const command=`"${nodePath}" "${scriptPath}" sharex serve --port ${port} --background`;
  return `Set IMGTRShell = CreateObject("WScript.Shell")\r\nIMGTRShell.Run "${command.replaceAll('"','""')}", 0, False\r\n`;
}

export async function readSharexState(paths=sharexServicePaths()){
  try{return JSON.parse(await readFile(paths.state,'utf8'))}catch{return null}
}

export async function writeSharexState(state,paths=sharexServicePaths()){
  await mkdir(dirname(paths.state),{recursive:true});
  await writeFile(paths.state,JSON.stringify(state,null,2)+'\n',{mode:0o600});
}

export async function removeSharexState(paths=sharexServicePaths()){await rm(paths.state,{force:true})}

export async function sharexHealth({port=43191,fetchImpl=globalThis.fetch}={}){
  try{const response=await fetchImpl(`http://127.0.0.1:${port}/health`,{headers:{Accept:'application/json'},signal:AbortSignal.timeout(900)});const payload=await response.json();return response.ok&&payload?.data?.service==='imgtr-sharex-bridge'?payload.data:null}catch{return null}
}

async function waitForHealth(port,expected=true,attempts=24){
  for(let attempt=0;attempt<attempts;attempt++){const health=await sharexHealth({port});if(Boolean(health)===expected)return health;await sleep(150)}
  return null;
}

export async function startSharexBackground({port=43191,nodePath=process.execPath,scriptPath}={}){
  if(process.platform!=='win32')throw new Error('Background ShareX integration is currently supported on Windows only.');
  const running=await sharexHealth({port});if(running)return {alreadyRunning:true,...running};
  if(!scriptPath)throw new Error('The IMGTR CLI script path is required.');
  const child=spawn(nodePath,[scriptPath,'sharex','serve','--port',String(port),'--background'],{detached:true,windowsHide:true,stdio:'ignore',env:{...process.env,IMGTR_SHAREX_BACKGROUND:'1'}});
  child.once('error',()=>{});child.unref();
  const health=await waitForHealth(port,true);if(!health)throw new Error('The IMGTR ShareX bridge could not start. Reinstall the SDK and allow the sharp install script if npm reported it as blocked.');
  return {alreadyRunning:false,...health};
}

export async function stopSharexBackground({port,kill=process.kill,paths=sharexServicePaths()}={}){
  const state=await readSharexState(paths),activePort=port||state?.port||43191,health=await sharexHealth({port:activePort});
  const pid=Number(health?.pid||0);if(!health||!pid){await removeSharexState(paths);return {wasRunning:false,port:activePort}}
  if(pid>0&&pid!==process.pid){try{kill(pid)}catch(error){if(error.code!=='ESRCH')throw error}}
  await waitForHealth(activePort,false,20);await removeSharexState(paths);return {wasRunning:true,port:activePort,pid};
}

export async function installSharexStartup({port=43191,nodePath=process.execPath,scriptPath,paths=sharexServicePaths(),start=true}={}){
  if(process.platform!=='win32')throw new Error('Automatic ShareX startup is currently supported on Windows only.');
  if(!paths.startup)throw new Error('The Windows Startup folder could not be located.');
  if(!scriptPath)throw new Error('The IMGTR CLI script path is required.');
  await mkdir(dirname(paths.startup),{recursive:true});
  await writeFile(paths.startup,createStartupLauncher({nodePath,scriptPath,port}),'utf8');
  const result=start?await startSharexBackground({port,nodePath,scriptPath}):null;
  return {startup:paths.startup,port,running:Boolean(result),alreadyRunning:Boolean(result?.alreadyRunning)};
}

export async function uninstallSharexStartup({paths=sharexServicePaths(),stop=true}={}){
  const state=await readSharexState(paths);if(stop)await stopSharexBackground({port:state?.port,paths});
  if(paths.startup)await rm(paths.startup,{force:true});
  return {removed:true,startup:paths.startup};
}
