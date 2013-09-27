<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

function debug($label, $value) {
    echo("<p>$label<br /><pre>".htmlentities(print_r($value, 1))."</pre></p>");
}
// phpinfo();
// debug('server', $_SERVER);

define('MANUAL_BASE_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');
// debug('MANUAL_BASE_PATH', MANUAL_BASE_PATH);

define('MANUAL_CONFIG_FILE', MANUAL_BASE_PATH.'config.json');
define('MANUAL_HTTP_URL', sprintf('http://%s%s', $_SERVER['SERVER_NAME'], pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME)));

define('MANUAL_LANG_DEFAULT', 'fr');

if (is_file(MANUAL_CONFIG_FILE)) {
    $config = json_decode(file_get_contents(MANUAL_CONFIG_FILE), true);
    if (empty($config)) {
        debug('error:', 'invalid config file');
    }
    // debug('config', $config);
} elseif(is_file('install.php')) {
    header('Location: '.pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME).'/'.'install.php');
}

// $config['stylesheet_css'] = 'view/impagina_blog.css';

// debug('config', $config);

define('MANUAL_CONTENT_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/content.json'); // TODO: ???
define('MANUAL_LIST_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'list.json'); // TODO: ????
define('MANUAL_CACHE_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/cache/');


// share this with update.php
define('MANUAL_CACHE_TOC_HTML_FILE', 'toc_html.json');


if (is_file(MANUAL_CONTENT_PATH) && is_file(MANUAL_LIST_PATH)) {
    $content = json_decode(file_get_contents(MANUAL_CONTENT_PATH), true);
    $list = json_decode(file_get_contents(MANUAL_LIST_PATH), true);
} elseif(is_file('install.php')) {
    header('Location: '.pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME).'/'.'install.php');
}

// define('MANUAL_MODREWRITE_ENABLED', array_key_exists('HTTP_MOD_REWRITE', $_SERVER));
define('MANUAL_MODREWRITE_ENABLED', true);

define('MANUAL_TEMPLATE_HEADER_FILE', 'view/template_header.html');
define('MANUAL_TEMPLATE_CSS_FILE', 'view/manual.css');
define('MANUAL_TEMPLATE_TOC_FILE', 'view/template_toc.html');
define('MANUAL_TEMPLATE_CHAPTER_FILE', 'view/template_chapter.html');
define('MANUAL_TEMPLATE_FOOTER_FILE', 'view/template_footer.html');

if ((MANUAL_TEMPLATE_HEADER_FILE != '') && file_exists(MANUAL_TEMPLATE_HEADER_FILE)) {
    define('MANUAL_TEMPLATE_HEADER', file_get_contents(MANUAL_TEMPLATE_HEADER_FILE));
} else {
    define('MANUAL_TEMPLATE_HEADER', <<<EOT
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>\$title</title>
<link href="view/\$stylesheet_css" rel="stylesheet" type="text/css" media="screen">
<style>
</style>
</head>
<body>
<header>
\$language
</header>     
<h1><a href="\$manual_http_url">\$manual_title</a></h1>
EOT
    );
}
if ((MANUAL_TEMPLATE_CHAPTER_FILE != '') && file_exists(MANUAL_TEMPLATE_CHAPTER_FILE)) {
    define('MANUAL_TEMPLATE_CHAPTER', file_get_contents(MANUAL_TEMPLATE_CHAPTER_FILE));
} else {
    define('MANUAL_TEMPLATE_CHAPTER', <<<EOT
\$content
EOT
    );
}
if ((MANUAL_TEMPLATE_TOC_FILE != '') && file_exists(MANUAL_TEMPLATE_TOC_FILE)) {
    define('MANUAL_TEMPLATE_TOC', file_get_contents(MANUAL_TEMPLATE_TOC_FILE));
} else {
    define('MANUAL_TEMPLATE_TOC', <<<EOT
\$content
EOT
    );
}
if ((MANUAL_TEMPLATE_FOOTER_FILE != '') && file_exists(MANUAL_TEMPLATE_FOOTER_FILE)) {
    define('MANUAL_TEMPLATE_FOOTER', file_get_contents(MANUAL_TEMPLATE_FOOTER_FILE));
} else {
    define('MANUAL_TEMPLATE_FOOTER', <<<EOT
</body>
</html>
EOT
    );
}

$content_language = "";
if (!empty($config['language'])) {
    $content_language = "<ul>\n";
    foreach ($config['language'] as $item) {
        $content_language .= "<li>$item</li>\n";
    }
    $content_language .= "</ul>\n";
}

echo(strtr(
    MANUAL_TEMPLATE_HEADER,
    array (
        '$language' => $content_language,
        '$manual_title' => $config['title'],
        '$manual_http_url' => MANUAL_HTTP_URL,
        // TODO: $site_http_url?
    )
));

// debug('_REQUEST', $_REQUEST);
// debug('list', $list);
// debug('content', $content);


// TODO: set it as cookie
$lang = array_key_exists('lang', $_REQUEST) ? $_REQUEST['lang'] : MANUAL_LANG_DEFAULT;


// TODO: check if man and page are defined in an index! --> check it from cache.json
if (array_key_exists('man', $_REQUEST)) :
    $book_toc_html = json_decode(file_get_contents(MANUAL_CACHE_PATH.$_REQUEST['man'].'/'.MANUAL_CACHE_TOC_HTML_FILE), true);
    // debug('book_toc_html', $book_toc_html);
    if (array_key_exists('page', $_REQUEST)) :
        echo(file_get_contents('cache/'.$_REQUEST['man'].'/'.$_REQUEST['page'].'/'.$_REQUEST['page'].'-'.$lang.'.html'));
    else :
        echo($book_toc_html[$lang]);
    endif;
else :
    echo("<ul>\n");
    foreach ($config['manual'] as $key => $value) :
        if (array_key_exists($lang, $value['title'])) :
            echo("<li><a href=\"".MANUAL_HTTP_URL."?man=".$key."&lang=$lang\">".$value['title'][$lang]."</a></li>\n");
        endif;
    endforeach;
    echo("</ul>\n");
endif;
echo(MANUAL_TEMPLATE_FOOTER);
