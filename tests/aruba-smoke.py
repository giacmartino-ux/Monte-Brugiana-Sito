"""Test su copia isolata, non sul sito pubblico."""
import base64, json, os, pathlib, re, shutil, subprocess, tempfile, time
from urllib.request import Request, urlopen, build_opener, HTTPRedirectHandler
from urllib.error import HTTPError
from urllib.parse import urlencode
ROOT=pathlib.Path(__file__).resolve().parent.parent
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self,*a,**k): return None
op=build_opener(NoRedirect)
with tempfile.TemporaryDirectory() as tmp:
    site=pathlib.Path(tmp)/"site"
    shutil.copytree(ROOT,site,ignore=shutil.ignore_patterns(".git",".github"))
    password="test-only-local-"+os.urandom(8).hex()
    hashed=subprocess.check_output(["php","-r",'echo password_hash($argv[1], PASSWORD_DEFAULT);',password],text=True)
    (site/"privato"/"password.hash").write_text(hashed)
    server=subprocess.Popen(["php","-S","127.0.0.1:18741","-t",str(site),str(site/"tests"/"router.php")],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    cookies={}
    def req(path, data=None, csrf=None, expected=200):
        h={}
        if cookies: h["Cookie"]="; ".join(k+"="+v for k,v in cookies.items())
        if csrf: h["X-Editor-CSRF"]=csrf
        if isinstance(data,dict): data=json.dumps(data).encode(); h["Content-Type"]="application/json"
        elif data is not None: h["Content-Type"]="application/x-www-form-urlencoded"
        try: r=op.open(Request("http://127.0.0.1:18741"+path,data=data,headers=h),timeout=10)
        except HTTPError as e: r=e
        for c in r.headers.get_all("Set-Cookie",[]):
            k,v=c.split(";",1)[0].split("=",1);cookies[k]=v
        body=r.read().decode("utf-8",errors="replace")
        assert r.code==expected,(path,expected,r.code,body[:400])
        return body
    try:
        for i in range(30):
            try: req("/");break
            except OSError: time.sleep(.2)
        req("/api-editor.php",expected=401)
        login=req("/editor")
        csrf=re.search('name="csrf" value="([^"]+)"',login).group(1)
        req("/editor",urlencode({"csrf":csrf,"password":password}).encode(),expected=302)
        html=req("/editor")
        csrf=re.search('name="editor-csrf" content="([^"]+)"',html).group(1)
        state=json.loads(req("/api-editor.php"))
        assert len(state["pages"])>=5 and len(state["archive"])>=13
        req("/api-editor.php",{"head":state["head"]},expected=403)
        changed=dict(state["pages"])
        changed["index.html"]=changed["index.html"].replace("Sei sempre quello che ascolta.","Test modifica pagina.")
        changed["test-pagina.html"]=changed["index.html"].replace("Test modifica pagina.","Test nuova pagina.").replace("</main>",'<img src="/assets/editor-test.jpg" alt="Test foto"></main>')
        picture=base64.b64encode((site/"assets"/"hero-amici.jpg").read_bytes()).decode()
        payload={"head":state["head"],"pages":changed,"assets":{"/assets/editor-test.jpg":"data:image/jpeg;base64,"+picture}}
        result=json.loads(req("/api-editor.php",payload,csrf))
        assert result["ok"] and "Test modifica pagina." in req("/") and "Test nuova pagina." in req("/test-pagina.html")
        assert (site/"assets"/"editor-test.jpg").is_file()
        req("/api-editor.php",payload,csrf,409)
        bad=dict(changed);bad["../bad.html"]=bad["index.html"]
        req("/api-editor.php",{"head":result["head"],"pages":bad},csrf,400)
        bad=dict(changed);bad["index.html"]=bad["index.html"].replace("</main>",'<script>alert(1)</script></main>')
        req("/api-editor.php",{"head":result["head"],"pages":bad},csrf,400)
        bad=dict(changed);bad["index.html"]=bad["index.html"].replace("</main>",'<a href="/manca.html">Errore</a></main>')
        req("/api-editor.php",{"head":result["head"],"pages":bad},csrf,400)
        bad=dict(changed);bad["index.html"]=bad["index.html"].replace("</main>",'<img src="/assets/manca.jpg"></main>')
        req("/api-editor.php",{"head":result["head"],"pages":bad},csrf,400)
        restored=json.loads(req("/api-editor.php",{"action":"restore","head":result["head"],"backup":result["backup"]},csrf))
        assert restored["head"]==state["head"] and "Test modifica pagina." not in req("/")
        req("/test-pagina.html",expected=404)
        req("/editor",urlencode({"csrf":csrf,"logout":"1"}).encode(),expected=302)
        req("/api-editor.php",expected=401)
        print("PASS: login, logout, CSRF, publish, new page, image, revision conflict, invalid paths/scripts/links/images, backup and restore.")
    finally: server.terminate();server.wait(timeout=10)
