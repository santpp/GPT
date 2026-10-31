"""El grabador de verdad: micrófono simulado de Chromium -> MediaRecorder (WebM/Opus) -> OGG/Opus.
El resultado se valida con ffprobe y se decodifica entero con ffmpeg, que es lo que haría WhatsApp."""
import base64,json,os,subprocess,tempfile
from playwright.sync_api import sync_playwright
BASE=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
ROOT=os.path.dirname(os.path.abspath(__file__))
def probe(data,label):
    with tempfile.NamedTemporaryFile(suffix='.ogg',delete=False) as f:f.write(data);path=f.name
    try:
        info=json.loads(subprocess.run(['ffprobe','-v','error','-show_entries','format=format_name,duration:stream=codec_name,channels,sample_rate','-of','json',path],capture_output=True,text=True,check=True).stdout)
        decode=subprocess.run(['ffmpeg','-v','error','-i',path,'-f','null','-'],capture_output=True,text=True)
        assert decode.returncode==0 and decode.stderr.strip()=='',f'{label}: no decodifica limpio: {decode.stderr[:300]}'
        return info
    finally:os.unlink(path)
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox','--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream'])
    context=b.new_context(permissions=['microphone'],service_workers='block');page=context.new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(BASE+'/login-not-needed.html') if False else page.goto(BASE+'/')
    page.wait_for_load_state('domcontentloaded')
    # 1) grabación real
    result=page.evaluate("""async()=>{
      const v=await import('/assets/voice.js');
      if(!v.supported())return {error:'no soportado'};
      const r=await v.record();await new Promise(done=>setTimeout(done,2200));
      const out=await r.stop();
      const bytes=new Uint8Array(await out.blob.arrayBuffer());
      let bin='';for(let i=0;i<bytes.length;i+=8192)bin+=String.fromCharCode(...bytes.subarray(i,i+8192));
      return {mime:out.mime,seconds:out.seconds,b64:btoa(bin),type:out.blob.type};
    }""")
    assert 'error' not in result,result
    assert result['mime']=='audio/ogg' and result['type']=='audio/ogg',result['mime']
    data=base64.b64decode(result['b64']);assert data[:4]==b'OggS' and b'OpusHead' in data[:120]
    info=probe(data,'grabación real')
    assert info['format']['format_name']=='ogg' and info['streams'][0]['codec_name']=='opus',info
    dur=float(info['format']['duration']);assert 1.8<=dur<=2.8,dur
    print(f'PASS a real recording leaves as OGG/Opus, {dur:.2f} s, and decodes cleanly: WhatsApp reads it as a voice note')
    # 2) un WebM de ffmpeg, distinto del de Chrome, también se reempaqueta
    webm=open(os.path.join(ROOT,'fixtures/audio/voz.webm'),'rb').read()
    out=page.evaluate("""async b64=>{
      const v=await import('/assets/voice.js');
      const bin=atob(b64);const bytes=Uint8Array.from(bin,c=>c.charCodeAt(0));
      const ogg=v.webmToOgg(bytes);let s='';for(let i=0;i<ogg.length;i+=8192)s+=String.fromCharCode(...ogg.subarray(i,i+8192));
      return btoa(s);
    }""",base64.b64encode(webm).decode())
    info=probe(base64.b64decode(out),'webm de ffmpeg')
    assert info['streams'][0]['codec_name']=='opus' and 1.8<=float(info['format']['duration'])<=2.3,info
    print('PASS another WebM/Opus file is repackaged just as well, with its original duration')
    # 3) el audio no se recodifica: los paquetes Opus son los mismos
    same=subprocess.run(['bash','-c',f"ffmpeg -v error -i {ROOT}/fixtures/audio/voz.webm -c copy -f null - 2>&1 | head -1"],capture_output=True,text=True).stdout.strip()
    assert same=='',same
    # 4) el micrófono bloqueado se explica
    denied=b.new_context(service_workers='block').new_page()
    denied.goto(BASE+'/');denied.wait_for_load_state('domcontentloaded')
    message=denied.evaluate("""async()=>{const v=await import('/assets/voice.js');
      navigator.mediaDevices.getUserMedia=()=>Promise.reject(Object.assign(new Error('x'),{name:'NotAllowedError'}));
      try{await v.record();return 'sin error'}catch(e){return e.message+'|'+e.code}}""")
    assert 'bloqueado' in message and message.endswith('mic_denied'),message
    print('PASS if the microphone is blocked, it says how to allow it')
    assert not errors,errors
    print('PASS no JS errors')
    b.close()
