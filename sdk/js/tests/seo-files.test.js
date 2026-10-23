import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root=fileURLToPath(new URL('../../../',import.meta.url));
const source=path=>readFile(new URL(path,`file:///${root.replaceAll('\\','/')}/`),'utf8');

test('SEO translations have identical unique keys in every supported language',async()=>{
  const php=await source('includes/translations-seo.php'),languages=['en','tr','ru','es','de'],keys={};
  for(const language of languages){
    const block=php.match(new RegExp(`'${language}'=>\\[(.*?)\\n\\]`,'s'));
    assert.ok(block,`missing ${language} SEO translation block`);
    keys[language]=[...block[1].matchAll(/'([^']+)'=>/g)].map(match=>match[1]);
    assert.equal(new Set(keys[language]).size,keys[language].length,`${language} contains duplicate SEO keys`);
  }
  for(const language of languages)assert.deepEqual([...keys[language]].sort(),[...keys.en].sort(),`${language} SEO key set differs from English`);
});

test('shared SEO head contains canonical, hreflang, social and structured metadata',async()=>{
  const php=await source('includes/seo.php');
  for(const marker of ['rel="canonical"','hreflang="x-default"','property="og:title"','name="twitter:card"','application/ld+json','name="robots"'])assert.ok(php.includes(marker),`missing ${marker}`);
  assert.match(php,/foreach\(supported_languages\(\)/);
});

test('robots and sitemap expose public pages while protecting private surfaces',async()=>{
  const [robots,htaccess,sitemap]=await Promise.all([source('robots.txt'),source('.htaccess'),source('sitemap.php')]);
  assert.match(robots,/Sitemap: https:\/\/imgtr\.xyz\/sitemap\.xml/);
  for(const path of ['/admin/','/ajax/','/api/','/account/','/manage/'])assert.ok(robots.includes(`Disallow: ${path}`));
  assert.match(htaccess,/RewriteRule \^sitemap\\\.xml\$ sitemap\.php/);
  for(const language of ['en','tr','ru','es','de'])assert.ok(sitemap.includes("array_keys(supported_languages())")||sitemap.includes(language));
  for(const path of ["'path'=>'/'","'path'=>'/app'","'path'=>'/faq'","'path'=>'/api'","'path'=>'/status'"])assert.ok(sitemap.includes(path));
});

test('browser, canonical and API documentation URLs are extensionless',async()=>{
  const htaccess=await source('.htaccess'),layout=await source('includes/layout.php'),home=await source('index.php'),sitemap=await source('sitemap.php'),docs=await source('api-docs.php'),openapi=await source('api/openapi.json'),sdk=await source('sdk/js/src/index.js');
  assert.match(htaccess,/RewriteRule \^\(faq\|status\|privacy\|terms\|report\|login\|register\|logout\|dashboard\)/);
  assert.match(htaccess,/RewriteRule \^api\/v1\/\(user\|images\|image\|deletion\|relays\|uploads\|uploads-verify\)/);
  assert.doesNotMatch(layout,/href="[^"]+\.php/);
  assert.doesNotMatch(home,/href="[^"]+\.php/);
  assert.doesNotMatch(sitemap,/'path'=>'[^']+\.php'/);
  assert.doesNotMatch(docs,/<code>\/[^<]+\.php/);
  assert.doesNotMatch(openapi,/"\/[^"]+\.php"\s*:/);
  assert.doesNotMatch(sdk,/\/api\/v1\/[a-z-]+\.php/);
});

test('default Open Graph card is a real 1200 by 630 PNG',async()=>{
  const png=await readFile(new URL('assets/img/og-imgtr.png',`file:///${root.replaceAll('\\','/')}/`));
  assert.equal(png.subarray(1,4).toString(),'PNG');
  assert.equal(png.readUInt32BE(16),1200);
  assert.equal(png.readUInt32BE(20),630);
});

test('private HTML surfaces explicitly opt out of indexing',async()=>{
  for(const path of ['login.php','register.php','logout.php','manage.php','collection.php'])assert.match(await source(path),/'index'=>false/,`${path} is missing noindex`);
  assert.match(await source('dashboard.php'),/X-Robots-Tag: noindex/);
  assert.match(await source('includes/admin-layout.php'),/X-Robots-Tag: noindex/);
});
