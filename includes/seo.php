<?php
require_once __DIR__.'/language.php';

function seo_path(): string {
    $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
    if($path==='/index.php')return '/';
    if($path==='/app.php')return '/app';
    $legacy=['/faq.php'=>'/faq','/api-docs.php'=>'/api','/status.php'=>'/status','/privacy.php'=>'/privacy','/terms.php'=>'/terms','/report.php'=>'/report','/login.php'=>'/login','/register.php'=>'/register','/dashboard.php'=>'/dashboard','/logout.php'=>'/logout'];
    if(isset($legacy[$path]))return $legacy[$path];
    return $path;
}

function seo_page_key(string $path): string {
    return match($path){
        '/'=>'home','/app','/app.php'=>'app','/faq','/faq.php'=>'faq','/api','/api-docs.php'=>'api','/status','/status.php'=>'status',
        '/privacy','/privacy.php'=>'privacy','/terms','/terms.php'=>'terms','/report','/report.php'=>'report',default=>'general'
    };
}

function seo_language_url(string $language,string $path): string {
    $url=rtrim(SITE_URL,'/').($path==='/'?'/':'/'.ltrim($path,'/'));
    return $url.(str_contains($url,'?')?'&':'?').'lang='.rawurlencode($language);
}

function seo_head(string $title,string $description='',array $options=[]): void {
    $language=current_language();$path=(string)($options['path']??seo_path());$page=seo_page_key($path);
    $titleKey='seo_title_'.$page;$descriptionKey='seo_description_'.$page;
    if(t($titleKey)!==$titleKey)$title=t($titleKey);
    if(t($descriptionKey)!==$descriptionKey)$description=t($descriptionKey);
    $description=trim(strip_tags($description));
    $branded=str_starts_with(strtoupper(trim($title)),'IMGTR');
    $fullTitle=(string)($options['full_title']??($branded?$title:$title.' — IMGTR'));
    $index=(bool)($options['index']??true);
    $robots=$index?'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1':'noindex, nofollow, noarchive';
    $canonicalPath=(string)($options['canonical_path']??$path);
    $canonical=(string)($options['canonical']??seo_language_url($language,$canonicalPath));
    $keywordKey='seo_keywords_'.$page;$pageKeywords=t($keywordKey);if($pageKeywords===$keywordKey)$pageKeywords='';
    $keywords=trim((string)($options['keywords']??implode(', ',array_filter([t('seo_keywords_general'),$pageKeywords]))));
    $image=(string)($options['image']??base_url('assets/img/og-imgtr.png'));
    $imageAlt=(string)($options['image_alt']??t('seo_og_image_alt'));
    $imageWidth=$options['image_width']??1200;$imageHeight=$options['image_height']??630;$imageType=$options['image_type']??'image/png';
    $ogType=(string)($options['og_type']??'website');
    $locales=['en'=>'en_GB','tr'=>'tr_TR','ru'=>'ru_RU','es'=>'es_ES','de'=>'de_DE'];
    $schema=$options['schema']??[
        '@context'=>'https://schema.org','@type'=>'WebPage','name'=>$fullTitle,'description'=>$description,'url'=>$canonical,
        'inLanguage'=>$language,'isPartOf'=>['@type'=>'WebSite','@id'=>rtrim(SITE_URL,'/').'/#website','name'=>'IMGTR','url'=>rtrim(SITE_URL,'/').'/' ]
    ];
    ?>
  <title><?=e($fullTitle)?></title>
  <meta name="description" content="<?=e($description)?>">
  <?php if($keywords!==''):?><meta name="keywords" content="<?=e($keywords)?>"><?php endif?>
  <meta name="robots" content="<?=e($robots)?>">
  <meta name="googlebot" content="<?=e($robots)?>">
  <meta name="author" content="IMGTR">
  <meta name="application-name" content="IMGTR">
  <meta name="theme-color" content="#10110f">
  <meta name="color-scheme" content="dark light">
  <?php if($index):?><link rel="canonical" href="<?=e($canonical)?>">
  <?php foreach(supported_languages() as $code=>$unused):?><link rel="alternate" hreflang="<?=e($code)?>" href="<?=e(seo_language_url($code,$canonicalPath))?>">
  <?php endforeach?><link rel="alternate" hreflang="x-default" href="<?=e(seo_language_url('en',$canonicalPath))?>"><?php endif?>
  <meta property="og:site_name" content="IMGTR">
  <meta property="og:type" content="<?=e($ogType)?>">
  <meta property="og:locale" content="<?=e($locales[$language]??'en_GB')?>">
  <?php foreach($locales as $code=>$locale):if($code!==$language):?><meta property="og:locale:alternate" content="<?=e($locale)?>"><?php endif;endforeach?>
  <meta property="og:title" content="<?=e($fullTitle)?>">
  <meta property="og:description" content="<?=e($description)?>">
  <meta property="og:url" content="<?=e($canonical)?>">
  <meta property="og:image" content="<?=e($image)?>">
  <meta property="og:image:secure_url" content="<?=e($image)?>">
  <meta property="og:image:alt" content="<?=e($imageAlt)?>">
  <meta property="og:image:width" content="<?=e((string)$imageWidth)?>">
  <meta property="og:image:height" content="<?=e((string)$imageHeight)?>">
  <meta property="og:image:type" content="<?=e((string)$imageType)?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?=e($fullTitle)?>">
  <meta name="twitter:description" content="<?=e($description)?>">
  <meta name="twitter:image" content="<?=e($image)?>">
  <meta name="twitter:image:alt" content="<?=e($imageAlt)?>">
  <script type="application/ld+json"><?=json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<?php }
