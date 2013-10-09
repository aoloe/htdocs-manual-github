<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
    
include_once("spyc.php");
include_once("markdown.php");

function debug($label, $value) {
    echo("<p>$label<br /><pre>".htmlentities(print_r($value, 1))."</pre></p>");
}

class Manual_log {
    public static $warning = array();
    public static $error = array();
    public static function render() {
        foreach (self::$error as $item) {
            echo('<p class="error">'.$item.'</p>');
        }
        foreach (self::$warning as $item) {
            echo('<p class="warning">'.$item.'</p>');
        }
    }
}

define('MANUAL_BASE_PATH', dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
// debug('MANUAL_BASE_PATH', MANUAL_BASE_PATH);


define('MANUAL_CONFIG_FILE', MANUAL_BASE_PATH.'config.json');
define('MANUAL_CACHE_PATH', MANUAL_BASE_PATH.'cache/');
define('MANUAL_CACHE_FILE', 'cache.json');

define('MANUAL_HTTP_URL', sprintf('http://%s%s/', $_SERVER['SERVER_NAME'], dirname(pathinfo($_SERVER['REQUEST_URI'], PATHINFO_DIRNAME))));
define('MANUAL_HTTP_ENGINE_URL', sprintf('http://%s%s/', $_SERVER['SERVER_NAME'], pathinfo($_SERVER['REQUEST_URI'], PATHINFO_DIRNAME)));
define('MANUAL_HTTP_UPDATE_URL', MANUAL_HTTP_ENGINE_URL.'update.php');
// define('MANUAL_MODREWRITE_ENABLED', array_key_exists('HTTP_MOD_REWRITE', $_SERVER));
define('MANUAL_MODREWRITE_ENABLED', true); // TODO: not used yet
define('MANUAL_GITHUB_NOREQUEST', false); // set to true for debugging purposes only
define('MANUAL_DEBUG_NO_HTTP_REQUEST',false); // set to true for debugging purposes only
define('MANUAL_FORCE_UPDATE', true); // for debugging purposes only

define('MANUAL_CACHE_GITHUB_FILE', 'cache.json');
define('MANUAL_SOURCE_BOOK_FILE', 'book.yaml');
define('MANUAL_CACHE_TOC_FILE', 'toc.json');
define('MANUAL_CACHE_LANGUAGE_FILE', 'language.json');
define('MANUAL_CACHE_SECTION_FILE', 'section.json');
define('MANUAL_CACHE_TOC_HTML_FILE', 'toc_html.json');

// debug('apache get_env', apache_getenv('HTTP_MOD_REWRITE'));

function ensure_directory_writable($path, $base_path = '') {
    $result = false;
    if ($base_path != '') {
        $base_path = rtrim($base_path, '/').'/';
        $path = trim(substr($path, count($base_path) -1), '/');
    }
    if (file_exists($base_path.$path)) {
        $result = is_dir($base_path.$path) && is_writable($base_path.$path);
    } else {
        $result = true;
        $path_item = $base_path;
        foreach (explode('/', $path) as $item) {
            $path_item .= $item.'/';
            if (!file_exists($path_item)) {
                $result = mkdir($path_item);
            } else {
                $result = is_dir($path_item);
            }
            if (!$result) {
                break;
            }
        }
        $result &= is_writable($base_path.$path);
    }
    return $result;
}

/**
 * check if the file is writable and if not, check that the directories leading to it do exist or can
 * be created
 * @param string $path the path, inclusive the file name, separated by /.
 * it must be a relative path starting from the current directory or from $base_path when defined.
 * @param string $path the part of the path where it should not create directories.
 */
function ensure_file_writable($path, $base_path = '') {
    $result = false;
    if ($base_path != '') {
        $base_path = rtrim($base_path, '/').'/';
        $path = trim(substr($path, count($base_path) - 1), '/');
    }
    if (file_exists($base_path.$path)) {
        $result = is_file($base_path.$path) && is_writable($base_path.$path);
    } else {
        $result = ensure_directory_writable(dirname($path), $base_path);
    }
    return $result;
} // ensure_file_writable

function put_cache($path, $content, $manual_id = null) {
    $result = false;
    $path_cache = (isset($manual_id) ? $manual_id.'/' : '').$path;
    if (ensure_file_writable($path_cache, MANUAL_CACHE_PATH)) {
        file_put_contents(MANUAL_CACHE_PATH.$path_cache, $content);
    }
    return $result;
} // file_put_cache_json()

function put_cache_json($path, $content, $manual_id = null) {
    return put_cache($path, json_encode($content), $manual_id);
} // file_put_cache_json()

function get_cache_json($path, $manual_id = null) {
    $result = array();
    $path_cache = (isset($manual_id) ? $manual_id.'/' : '').$path;
    if (file_exists(MANUAL_CACHE_PATH.$path_cache)) {
        $result = json_decode(file_get_contents(MANUAL_CACHE_PATH.$path_cache), true);
        if ($result === false) {
            $result = array();
        }
    }
    return $result;
} // file_get_cache_json()

function get_content_from_github($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // curl_setopt($ch, CURLOPT_HEADER, true);
    // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    // curl_setopt($ch, CURLOPT_VERBOSE, true);
    $content = curl_exec($ch);
    // debug('curl getinfo', curl_getinfo($ch));
    curl_close($ch);
    return $content;
} // get_content_from_github()

function get_github_file_list($user) {
    $result = array();
    if (!MANUAL_GITHUB_NOREQUEST) {
        $result = get_content_from_github(GITHUB_URL);
        file_put_contents("content_github.json", $result);
    } else {
        $result = file_get_contents("content_github.json");
    }
    $result = json_decode($result, true);

    // debug('result', $result);
    return $result;
} // get_github_file_list()

function get_cache($manual_id) {
    $result = array();
    $result = get_cache_json(MANUAL_CACHE_FILE, $manual_id);
    if (empty($result)) {
        $result = array (
            'title' => array (),
            'toc' => array (),
        );
    }
    return $result;
} // get_cache()

/**
 * get a nested toc, that will be stored in the toc.json file and will be used to generate the html toc
 */
function get_toc($toc_flat) {
    $result = array();
    $nodes = array();
    foreach ($toc_flat as & $item) {
        if (array_key_exists('directory', $item) && !empty($item['directory'])) {
            $item['items'] = array();
            $nodes[$item['directory']] = & $item;
            if (array_key_exists('parent', $item) && !empty($item['parent'])) {
                $nodes[$item['parent']]['items'][] = & $item;
            } else {
                $result[] = & $item;
            }
        }
    }
    return $result;
} // get_toc()

function get_toc_html($toc, $manual_id, $language) {
    $result = "";
    $result .= "<ul>\n";
    foreach ($toc as $item) {
        $result .= "<li><a href=\"".MANUAL_HTTP_URL."?man=$manual_id&section=".$item['directory']."\">".$item['title'][$language]."</a></li>\n";
        if (array_key_exists('items', $item)) {
            $result .= get_toc_html($item['items'], $manual_id, $language);
        }
    }
    $result .= "</ul>\n";
    return $result;
} // get_toc_html()

/**
 * a section is published if there is a file on github for the specific language _and_
 * the full section or the language are not marked as not published
 */
function is_published($item, $language, $cache) {
    $filename = $item['directory'].'-'.$language.'.md';
    $path = $item['directory'].'/'.$filename;
    $result = (
        array_key_exists($path, $cache) && 
        (
            !array_key_exists('published', $item) ||
            (is_bool($item['published']) && $item['published']) ||
            (is_array($item['published']) &&
                (
                    !array_key_exists($language, $item['published']) ||
                    $item['published'][$language]
                )
            )
        )
    );
    return $result;
}

/**
 * return the list of the languages used in the titles with the number of section published
 */
function get_language($toc, $cache) {
    $result = array();
    foreach ($toc as $item) {
        foreach (array_keys($item['title']) as $iitem) {
            if (is_published($item, $iitem, $cache)) {
                if (array_key_exists($iitem, $result)) {
                    $result[$iitem]++;
                } else {
                    $result[$iitem] = 0;
                }
            }
        }
        if (!empty($item['items'])) {
            $language = get_language($item['items'], $cache);
            foreach ($language as $key => $value) {
                if (!array_key_exists($key, $result)) {
                    $result[$key] = 0;
                }
                $result[$key] += $value;
            }
        }
    }
    return $result;
} // get_language()

/**
 * return the list of files that are published
 */
function get_book_files($book_toc, $cache) {
    $result = array();
    foreach ($book_toc as $item) {
        // TODO: only do this if it's not in the book_toc from the cache or it is in the cache_update
        // debug('item', $item);
        $published = array();
        foreach ($item['title'] as $key => $value) {
            $filename_md = $item['directory'].'-'.$key.'.md';
            $filename_html = $item['directory'].'-'.$key.'.html';
            if (is_published($item, $key, $cache)) {
                $published[$key] = array(
                    'raw' => $item['directory'].'/'.$filename_md,
                    'render' => array (
                        'source' => 'md',
                        'target' => 'html',
                        'filename' => $item['directory'].'/'.$filename_html,
                    ),
                );
            }
        }
        $items = array();
        if (!empty($item['items'])) {
            $items = get_book_files($item['items'], $cache);
        }
        if (!empty($published) || !empty($items)) {
            $result[$item['directory']] = array (
                'published' => $published,
            );
            $result = array_merge($result, $items);
        }
    }
    return $result;
} // get_book_files()


/**
 * download the files from github
 */
function downlad_files($file, $manual_id) {
    foreach ($file as $key => $value) {
        // debug('value', $value);
        foreach ($value['published'] as $kkey => $vvalue) {
            // debug('vvalue', $vvalue);
            if (!MANUAL_DEBUG_NO_HTTP_REQUEST) {
                $content = get_content_from_github(GITHUB_URL_RAW.$vvalue['raw']);
            } else {
                $content = "# Introduction";
            }
            // debug('content', $content);
            $cache_filename = $vvalue['raw'];
            if (
                array_key_exists('render', $vvalue) &&
                ($vvalue['render']['source'] == 'md') && 
                ($vvalue['render']['target'] == 'html')
            ) {
                $content = Markdown($content);
                $cache_filename = $vvalue['render']['filename'];
            }
            // debug('content', $content);
            put_cache($cache_filename, $content, $manual_id);
        }
    }
} // downlad_files()

if (!MANUAL_GITHUB_NOREQUEST) {
    Manual_log::$warning[] = 'Requests are from the cache: queries to GitHub are disabled.';
}

if (is_file(MANUAL_CONFIG_FILE)) {
    $config = json_decode(file_get_contents(MANUAL_CONFIG_FILE), true);
} else {
    header('Location: '.pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME).'/'.'configure.php');
}

if (!ensure_directory_writable(MANUAL_CACHE_PATH)) {
    Manual_log::$error[] = 'cache is not writable';
}
// debug('config', $config);
if (file_exists('install.php')) {
    Manual_log::$warning[] = 'You should remove the <a href="install.php">install file</a>.';
}

if (array_key_exists('man', $_REQUEST) && array_key_exists($_REQUEST['man'], $config['manual'])) {
    $manual_id = $_REQUEST['man'];
    ensure_directory_writable(MANUAL_CACHE_PATH.$manual_id);

    $config_manual = $config['manual'][$manual_id];
    // debug('config_manual', $config_manual);

    define('GITHUB_URL', strtr(
        'https://api.github.com/repos/$user/$repository/git/trees/master?recursive=1',
        array(
            '$user' => $config_manual['git_user'],
            '$repository' => $config_manual['git_repository'],
        )
    ));
    define('GITHUB_URL_RAW', strtr(
        'https://raw.github.com/$user/$repository/master/',
        array(
            '$user' => $config_manual['git_user'],
            '$repository' => $config_manual['git_repository'],
        )
    ));

    $cache = get_cache($manual_id);
    // debug('cache', $cache);

    $content_github = get_github_file_list($config_manual['git_user']);
    // debug('content_github', $content_github);

    // $cache_path = MANUAL_CACHE_PATH.$manual_id.'/';

    // check each file on github, compare it to the cache
    $cache_update = array();
    foreach ($content_github['tree'] as $item) {
        if (!array_key_exists($item['path'], $cache)) {
            $cache[$item['path']] = array (
                'sha' => '',
            );
        }
        if ($cache[$item['path']]['sha'] != $item ['sha']) {
            $item = array (
                'sha' => $item['sha'],
                'path' => $item['path'],
            );
            $cache[$item['path']] = $item;
            // TODO: check against the old cache
            $cache_update[$item['path']] = $item;
        }
    }
    put_cache_json(MANUAL_CACHE_GITHUB_FILE, $cache, $manual_id);
    // debug('cache', $cache);
    // debug('cache_update', $cache_update);

    if (array_key_exists(MANUAL_SOURCE_BOOK_FILE, $cache_update)) {
        if (!MANUAL_DEBUG_NO_HTTP_REQUEST) {
            $book = get_content_from_github(GITHUB_URL_RAW.MANUAL_SOURCE_BOOK_FILE);
        } else {
            $book = file_get_contents('book_github.yaml');
        }
        // file_put_contents($cache_path.'book.yaml', $book);
        $book = Spyc::YAMLLoadString($book);
        put_cache_json(MANUAL_CACHE_TOC_FILE, $book, $manual_id);
    } else {
        $book = get_cache_json(MANUAL_CACHE_TOC_FILE, $manual_id);
    }
    // debug('book', $book);

    // TODO: first read the book_toc from the cache

    $book_toc = get_toc($book['toc']);
    // debug('book_toc', $book_toc);

    $book_files = get_book_files($book_toc, $cache);
    // debug('book_files', $book_files);
    put_cache_json(MANUAL_CACHE_SECTION_FILE, $book_files, $manual_id);

    $book_language = get_language($book_toc, $cache);
    // debug('book_language', $book_language);
    put_cache_json(MANUAL_CACHE_LANGUAGE_FILE, $book_language, $manual_id);

    downlad_files($book_files, $manual_id);

    $book_toc_html = array();
    foreach (array_keys($book_language) as $item) {
        $book_toc_html[$item] = get_toc_html($book_toc, $manual_id, $item);
    }
    // debug('book_toc_html', $book_toc_html);
    put_cache_json(MANUAL_CACHE_TOC_HTML_FILE, $book_toc_html, $manual_id);

} // if man in request

$rate_limit = json_decode(get_content_from_github("https://api.github.com/rate_limit"));
// debug('rate_limit', $rate_limit);

?>
<html>
<head>
<title><?= $config['title'] ?></title>
<style>
    .warning {background-color:yellow;}
    .error {background-color:orange;}
</style>
</head>
<body>
<h1><?= $config['title'] ?> Update</h1>

<?php

Manual_log::render();

if ($rate_limit) {
    echo("<p>".$rate_limit->rate->remaining." hits remaining out of ".$rate_limit->rate->limit." for the next hour.</p>");
}

$form_manual_checkbox = array();

foreach ($config['manual'] as $key => $value) {
    $form_manual_checkbox[] = "<input type=\"checkbox\" name=\"man\" value=\"$key\" id=\"$key\"><label for=\"$key\">".reset($value['title'])."</label>";
}

?>
<form method="post">
<p><?= implode("<br />\n", $form_manual_checkbox) ?></p>
<input type="checkbox" name="force" value="yes" id="force_update" /> <label for="force_update">Force</label><br />
<input type="submit" value="&raquo;" />
</form>
<p>You can now <a href="<?= MANUAL_HTTP_URL ?>">read your manuals</a>.</p>
</body>
</html>
