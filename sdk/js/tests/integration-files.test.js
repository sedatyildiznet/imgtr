import assert from 'node:assert/strict';
import { access,readFile } from 'node:fs/promises';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root=fileURLToPath(new URL('../../../',import.meta.url));
const source=path=>readFile(new URL(path,`file:///${root.replaceAll('\\','/')}/`),'utf8');

test('ShareX translations have the same unique keys in all supported languages',async()=>{
  const php=await source('includes/translations-sharex.php'),languages=['en','tr','ru','es','de'],keys={};
  for(const language of languages){
    const block=php.match(new RegExp(`'${language}'=>\\[(.*?)\\n\\]`,'s'));
    assert.ok(block,`missing ${language} translation block`);
    keys[language]=[...block[1].matchAll(/'([^']+)'=>/g)].map(match=>match[1]);
    assert.equal(new Set(keys[language]).size,keys[language].length,`${language} contains duplicate keys`);
  }
  for(const language of languages)assert.deepEqual([...keys[language]].sort(),[...keys.en].sort(),`${language} key set differs from English`);
});

test('public App and private ShareX configuration routes remain registered',async()=>{
  const htaccess=await source('.htaccess');
  assert.match(htaccess,/RewriteRule \^app\/\?\$ app\.php \[L,QSA\]/);
  assert.match(htaccess,/RewriteRule \^account\/integrations\/sharex\/download\/\?\$ sharex-config\.php \[L,QSA\]/);
});

test('public image viewer uses the current shared header and footer layout',async()=>{
  const viewer=await source('view.php'),layout=await source('includes/layout.php');
  assert.match(viewer,/require_once __DIR__\.'\/includes\/layout\.php'/);
  assert.match(viewer,/page_start\(\$image\['original_filename'\]/);
  assert.match(viewer,/page_end\(\['\/assets\/js\/view\.js/);
  assert.doesNotMatch(viewer,/<header class="site-header">/);
  assert.doesNotMatch(viewer,/<!doctype html>/);
  assert.match(layout,/<a href="\/app"><\?=e\(t\('nav_app'\)\)\?>/);
  assert.match(layout,/<footer class="site-footer">/);
  assert.match(layout,/function page_end\(array \$scripts=\[\]\)/);
});

test('personalized ShareX config targets only the loopback bridge',async()=>{
  const php=await source('sharex-config.php');
  assert.match(php,/'RequestURL'=>'http:\/\/127\.0\.0\.1:43191\/upload'/);
  assert.match(php,/'FileFormName'=>'file'/);
  assert.match(php,/'Version'=>'17\.0\.0'/);
  assert.match(php,/'URL'=>'\{json:data\.url\}'/);
  assert.match(php,/'ErrorMessage'=>'\{json:error\.message\}'/);
  assert.doesNotMatch(php,/\$json:/);
  assert.doesNotMatch(php,/'RequestURL'=>'https:\/\/imgtr\.xyz/);
});

test('public App offers the automatic Windows setup without embedding credentials',async()=>{
  const page=await source('app.php'),setup=await source('downloads/IMGTR-ShareX-Setup.bat'),dashboard=await source('dashboard.php'),admin=await source('includes/admin-layout.php'),refinement=await source('assets/css/dashboard-refine.css');
  assert.match(page,/\/downloads\/IMGTR-ShareX-Setup\.bat/);
  assert.match(setup,/OpenJS\.NodeJS\.LTS/);
  assert.match(setup,/imgtr\.cmd sharex install/);
  assert.match(setup,/imgtr-sdk-1\.2\.0\.tgz/);
  assert.doesNotMatch(setup,/imgtr_[a-f0-9]{48}/i);
  assert.match(dashboard,/fa-solid fa-display/);
  assert.match(dashboard,/data-tab="integration"[^>]*>.*?t\('nav_app'\)/s);
  assert.match(dashboard,/integration-workflow/);
  assert.match(dashboard,/dashboard-refine\.css/);
  assert.match(admin,/fontawesome-free@7\.2\.0/);
  assert.match(admin,/fa-chart-line/);
  assert.match(admin,/fa-clock-rotate-left/);
  assert.match(refinement,/\.dashboard-admin \.admin-tab\{[^}]*text-decoration:none/);
});

test('admin control migration provides bans, audit records and traffic analytics',async t=>{
  try{await access(new URL('../../../updates/2026-08-31-admin-control.sql',import.meta.url))}catch{t.skip('optional deployment migration is not present in this checkout');return}
  const sql=await source('updates/2026-08-31-admin-control.sql');
  assert.match(sql,/CREATE TABLE IF NOT EXISTS user_bans/);
  assert.match(sql,/CREATE TABLE IF NOT EXISTS admin_audit_log/);
  assert.match(sql,/CREATE TABLE IF NOT EXISTS traffic_hourly/);
  assert.match(sql,/PRIMARY KEY \(bucket_hour,visitor_hash\)/);
});

test('web sessions and API keys enforce active account restrictions',async()=>{
  const auth=await source('includes/auth.php'),login=await source('login.php'),api=await source('includes/api.php');
  assert.match(auth,/active_user_ban\(\(int\)\$candidate\['id'\]\)/);
  assert.match(auth,/unset\(\$_SESSION\['user_id'\]\)/);
  assert.match(login,/record_auth_activity\('login_blocked'/);
  assert.match(login,/account_banned_message/);
  assert.match(api,/api_error\('account_restricted'/);
});

test('admin image inventory exposes exact Nostr records and safe deletion controls',async()=>{
  const page=await source('admin/images.php');
  assert.match(page,/manifest_event_id/);
  assert.match(page,/chunk_event_ids/);
  assert.match(page,/nostr:/);
  assert.match(page,/delete_image_everywhere\(\$image,12\)/);
  assert.match(page,/dispatch_image_deletion\(\$image,12\)/);
  assert.match(page,/admin_audit\(\(int\)\$admin\['id'\],'image\.delete'/);
});

test('admin member restrictions support reasons, expiry and revocation',async()=>{
  const page=await source('admin/users.php'),control=await source('includes/control.php');
  assert.match(page,/name="reason"/);
  assert.match(page,/value="permanent"/);
  assert.match(page,/value="custom"/);
  assert.match(page,/create_user_ban\(/);
  assert.match(page,/revoke_user_ban\(/);
  assert.match(control,/expires_at>UTC_TIMESTAMP\(\)/);
});

test('admin audit log is paginated and dashboard has period traffic charts',async()=>{
  const activity=await source('admin/activity.php'),dashboard=await source('admin/index.php'),tracking=await source('includes/control.php'),image=await source('image.php');
  assert.match(activity,/LIMIT '\.\$perPage\.' OFFSET '\.\$offset/);
  assert.match(activity,/admin_audit_log/);
  assert.match(dashboard,/\['day','week','month'\]/);
  assert.match(dashboard,/COUNT\(DISTINCT visitor_hash\)/);
  assert.match(dashboard,/class="traffic-chart"/);
  assert.match(tracking,/traffic_hourly/);
  assert.match(tracking,/hash_hmac\('sha256'/);
  assert.match(image,/record_public_hit\('/);
});

test('account restriction translations have matching keys in every language',async()=>{
  const php=await source('includes/translations-control.php'),languages=['en','tr','ru','es','de'],keys={};
  for(const language of languages){
    const block=php.match(new RegExp(`'${language}'=>\\[(.*?)\\]`,'s'));
    assert.ok(block,`missing ${language} control translation block`);
    keys[language]=[...block[1].matchAll(/'([^']+)'=>/g)].map(match=>match[1]);
    assert.equal(new Set(keys[language]).size,keys[language].length,`${language} contains duplicate control keys`);
  }
  for(const language of languages)assert.deepEqual([...keys[language]].sort(),[...keys.en].sort(),`${language} control key set differs from English`);
});

test('refined album workspace is themed, editable and translated',async()=>{
  const dashboard=await source('dashboard.php'),css=await source('assets/css/dashboard-refine.css'),js=await source('assets/js/dashboard.js'),endpoint=await source('ajax/update-album.php');
  assert.match(dashboard,/class="album-create-form"/);
  assert.match(dashboard,/class="themed-select"/);
  assert.match(dashboard,/class="album-cover-stack"/);
  assert.match(dashboard,/class="album-details-form"/);
  assert.match(css,/\.dashboard-body\.dark select\{color-scheme:dark\}/);
  assert.match(css,/\.dashboard select option\{[^}]*background-color:var\(--surface\)/);
  assert.match(js,/post\('\/ajax\/update-album\.php'/);
  assert.match(endpoint,/UPDATE albums SET title=\?,visibility=\?/);
});

test('album refinement translations have identical keys in every language',async()=>{
  const php=await source('includes/translations-album.php'),languages=['en','tr','ru','es','de'],keys={};
  for(const language of languages){
    const block=php.match(new RegExp(`'${language}'=>\\[(.*?)\\](?:,|\\s*\\n\\];)`,'s'));
    assert.ok(block,`missing ${language} album translation block`);
    keys[language]=[...block[1].matchAll(/'([^']+)'=>/g)].map(match=>match[1]);
    assert.equal(new Set(keys[language]).size,keys[language].length,`${language} contains duplicate album keys`);
  }
  for(const language of languages)assert.deepEqual([...keys[language]].sort(),[...keys.en].sort(),`${language} album key set differs from English`);
});

test('homepage, dashboard and admin render the same shared footer',async()=>{
  const layout=await source('includes/layout.php'),home=await source('index.php'),dashboard=await source('dashboard.php'),admin=await source('includes/admin-layout.php');
  assert.match(layout,/function site_footer\(/);
  assert.match(layout,/function page_end\([^)]*\).*?site_footer\(\$user\)/s);
  assert.match(home,/site_footer\(\$user\)/);
  assert.match(dashboard,/site_footer\(\$user\)/);
  assert.match(admin,/site_footer\(current_user\(\)\)/);
  assert.doesNotMatch(home,/<p class="index-label">IMGTR<\/p>/);
  assert.equal((home.match(/<footer class="site-footer">/g)||[]).length,0,'homepage must not duplicate shared footer markup');
});

test('mobile App and API code grids cannot expand the page viewport',async()=>{
  const css=await source('assets/css/premium.css');
  assert.match(css,/\.developer-section>div,\.app-page section>div,\.app-architecture>div,\.app-security>div\{min-width:0\}/);
  assert.match(css,/\.developer-section pre,\.app-architecture pre\{width:100%;max-width:100%;min-width:0\}/);
  assert.match(css,/@media\(max-width:680px\)\{\.developer-section\{grid-template-columns:minmax\(0,1fr\)\}/);
  assert.match(css,/@media\(max-width:420px\)\{\.site-footer\{grid-template-columns:1fr\}/);
});

test('current legal and help content is complete in every supported language',async()=>{
  const php=await source('includes/translations-legal-current.php'),languages=['en','tr','ru','es','de'],keys={};
  for(let index=0;index<languages.length;index++){
    const language=languages[index],next=languages[index+1];
    const start=php.indexOf(`'${language}'=>[`),end=next?php.indexOf(`'${next}'=>[`,start):php.lastIndexOf('];');
    assert.ok(start>=0&&end>start,`missing ${language} legal translation block`);
    const block=php.slice(start,end);
    keys[language]=[...block.matchAll(/'([a-z0-9_]+)'\s*=>/g)].map(match=>match[1]).filter(key=>!languages.includes(key));
    assert.equal(new Set(keys[language]).size,keys[language].length,`${language} contains duplicate legal keys`);
    assert.equal(keys[language].filter(key=>/^faq_q\d+$/.test(key)).length,15,`${language} FAQ question count differs`);
    assert.equal(keys[language].filter(key=>/^terms_h\d+$/.test(key)).length,12,`${language} terms section count differs`);
    assert.equal(keys[language].filter(key=>/^privacy_h\d+$/.test(key)).length,8,`${language} privacy section count differs`);
    assert.equal(keys[language].filter(key=>/^report_h\d+$/.test(key)).length,4,`${language} report section count differs`);
  }
  for(const language of languages)assert.deepEqual([...keys[language]].sort(),[...keys.en].sort(),`${language} legal key set differs from English`);
  assert.match(php,/ShareX/);
  assert.match(php,/NIP-09/);
  assert.match(php,/API, SDK/);
  assert.match(php,/Nostr/);
});

test('legal pages use the expanded translated content without changing their layout',async()=>{
  const language=await source('includes/language.php'),faq=await source('faq.php'),terms=await source('terms.php'),privacy=await source('privacy.php'),report=await source('report.php');
  assert.match(language,/translations-legal-current\.php/);
  assert.match(language,/\$legalUi\[\$code\]\?\?\[\]\);\}return/);
  assert.match(faq,/\$i<=15/);
  assert.match(terms,/\$i<=12/);
  assert.match(privacy,/\$i<=8/);
  assert.match(report,/\$i<=4/);
  assert.match(faq,/class="faq-list"/);
  assert.match(terms,/class="prose-card legal-prose"/);
  assert.match(privacy,/class="prose-card"/);
  assert.match(report,/class="prose-card"/);
});

test('help and policy reading tools remain restrained, responsive and translated',async()=>{
  const faq=await source('faq.php'),terms=await source('terms.php'),privacy=await source('privacy.php'),report=await source('report.php'),css=await source('assets/css/premium.css'),js=await source('assets/js/faq.js'),translations=await source('includes/translations-legal-ui.php');
  assert.match(faq,/id="faq-search"/);
  assert.match(faq,/assets\/js\/faq\.js/);
  assert.match(js,/toLocaleLowerCase/);
  assert.match(js,/item\.hidden=!match/);
  for(const page of [terms,privacy,report]){
    assert.match(page,/class="legal-layout"/);
    assert.match(page,/class="legal-toc"/);
  }
  assert.match(css,/\.legal-layout\{display:grid/);
  assert.match(css,/@media\(max-width:760px\)\{\.legal-page/);
  const languages=['en','tr','ru','es','de'];
  for(const language of languages){
    const block=translations.match(new RegExp(`'${language}'=>\\[(.*?)\\]`,'s'));
    assert.ok(block,`missing ${language} legal UI translations`);
    assert.equal((block[1].match(/=>/g)||[]).length,4,`${language} legal UI key count differs`);
  }
});
