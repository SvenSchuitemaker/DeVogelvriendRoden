<?php
declare(strict_types=1);

/*
 * Self-hosted sponsor administration. PHP 8.1+ required.
 * Credentials and writable private files must live OUTSIDE the public document root.
 */
$root = dirname(__DIR__);
$configFile = __DIR__ . '/config.local.php';
$config = is_file($configFile) ? require $configFile : null;
$private = sys_get_temp_dir();
$user = is_array($config) ? (string) ($config['user'] ?? '') : '';
$hash = is_array($config) ? (string) ($config['password_hash'] ?? '') : '';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$local = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) === 1;
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
if (!$secure && !$local) {
    http_response_code(403);
    exit('Admin vereist HTTPS.');
}
if (!is_array($config) || $user === '' || $hash === '' || !is_writable($private)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    exit('Admin is nog niet geïnstalleerd. Volg de stappen in SPONSORBEHEER.md en open daarna <a href="install.php">de installatiepagina</a>.');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('vogelvriend_admin');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/admin', 'secure' => $secure,
    'httponly' => true, 'samesite' => 'Strict'
]);
session_start();
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function fail(int $code, string $message): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
function authed(): bool {
    return !empty($_SESSION['authenticated']) && ($_SESSION['authenticated_at'] ?? 0) > time() - 8 * 3600;
}
function csrf(): void {
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? ''))) {
        fail(403, 'Ongeldige sessie. Vernieuw de pagina.');
    }
}
function dataFile(): string {
    global $root;
    return $root . '/data/sponsors.json';
}
function sponsors(): array {
    $raw = @file_get_contents(dataFile());
    $list = $raw === false ? null : json_decode($raw, true);
    if (!is_array($list) || !array_is_list($list)) {
        fail(500, 'Sponsorbestand kon niet worden gelezen.');
    }
    return $list;
}
function writeSponsors(array $list): void {
    global $private;
    $lock = fopen($private . '/vogelvriend-' . hash('sha256', __DIR__) . '-sponsors.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) fail(500, 'Opslag is niet beschikbaar.');
    $json = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $target = dataFile();
    $tmp = tempnam(dirname($target), '.sponsors-');
    if ($tmp === false) {
        flock($lock, LOCK_UN);
        fail(500, 'Kan tijdelijk bestand niet maken.');
    }
    try {
        if (file_put_contents($tmp, $json) === false || !chmod($tmp, 0644)) throw new RuntimeException('write');
        if (!rename($tmp, $target)) throw new RuntimeException('rename');
    } catch (Throwable $e) {
        @unlink($tmp);
        flock($lock, LOCK_UN);
        fail(500, 'Opslaan mislukt. Controleer de schrijfrechten op data/.');
    }
    flock($lock, LOCK_UN);
    fclose($lock);
}
function validUrl(string $url): bool {
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) && !empty($parts['host']);
}
function validImage(string $image): bool {
    global $root;
    if ($image === '' || str_contains($image, '..') || str_starts_with($image, '/') || preg_match('/[?#\\\x00-\x1f]/', $image)) return false;
    if (!str_starts_with($image, 'sponsoren/')) return false;
    return is_file($root . '/' . $image);
}
function cleanSponsor(mixed $row): array {
    if (!is_array($row)) fail(422, 'Ongeldige sponsor.');
    $name = trim((string) ($row['name'] ?? ''));
    $url = trim((string) ($row['url'] ?? ''));
    $image = trim((string) ($row['image'] ?? ''));
    $tier = (string) ($row['tier'] ?? '');
    $page = (string) ($row['page'] ?? '');
    $pages = ['index', 'bestuur', 'agenda', 'sponsoren', 'ringen', 'lidworden', 'contact', 'tentoonstelling'];
    if ($name === '' || strlen($name) > 240 || !validUrl($url) || strlen($url) > 2048 || !validImage($image) || !in_array($tier, ['gold', 'silver', 'bronze'], true)) {
        fail(422, 'Controleer naam, website, categorie en afbeelding.');
    }
    if ($tier === 'gold') $page = '';
    elseif ($tier === 'silver') $page = 'sponsoren';
    elseif (!in_array($page, $pages, true)) fail(422, 'Kies een geldige pagina.');
    return [
        'name' => $name, 'url' => $url, 'image' => $image,
        'tier' => $tier, 'page' => $page,
        'order' => max(0, min(100000, (int) ($row['order'] ?? 0))),
        'active' => ($row['active'] ?? false) === true
    ];
}
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['api'] === 'login') {
        csrf();
        $fingerprint = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $ratePath = $private . '/vogelvriend-' . hash('sha256', __DIR__) . '-login-' . $fingerprint . '.json';
        $rate = json_decode((string) @file_get_contents($ratePath), true) ?: ['count' => 0, 'until' => 0];
        if (($rate['until'] ?? 0) > time()) fail(429, 'Te veel pogingen. Probeer over 15 minuten opnieuw.');
        $ok = hash_equals($user, (string) ($_POST['username'] ?? '')) && password_verify((string) ($_POST['password'] ?? ''), $hash);
        if (!$ok) {
            $count = (int) ($rate['count'] ?? 0) + 1;
            file_put_contents($ratePath, json_encode(['count' => $count, 'until' => $count >= 5 ? time() + 900 : 0]), LOCK_EX);
            fail(401, 'Onjuiste gebruikersnaam of wachtwoord.');
        }
        @unlink($ratePath);
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['authenticated_at'] = time();
        echo '{"ok":true}';
        exit;
    }
    if (!authed()) fail(401, 'Log opnieuw in.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['api'] === 'logout') {
        csrf();
        $_SESSION = [];
        session_destroy();
        echo '{"ok":true}';
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['api'] === 'sponsors') {
        echo json_encode(['sponsors' => sponsors()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['api'] === 'media') {
        // Show uploaded logos, all legacy sponsor folders, and already linked images.
        // Existing SVGs can be selected, but only safe raster formats can be uploaded.
        $images = [];
        $legacy = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/sponsoren', FilesystemIterator::SKIP_DOTS)
        );
        foreach ($legacy as $file) {
            if (!$file->isFile() || !preg_match('/\.(png|jpg|jpeg|webp|svg)$/i', $file->getFilename())) continue;
            $path = str_replace('\\\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $images[] = $path;
        }
        foreach (sponsors() as $sponsor) {
            if (isset($sponsor['image']) && is_string($sponsor['image']) && validImage($sponsor['image'])) {
                $images[] = $sponsor['image'];
            }
        }
        $images = array_values(array_unique($images));
        rsort($images);
        echo json_encode(['images' => $images], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['api'] === 'save') {
        csrf();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload) || !isset($payload['sponsors']) || !is_array($payload['sponsors']) || count($payload['sponsors']) > 500) fail(422, 'Ongeldige gegevens.');
        $list = array_map('cleanSponsor', $payload['sponsors']);
        writeSponsors($list);
        echo '{"ok":true}';
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['api'] === 'upload') {
        csrf();
        if (empty($_FILES['logo']) || !is_uploaded_file($_FILES['logo']['tmp_name']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK || $_FILES['logo']['size'] > 5 * 1024 * 1024) fail(422, 'Kies een afbeelding van maximaal 5 MB.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['logo']['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext || @getimagesize($_FILES['logo']['tmp_name']) === false) fail(422, 'Alleen JPG, PNG en WebP zijn toegestaan.');
        $directory = $root . '/sponsoren/uploads';
        if (!is_dir($directory) || !is_writable($directory)) fail(500, 'De uploadmap is niet schrijfbaar.');
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $directory . '/' . $filename)) fail(500, 'Upload mislukt.');
        chmod($directory . '/' . $filename, 0644);
        echo json_encode(['image' => 'sponsoren/uploads/' . $filename]);
        exit;
    }
    fail(404, 'Onbekende actie.');
}
$loggedIn = authed();
$token = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sponsorbeheer | De Vogelvriend Roden</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#182a3b;background:#f2f6f9}*{box-sizing:border-box}
body{margin:0}button,input,select{font:inherit}button{cursor:pointer}button:disabled{opacity:.5;cursor:not-allowed}
header{background:#133b57;color:#fff;padding:16px max(20px,calc((100vw - 1200px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}
header strong{font-size:1.2rem}.shell{max-width:1200px;margin:30px auto;padding:0 20px}.panel{background:white;border:1px solid #dce5ec;border-radius:16px;padding:24px;box-shadow:0 6px 22px #1d3a5010}
.login{max-width:440px;margin:9vh auto}h1{font-size:1.6rem;margin:0 0 8px}h2{font-size:1.2rem;margin:0 0 16px}
p{color:#576c7d}label{display:block;font-size:.85rem;font-weight:650;margin-bottom:5px}input,select{width:100%;padding:10px 12px;border:1px solid #b9c9d4;border-radius:8px;background:white;color:#182a3b}
.field{margin-bottom:15px}.btn{border:0;border-radius:9px;background:#177c73;color:white;padding:11px 16px;font-weight:650}.btn.secondary{background:#e4edf2;color:#163c56}.btn.danger{background:#fff0f0;color:#a32424}
.bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin:22px 0}.bar .actions{display:flex;flex-wrap:wrap;gap:8px}
.filters{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}.filters>*{max-width:260px}
.list{display:grid;gap:10px}.item{display:grid;grid-template-columns:74px minmax(0,1fr) auto;gap:14px;align-items:center;border:1px solid #e0e8ed;border-radius:12px;padding:12px}
.item img{width:74px;height:60px;object-fit:contain;background:white}.item small{color:#728493;display:block;margin-top:4px}.item .actions{display:flex;gap:6px}
.notice{padding:12px;border-radius:9px;background:#eaf5f0;color:#176149;margin:14px 0}.error{background:#ffeded;color:#9a2525}
dialog{border:0;border-radius:16px;box-shadow:0 24px 90px #142a4560;padding:24px;width:min(96vw,580px);max-height:92vh;overflow:auto}dialog::backdrop{background:#10283999}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.full{grid-column:1/-1}.preview{max-width:170px;height:95px;object-fit:contain;margin:8px 0}
.media{display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:8px;max-height:250px;overflow:auto}.media button{border:1px solid #d9e3e9;border-radius:9px;background:#fff;padding:7px}.media img{height:70px;width:100%;object-fit:contain}
footer{color:#6a7d8b;text-align:center;margin:24px}.hidden{display:none!important}
@media(max-width:620px){.item{grid-template-columns:56px 1fr}.item img{width:56px}.item .actions{grid-column:1/-1;justify-content:flex-end}.grid{grid-template-columns:1fr}.panel{padding:16px}}
</style>
</head>
<body data-logged-in="<?= $loggedIn ? 'true' : 'false' ?>">
<header><strong>🐦 De Vogelvriend Roden</strong><button id="logout" class="btn secondary <?= $loggedIn ? '' : 'hidden' ?>">Uitloggen</button></header>
<main class="shell">
<section id="loginPanel" class="panel login <?= $loggedIn ? 'hidden' : '' ?>">
<h1>Beheerderslogin</h1><p>Log in om de sponsoren te beheren.</p>
<form id="loginForm"><div class="field"><label for="username">Gebruikersnaam</label><input id="username" name="username" autocomplete="username" required></div>
<div class="field"><label for="password">Wachtwoord</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
<button class="btn" type="submit">Inloggen</button></form>
</section>
<section id="dashboard" class="<?= $loggedIn ? '' : 'hidden' ?>">
<div class="bar"><div><h1>Sponsorbeheer</h1><p>Beheer logo's, links, categorieën en paginaplaatsing.</p></div><div class="actions"><button class="btn secondary" id="uploadButton">Logo uploaden</button><button class="btn" id="addButton">+ Sponsor toevoegen</button></div></div>
<div id="message" role="status" aria-live="polite"></div>
<div class="panel"><div class="filters"><input id="search" placeholder="Zoek sponsor…" aria-label="Zoek sponsor"><select id="filter" aria-label="Filter categorie"><option value="">Alle categorieën</option><option value="gold">Hoofdsponsoren (Gold)</option><option value="silver">Sponsorenpagina (Silver)</option><option value="bronze">Paginasponsoren (Brons)</option></select></div><div id="list" class="list"></div></div>
</section>
</main>
<dialog id="editDialog"><form id="editForm">
<h2 id="editTitle">Sponsor bewerken</h2><div class="grid">
<div class="field full"><label for="name">Bedrijfsnaam</label><input id="name" required maxlength="120"></div>
<div class="field full"><label for="url">Website (https://...)</label><input id="url" type="url" required></div>
<div class="field"><label for="tier">Categorie</label><select id="tier"><option value="gold">Gold — overal</option><option value="silver">Silver — sponsorenpagina</option><option value="bronze">Brons — specifieke pagina</option></select></div>
<div class="field"><label for="page">Pagina</label><select id="page"><option value="index">Home</option><option value="bestuur">Bestuur</option><option value="agenda">Agenda</option><option value="sponsoren">Sponsoren</option><option value="ringen">Ringen</option><option value="lidworden">Lid worden</option><option value="contact">Contact</option><option value="tentoonstelling">Tentoonstelling</option></select></div>
<div class="field"><label for="order">Volgorde</label><input id="order" type="number" min="0" max="100000" value="0"></div>
<div class="field"><label><input id="active" type="checkbox" style="width:auto"> Actief tonen</label></div>
<div class="field full"><label>Logo</label><img id="preview" class="preview" alt="Gekozen logo"><input id="image" readonly required placeholder="Selecteer een logo"><div class="actions" style="margin-top:8px"><button class="btn secondary" type="button" id="chooseLogo">Uit mediabibliotheek</button> <button class="btn secondary" type="button" id="editUpload">Nieuw logo uploaden</button></div></div>
</div><div class="bar"><button class="btn secondary" type="button" id="cancelEdit">Annuleren</button><button class="btn" type="submit">Opslaan</button></div>
</form></dialog>
<dialog id="mediaDialog"><h2>Mediabibliotheek</h2><p>Kies een bestaand sponsorlogo uit de volledige galerij, of upload een nieuw bestand.</p><input id="file" type="file" accept="image/jpeg,image/png,image/webp"><button id="sendFile" class="btn" style="margin:12px 0">Uploaden</button><div id="media" class="media"></div><div class="bar"><button class="btn secondary" id="closeMedia">Sluiten</button></div></dialog>
<footer>Eigen sponsorbeheer · opslag op eigen server</footer>
<script>
const csrf = <?= json_encode($_SESSION['csrf']) ?>;
const $ = id => document.getElementById(id);
const pages = {index:'Home',bestuur:'Bestuur',agenda:'Agenda',sponsoren:'Sponsoren',ringen:'Ringen',lidworden:'Lid worden',contact:'Contact',tentoonstelling:'Tentoonstelling'};
const tiers = {gold:'Gold',silver:'Silver',bronze:'Brons'};
let records=[],editing=-1,chooseForEditor=false;
async function request(action, options={}) {
  const response=await fetch('index.php?api='+action,{credentials:'same-origin',cache:'no-store',...options,headers:{'X-CSRF-Token':csrf,...(options.headers||{})}});
  const result=await response.json().catch(()=>({error:'Serverantwoord niet leesbaar'}));
  if(!response.ok)throw Error(result.error||'Er ging iets mis');
  return result;
}
function message(text,isError=false){const box=$('message');if(box){box.className='notice'+(isError?' error':'');box.textContent=text;}}
function imgSrc(image){return '../'+image.split('/').map(encodeURIComponent).join('/');}
function elem(tag,cls,text){const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;}
async function load(){records=(await request('sponsors')).sponsors;render();}
function render(){
 const q=$('search').value.toLowerCase(),tier=$('filter').value;
 const list=$('list');list.replaceChildren();
 const filtered=records.map((s,i)=>({...s,index:i})).filter(s=>(!tier||s.tier===tier)&&s.name.toLowerCase().includes(q)).sort((a,b)=>a.tier.localeCompare(b.tier)||a.order-b.order);
 if(!filtered.length){list.append(elem('p','','Geen sponsoren gevonden.'));return;}
 for(const s of filtered){
  const item=elem('div','item'),img=elem('img');img.src=imgSrc(s.image);img.alt='';
  const info=elem('div');info.append(elem('strong','',s.name),elem('small','',tiers[s.tier]+' · '+(s.page?pages[s.page]:'Alle pagina’s')+' · volgorde '+s.order+(s.active?'':' · uitgeschakeld')));
  const actions=elem('div','actions');
  const edit=elem('button','btn secondary','Bewerken');edit.onclick=()=>openEdit(s.index);
  const up=elem('button','btn secondary','↑');up.title='Eerder tonen';up.onclick=()=>move(s.index,-1);
  const down=elem('button','btn secondary','↓');down.title='Later tonen';down.onclick=()=>move(s.index,1);
  const remove=elem('button','btn danger','Verwijder');remove.onclick=()=>removeSponsor(s.index);
  actions.append(up,down,edit,remove);item.append(img,info,actions);list.append(item);
 }
}
async function save(){await request('save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sponsors:records})});message('Wijzigingen zijn opgeslagen.');render();}
function openEdit(index){
 editing=index;const s=index>=0?records[index]:{name:'',url:'',image:'',tier:'bronze',page:'sponsoren',order:0,active:true};
 $('editTitle').textContent=index<0?'Sponsor toevoegen':'Sponsor bewerken';
 for(const key of ['name','url','image','tier','page','order'])$(key).value=s[key]??'';
 $('active').checked=s.active;$('preview').src=s.image?imgSrc(s.image):'';
 updatePage();$('editDialog').showModal();
}
function updatePage(){const t=$('tier').value;$('page').disabled=t!=='bronze';if(t==='silver')$('page').value='sponsoren';}
$('tier').onchange=updatePage;
$('editForm').onsubmit=async e=>{
 e.preventDefault();const row={name:$('name').value.trim(),url:$('url').value.trim(),image:$('image').value,tier:$('tier').value,page:$('tier').value==='bronze'?$('page').value:($('tier').value==='silver'?'sponsoren':''),order:Number($('order').value),active:$('active').checked};
 if(!row.image)return alert('Selecteer eerst een logo.');
 const previous=records.slice();if(editing<0)records.push(row);else records[editing]=row;
 try{await save();$('editDialog').close();}catch(err){records=previous;alert(err.message);}
};
$('cancelEdit').onclick=()=>$('editDialog').close();
async function move(index,delta){
 const s=records[index],siblings=records.map((x,i)=>({...x,index:i})).filter(x=>x.tier===s.tier&&x.page===s.page).sort((a,b)=>a.order-b.order);
 const i=siblings.findIndex(x=>x.index===index),other=siblings[i+delta];if(!other)return;
 const snapshot=JSON.stringify(records);
 // Normalize the entire group to unique positions so moving works even when multiple sponsors share an order.
 const reordered=siblings.map(x=>x.index);const target=reordered.indexOf(index);
 reordered.splice(target,1);reordered.splice(target+delta,0,index);
 reordered.forEach((recordIndex,position)=>{records[recordIndex].order=position+1;});
 try{await save();}catch(err){records=JSON.parse(snapshot);message(err.message,true);}
}
async function removeSponsor(index){
 if(!confirm('Sponsor "'+records[index].name+'" verwijderen? Het logo blijft in de mediabibliotheek.'))return;
 const old=records.slice();records.splice(index,1);try{await save();}catch(err){records=old;message(err.message,true);}
}
function openMedia(forEditor){chooseForEditor=forEditor;$('mediaDialog').showModal();loadMedia();}
async function loadMedia(){
 try{
  const images=(await request('media')).images,media=$('media');media.replaceChildren();
  if(!images.length)media.append(elem('p','','Nog geen sponsorlogo's gevonden.'));
  for(const path of images){const b=elem('button');b.type='button';const im=elem('img');im.src=imgSrc(path);im.alt=path.split('/').pop();b.append(im);b.onclick=()=>{if(chooseForEditor){$('image').value=path;$('preview').src=imgSrc(path);$('mediaDialog').close();}else{message('Logo beschikbaar in de mediabibliotheek: '+path);$('mediaDialog').close();}};media.append(b);}
 }catch(err){alert(err.message);}
}
$('chooseLogo').onclick=()=>openMedia(true);$('editUpload').onclick=()=>openMedia(true);
$('uploadButton').onclick=()=>openMedia(false);$('closeMedia').onclick=()=>$('mediaDialog').close();
$('sendFile').onclick=async()=>{
 const file=$('file').files[0];if(!file)return alert('Kies een bestand.');
 const body=new FormData();body.append('logo',file);
 try{const result=await request('upload',{method:'POST',body});$('file').value='';await loadMedia();if(chooseForEditor){$('image').value=result.image;$('preview').src=imgSrc(result.image);$('mediaDialog').close();}else{message('Logo succesvol geüpload.');$('mediaDialog').close();}}
 catch(err){alert(err.message);}
};
$('addButton').onclick=()=>openEdit(-1);$('search').oninput=render;$('filter').onchange=render;
$('loginForm').onsubmit=async e=>{
 e.preventDefault();const data=new FormData(e.target);data.append('csrf',csrf);
 try{await request('login',{method:'POST',body:data});location.reload();}catch(err){alert(err.message);}
};
$('logout').onclick=async()=>{try{await request('logout',{method:'POST'});location.reload();}catch(err){alert(err.message);}};
if(document.body.dataset.loggedIn==='true')load().catch(err=>message(err.message,true));
</script>
</body></html>
