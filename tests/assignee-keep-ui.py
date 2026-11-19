"""1.10.30 · Guardar la ficha cambiando otro dato no toca al responsable, aunque no esté en la lista del equipo (antes lo quitaba)."""
import os
from playwright.sync_api import sync_playwright
B=os.getenv('TEST_BASE_URL','http://127.0.0.1:8080')
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=os.getenv('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox']);pg=b.new_page()
    pg.goto(B+'/assets/contact-editor.js')
    r=pg.evaluate("""async()=>{const m=await import('/assets/contact-editor.js');
      const team=[{id:'u-ana',name:'Ana'},{id:'u-carla',name:'Carla'}];
      const out={};
      for(const [k,who] of [['fuera','u-viejo'],['dentro','u-carla'],['nadie','']]){
        const c={firstName:'X',assignedTo:who||undefined,customFields:[]};
        const d=document.createElement('form');d.innerHTML=m.contactForm(c,[],team);document.body.append(d);
        d.querySelector('[name=firstName]').value='Y';
        out[k]=m.readContactForm(d,c);d.remove();}
      return out}""")
    print(r)
    assert 'assignedTo' not in r['fuera'] and 'assignedTo' not in r['dentro'] and 'assignedTo' not in r['nadie']
    print('PASS cambiar otro dato de la ficha no toca al responsable, aunque no esté en la lista')
    b.close()
