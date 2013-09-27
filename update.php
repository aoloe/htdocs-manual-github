<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

function debug($label, $value) {
    echo("<p>$label<br /><pre>".htmlentities(print_r($value, 1))."</pre></p>");
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
define('MANUAL_MODREWRITE_ENABLED', true);
define('MANUAL_GITHUB_NOREQUEST', true); // set to true for debugging purposes only
define('MANUAL_FORCE_UPDATE', false); // for debugging purposes only
define('MANUAL_STORE_NOUPDATE', true); // set to true for debugging purposes only

define('MANUAL_CACHE_LANGUAGE_FILE', 'language.json');
define('MANUAL_CACHE_TOC_HTML_FILE', 'toc_html.json');

// debug('apache get_env', apache_getenv('HTTP_MOD_REWRITE'));

if (is_file(MANUAL_CONFIG_FILE)) {
    $config = json_decode(file_get_contents(MANUAL_CONFIG_FILE), 1);
} else {
    // header('Location: '.pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME).'/'.'install.php');
}
// debug('config', $config);
?>
<html>
<head>
<title><?= $config['title'] ?></title>
<style>
    .warning {background-color:yellow;}
</style>
</head>
<body>
<h1><?= $config['title'] ?> Update</h1>

<?php
if (file_exists('install.php')) {
    echo('<p class="warning">You should remove the <a href="install.php">install file</a>.</p>');
}

function get_cache($manual_id) {
    $result = array();
    $cache_path = MANUAL_CACHE_PATH.$manual_id.'/';
    if (!file_exists($cache_path)) {
        mkdir($cache_path);
    }

    if (file_exists($cache_path.MANUAL_CACHE_FILE)) {
        $result = json_decode(file_get_contents($cache_path.MANUAL_CACHE_FILE));
    }
    if (empty($result)) {
        $result = array (
            'title' => array (),
            'toc' => array (),
        );
    }
    return $result;
}

function & array_get_item(&$tree, $path) {
    $path = $path == '.' ? array() : $path;
    $path = is_array($path) ? $path : explode('/', $path);
    $current = &$tree;
    foreach ($path as $item) {
        if (!isset($current[$item])) {
            $current[$item] = array();
        }
        $current = &$current[$item];
    }
    return $current;
}

/**
 * sets the value into the tree, based on the path.
 */
/*
function array_set(&$tree, $path, $value) {
    $path = $path == '.' ? array() : $path;
    $path = is_array($path) ? $path : explode('/', $path);
    $current = &$tree;
    foreach ($path as $item) {
        if (!isset($current[$item])) {
            $current[$item] = array();
        }
        $current = &$current[$item];
    }
    $current[$value['filename']] = $value;
}
*/

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
}

function get_github_file_list($user) {
    $result = array();
    if (!MANUAL_GITHUB_NOREQUEST) {
        $result = get_content_from_github(GITHUB_URL);
        file_put_contents("content_github.json", $result);
    } else {
        echo('<p class="warning">Requests are from the cache: queries to GitHub are disabled.</p>');
        $result = file_get_contents("content_github.json");
    }
    $result = json_decode($result, true);

    // debug('result', $result);
    return $result;
}

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
}

/**
 * get list of files to be downloaded from github
 */
function get_file_download($toc, $cache) {
    $result = array();
    foreach ($toc as $item) {
        // TODO: only do this if it's not in the book_toc from the cache or it is in the cache_update
        $active = false;
        foreach ($item['title'] as $key => $value) {
            $filename = $item['directory'].'-'.$key.'.md';
            $path = $item['directory'].'/'.$filename;
            if (is_published($item, $key, $cache)) {
                $result[] = $path;
            }
        }
        if (!empty($item['items'])) {
            $result = array_merge($result, get_file_download($item['items'], $cache));
        }
    }
    return $result;
} // get_file_download()

/**
 * download the files from github
 */
function downlad_files($file, $cache_path) {
    foreach ($file as $item) {
        // debug('item', $item);
        if (!MANUAL_STORE_NOUPDATE) {
            $content = get_content_from_github(GITHUB_URL_RAW.$item);
        } else {
            $content = "# Introduction";
        }
        // debug('content', $content);

        $cache_filename = $item;
        if (pathinfo($item, PATHINFO_EXTENSION) == 'md') {
            $content = Markdown($content);
            $cache_filename = substr($cache_filename, 0, -3).'.html';
        }
        // debug('content', $content);
        // debug('cache_path', $cache_path);
        $cache_section_path = $cache_path;
        // debug('cache_section_path', $cache_section_path);
        foreach (array_slice(explode('/', $item), 0, -1) as $iitem) {
            // debug('iitem', $iitem);
            $cache_section_path .= $iitem.'/';
            // debug('cache_path', $cache_path);
            if (!file_exists($cache_section_path)) {
                mkdir($cache_section_path);
            }
        }
        // debug('cache_path', $cache_path);
        // debug('filename', $filename);
        if (is_dir($cache_section_path) && is_writable($cache_section_path)) {
            file_put_contents($cache_path.$cache_filename, $content);
        }
    }
}


if (array_key_exists('man', $_REQUEST) && array_key_exists($_REQUEST['man'], $config['manual'])) {

    $manual_id = $_REQUEST['man'];

    $cache = get_cache($manual_id);
    // debug('cache', $cache);

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

    $content_github = get_github_file_list($config_manual['git_user']);

    // check each file on github, compare it to the cache
    $update_time = time();
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
            $cache_update[$item['path']] = $item;
        }
    }

    // debug('cache', $cache);
    // debug('cache_update', $cache_update);

    // TODO: in get_cache() we are making sure that $cache_path exists... but i'm not sure it's a good idea!
    $cache_path = MANUAL_CACHE_PATH.$manual_id.'/';
    
    // TODO: define a constant for book.yaml
    include_once("spyc.php");
    $toc_update = false;
    if (array_key_exists('book.yaml', $cache_update)) {
        if (!MANUAL_STORE_NOUPDATE) {
            $book = get_content_from_github(GITHUB_URL_RAW.'book.yaml');
        } else {
            $book = file_get_contents('book_github.yaml');
        }
        // file_put_contents($cache_path.'book.yaml', $book);
        $toc_update = true;
    } else {
        file_get_contents($cache_path.'book.yaml');
    }
    $book = Spyc::YAMLLoadString($book);
    // debug('book', $book);

    // TODO: first read the book_toc from the cache

    $book_toc = get_toc($book['toc']);
    // debug('book_toc', $book_toc);

    $book_language = get_language($book_toc, $cache);
    // debug('book_language', $book_language);

    $file_download = get_file_download($book_toc, $cache);
    // debug('file_download', $file_download);

    include_once("markdown.php");

    file_put_contents($cache_path.MANUAL_CACHE_LANGUAGE_FILE, json_encode($book_language));

    downlad_files($file_download, $cache_path);

    $book_toc_html = array();
    foreach (array_keys($book_language) as $item) {
        $book_toc_html[$item] = get_toc_html($book_toc, $manual_id, $item);
    }
    // debug('book_toc_html', $book_toc_html);
    file_put_contents($cache_path.MANUAL_CACHE_TOC_HTML_FILE, json_encode($book_toc_html));

} // if man in request

$rate_limit = json_decode(get_content_from_github("https://api.github.com/rate_limit"));
// debug('rate_limit', $rate_limit);

echo("<p>".$rate_limit->rate->remaining." hits remaining out of ".$rate_limit->rate->limit." for the next hour.</p>");

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
