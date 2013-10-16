<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

function debug($label, $value) {
    echo("<p>$label<br /><pre>".htmlentities(print_r($value, 1))."</pre></p>");
}
include('config.php');


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

// debug('$_SERVER', $_SERVER);
// debug('config', $config);

if (is_file(MANUAL_CONTENT_PATH) && is_file(MANUAL_LIST_PATH)) {
    $content = json_decode(file_get_contents(MANUAL_CONTENT_PATH), true);
    $list = json_decode(file_get_contents(MANUAL_LIST_PATH), true);
} elseif(is_file('install.php')) {
    header('Location: '.pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME).'/'.'install.php');
}

// define('MANUAL_MODREWRITE_ENABLED', array_key_exists('HTTP_MOD_REWRITE', $_SERVER));
define('MANUAL_MODREWRITE_ENABLED', true); // TODO: not used yet

define('MANUAL_TEMPLATE_HTML_FILE', 'view/template_html.html');
define('MANUAL_TEMPLATE_HEADER_FILE', 'view/template_header.html');
define('MANUAL_TEMPLATE_CSS_FILE', 'view/manual.css');
define('MANUAL_TEMPLATE_TOC_FILE', 'view/template_toc.html');
define('MANUAL_TEMPLATE_CHAPTER_FILE', 'view/template_chapter.html');
/*
define('MANUAL_TEMPLATE_FOOTER_FILE', 'view/template_footer.html');
*/

if ((MANUAL_TEMPLATE_HTML_FILE != '') && file_exists(MANUAL_TEMPLATE_HTML_FILE)) {
    define('MANUAL_TEMPLATE_HTML', file_get_contents(MANUAL_TEMPLATE_HTML_FILE));
} else {
    define('MANUAL_TEMPLATE_HTML', <<<EOT
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>\$page_title</title>
<link href="\$stylesheet_css" rel="stylesheet" type="text/css" media="screen">
<style>
</style>
</head>
<body>
<header>
\$header
</header>     
<h1><a href="\$page_title_url">\$page_title</a></h1>
\$content
</body>
</html>
EOT
    );
}

if ((MANUAL_TEMPLATE_HEADER_FILE != '') && file_exists(MANUAL_TEMPLATE_HEADER_FILE)) {
    define('MANUAL_TEMPLATE_HEADER', file_get_contents(MANUAL_TEMPLATE_HEADER_FILE));
} else {
    define('MANUAL_TEMPLATE_HEADER', <<<EOT
        \$navigation
        \$language
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

$content_language = "";
/*
if (!empty($config['language'])) {
    $content_language = "<ul>\n";
    foreach ($config['language'] as $item) {
        $content_language .= "<li>$item</li>\n";
    }
    $content_language .= "</ul>\n";
}
*/

// debug('_REQUEST', $_REQUEST);
// debug('list', $list);
// debug('content', $content);


// TODO: set it as cookie
$language = array_key_exists('lang', $_REQUEST) ? $_REQUEST['lang'] : MANUAL_LANG_DEFAULT;

function get_config_manual($config, $man) {
    $result = null;
    // debug('config', $config);
    if (array_key_exists($man, $config['manual'])) {
        $result = $config['manual'][$man];
    }
    return $result;
} // get_config_manual()

function get_page($section, $manual_id, $page_id, $language) {
    $result = null;
    // debug('language', $language);
    // debug('page_id', $page_id);
    // debug('section', $section);
    if (array_key_exists($page_id, $section) && array_key_exists($language, $section[$page_id]['published'])) {
        $item = $section[$page_id]['published'][$language];
        $filename = 'cache/'.$manual_id.'/'.(array_key_exists('render', $item) ? $item['render']['filename'] : $item['raw']);
        $result = file_get_contents($filename);
    }
    // debug('result', $result);
    return $result;
}

// TODO: check if man and page are defined in an index! --> check it from cache.json
$manual_id = null;
$config_manual = null;
$page_id = null;
$page = null;

$navigation = array (
    "http://".$_SERVER['HTTP_HOST'] => "Graphicslab",
);


if (array_key_exists('man', $_REQUEST)) {
    $manual_id = $_REQUEST['man'];
    $config_manual = get_config_manual($config, $manual_id);
}

function get_content_navigation($navigation) {
    $result = "";
    if (!empty($navigation)) {
        $result .= "<ul class=\"navigation\">";
        foreach ($navigation as $key => $value) {
            $result .= "<li><a href=\"".$key."\">".$value."</a></li>";
        }
        $result .= "</ul>";
    }
    return $result;
} // get_content_navigation()


// debug('config_manual', $config_manual);
$content_page = "";
if (isset($config_manual)) :

    $navigation[MANUAL_HTTP_URL] = "Les manuels";

    $book_section = json_decode(file_get_contents(MANUAL_CACHE_PATH.$manual_id.'/'.MANUAL_CACHE_SECTION_FILE), true);
    $book_toc_html = json_decode(file_get_contents(MANUAL_CACHE_PATH.$manual_id.'/'.MANUAL_CACHE_TOC_HTML_FILE), true);
    // debug('book_toc_html', $book_toc_html);
    if (array_key_exists('section', $_REQUEST)) {
        $page_id = $_REQUEST['section'];
        $page = get_page($book_section, $manual_id, $page_id, $language);
    }
    // debug('page', $page);
    if (isset($page)) :
        $navigation[MANUAL_HTTP_URL."?man=$manual_id"] = $config_manual['title'][$language];
        $content_title_url = MANUAL_HTTP_URL."?man=$manual_id";
        $content_title = $config_manual['title'][$language];
        $content_page = $page;
    else :
        $content_title = $config_manual['title'][$language];
        $content_title_url = MANUAL_HTTP_URL."?man=$manual_id";
        $content_page = $book_toc_html[$language];
    endif;

else :

    $content_page .= "<ul class=\"toc\">\n";
    foreach ($config['manual'] as $key => $value) :
        if (array_key_exists($language, $value['title'])) :
            $content_page .= "<li><a href=\"".MANUAL_HTTP_URL."?man=".$key."&lang=$language\">".$value['title'][$language]."</a></li>\n";
        endif;
    endforeach;
    $content_page .= "</ul>\n";
endif;

$content_navigation = get_content_navigation($navigation);

$content_header = strtr(
    MANUAL_TEMPLATE_HEADER,
    array (
        '$navigation' => $content_navigation,
        '$language' => $content_language,
    )
);

echo(strtr(
    MANUAL_TEMPLATE_HTML,
    array (
        '$header' => $content_header,
        '$page_title' => $config['title'],
        '$page_title_url' => MANUAL_HTTP_URL,
        '$stylesheet_css' => MANUAL_TEMPLATE_CSS_FILE,
        '$content' => $content_page,
    )
));
