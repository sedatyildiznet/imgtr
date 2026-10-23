#!/usr/bin/env node
import { access, readFile, writeFile, mkdir, unlink } from 'node:fs/promises';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { ImgtrClient, ImgtrError } from '../src/index.js';
import { createSharexBridge } from '../src/sharex-bridge.js';
import { installSharexStartup, readSharexState, removeSharexState, sharexHealth, sharexServicePaths, startSharexBackground, stopSharexBackground, uninstallSharexStartup, writeSharexState } from '../src/sharex-service.js';

const configPath=process.env.IMGTR_CONFIG||join(homedir(),'.config','imgtr','config.json');
const cliPath=fileURLToPath(import.meta.url);
const jsonFlag=process.argv.includes('--json');
const args=process.argv.slice(2).filter(arg=>arg!=='--json');
const value=(name,fallback)=>{const index=args.indexOf(name);return index>=0&&args[index+1]?args[index+1]:fallback};
async function config(){try{return JSON.parse(await readFile(configPath,'utf8'))}catch{return {}}}
function print(value){process.stdout.write((jsonFlag?JSON.stringify(value,null,2):typeof value==='string'?value:JSON.stringify(value,null,2))+'\n')}
function help(){print(`IMGTR CLI

Usage:
  imgtr upload <file...> [--json]
  imgtr images [--limit 25] [--cursor CURSOR] [--json]
  imgtr image <id> [--json]
  imgtr delete <id> --yes
  imgtr deletion <id> [--retry] [--json]
  imgtr user [--json]
  imgtr relays [--json]
  imgtr sharex install [--port 43191]
  imgtr sharex start [--port 43191]
  imgtr sharex stop
  imgtr sharex status [--json]
  imgtr sharex uninstall
  imgtr sharex serve [--port 43191]
  imgtr config set-key <imgtr_key>
  imgtr config set-url <https://host>
  imgtr config show
  imgtr config clear

Environment: IMGTR_API_KEY, IMGTR_BASE_URL, IMGTR_CONFIG`)}
async function saveConfig(next){await mkdir(dirname(configPath),{recursive:true});await writeFile(configPath,JSON.stringify(next,null,2)+'\n',{mode:0o600})}
async function run(){
  const [command,...rest]=args;if(!command||command==='help'||command==='--help'||command==='-h'){help();return}
  if(command==='config'){
    const current=await config(),action=rest[0];
    if(action==='set-key'){if(!/^imgtr_[a-f0-9]{48}$/i.test(rest[1]||''))throw new Error('A valid imgtr_ API key is required.');await saveConfig({...current,apiKey:rest[1]});print('API key saved.');return}
    if(action==='set-url'){const url=new URL(rest[1]);if(url.protocol!=='https:'&&url.hostname!=='localhost')throw new Error('The IMGTR URL must use HTTPS.');await saveConfig({...current,baseUrl:url.toString().replace(/\/$/,'')});print('Base URL saved.');return}
    if(action==='show'){print({config:configPath,baseUrl:current.baseUrl||'https://imgtr.xyz',apiKey:current.apiKey?`${current.apiKey.slice(0,10)}…${current.apiKey.slice(-4)}`:null});return}
    if(action==='clear'){try{await unlink(configPath)}catch{}print('Configuration cleared.');return}
    throw new Error('Unknown config action.');
  }
  const saved=await config(),apiKey=process.env.IMGTR_API_KEY||saved.apiKey,baseUrl=process.env.IMGTR_BASE_URL||saved.baseUrl||'https://imgtr.xyz';
  if(command==='sharex'){
    const action=rest[0]||'status';
    const port=Number(value('--port',43191));if(!Number.isInteger(port)||port<1024||port>65535)throw new Error('ShareX bridge port must be between 1024 and 65535.');
    if(action==='install'){const result=await installSharexStartup({port,scriptPath:cliPath});print(jsonFlag?result:`IMGTR ShareX background integration installed.\nStartup: ${result.startup}\nBridge: http://127.0.0.1:${port}/upload`);return}
    if(action==='start'){const result=await startSharexBackground({port,scriptPath:cliPath});print(jsonFlag?result:result.alreadyRunning?'IMGTR ShareX bridge is already running.':`IMGTR ShareX bridge started in the background on port ${port}.`);return}
    if(action==='stop'){const state=await readSharexState(),result=await stopSharexBackground({port:state?.port});print(jsonFlag?result:result.wasRunning?'IMGTR ShareX bridge stopped.':'IMGTR ShareX bridge is not running.');return}
    if(action==='status'){const state=await readSharexState(),activePort=state?.port||port,health=await sharexHealth({port:activePort}),startup=sharexServicePaths().startup,installed=Boolean(startup&&await access(startup).then(()=>true,()=>false));const result={installed,running:Boolean(health),port:activePort,pid:health?.pid||null,started_at:state?.started_at||null};print(jsonFlag?result:`IMGTR ShareX\nInstalled: ${installed?'yes':'no'}\nRunning: ${health?'yes':'no'}\nPort: ${activePort}${health?.pid?`\nPID: ${health.pid}`:''}`);return}
    if(action==='uninstall'){const result=await uninstallSharexStartup();print(jsonFlag?result:'IMGTR ShareX background integration removed.');return}
    if(action!=='serve')throw new Error('Usage: imgtr sharex <install|start|stop|status|uninstall|serve>');
    const bridge=createSharexBridge({port,upload:async({apiKey:requestKey,buffer,filename,mime})=>new ImgtrClient({apiKey:requestKey,baseUrl}).upload(buffer,{filename,mime,waitForDistribution:false})});
    await bridge.listen();await writeSharexState({pid:process.pid,port,started_at:new Date().toISOString(),background:args.includes('--background')});if(!args.includes('--background')){process.stdout.write(`IMGTR ShareX bridge listening on http://127.0.0.1:${port}/upload\n`);process.stdout.write('Press Ctrl+C to stop. Use "imgtr sharex install" for invisible automatic startup.\n')}
    const stop=async()=>{try{await bridge.close()}finally{const state=await readSharexState();if(state?.pid===process.pid)await removeSharexState();process.exit(0)}};process.once('SIGINT',stop);process.once('SIGTERM',stop);return
  }
  if(!apiKey)throw new Error('No API key. Run: imgtr config set-key <key>');const client=new ImgtrClient({apiKey,baseUrl});
  if(command==='upload'){
    const files=rest.filter(arg=>!arg.startsWith('--'));if(!files.length)throw new Error('At least one image path is required.');const output=[];
    for(const file of files){if(!jsonFlag)process.stderr.write(`Uploading ${file}…\n`);const result=await client.upload(file,{waitForDistribution:true,onProgress:event=>{if(!jsonFlag&&event.phase==='publish')process.stderr.write(`\rRelay publication ${event.completed}/${event.total}`)}});if(!jsonFlag)process.stderr.write('\n');output.push({id:result.id,url:result.url,direct_url:result.direct_url,replicas:result.replicas})}print(output.length===1?output[0]:output);return
  }
  if(command==='images'){print(await client.listImages({limit:Number(value('--limit',25)),cursor:value('--cursor')}));return}
  if(command==='image'){if(!rest[0])throw new Error('Image id is required.');print(await client.getImage(rest[0]));return}
  if(command==='delete'){if(!rest[0])throw new Error('Image id is required.');if(!args.includes('--yes'))throw new Error('Deletion requires --yes.');print(await client.deleteImage(rest[0]));return}
  if(command==='deletion'){if(!rest[0])throw new Error('Image id is required.');print(args.includes('--retry')?await client.retryDeletion(rest[0]):await client.deletionStatus(rest[0]));return}
  if(command==='user'){print(await client.me());return}
  if(command==='relays'){print(await client.relays());return}
  throw new Error(`Unknown command: ${command}`);
}
run().catch(error=>{if(jsonFlag)print({success:false,error:{code:error.code||'cli_error',message:error.message,request_id:error.requestId||null}});else process.stderr.write(`IMGTR: ${error.message}\n`);process.exitCode=error instanceof ImgtrError&&error.status?Math.min(error.status,125):1});
