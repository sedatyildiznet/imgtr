<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/seo.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
$routes=[
    ['path'=>'/','changefreq'=>'daily','priority'=>'1.0','file'=>'index.php'],
    ['path'=>'/app','changefreq'=>'monthly','priority'=>'0.8','file'=>'app.php'],
    ['path'=>'/faq','changefreq'=>'monthly','priority'=>'0.7','file'=>'faq.php'],
    ['path'=>'/api','changefreq'=>'weekly','priority'=>'0.8','file'=>'api-docs.php'],
    ['path'=>'/status','changefreq'=>'hourly','priority'=>'0.6','file'=>'status.php'],
    ['path'=>'/privacy','changefreq'=>'yearly','priority'=>'0.3','file'=>'privacy.php'],
    ['path'=>'/terms','changefreq'=>'yearly','priority'=>'0.3','file'=>'terms.php'],
    ['path'=>'/report','changefreq'=>'monthly','priority'=>'0.4','file'=>'report.php'],
];
$languages=array_keys(supported_languages());
$xml=fn(string $value)=>htmlspecialchars($value,ENT_QUOTES|ENT_XML1,'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php foreach($routes as $route):$lastmod=gmdate('Y-m-d',filemtime(__DIR__.'/'.$route['file'])?:time());foreach($languages as $language):?>
  <url>
    <loc><?=$xml(seo_language_url($language,$route['path']))?></loc>
    <lastmod><?=$lastmod?></lastmod>
    <changefreq><?=$route['changefreq']?></changefreq>
    <priority><?=$route['priority']?></priority>
<?php foreach($languages as $alternate):?>    <xhtml:link rel="alternate" hreflang="<?=$alternate?>" href="<?=$xml(seo_language_url($alternate,$route['path']))?>" />
<?php endforeach?>    <xhtml:link rel="alternate" hreflang="x-default" href="<?=$xml(seo_language_url('en',$route['path']))?>" />
  </url>
<?php endforeach;endforeach?>
</urlset>
