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

include('config.php');

// define('MANUAL_MODREWRITE_ENABLED', array_key_exists('HTTP_MOD_REWRITE', $_SERVER));
define('MANUAL_MODREWRITE_ENABLED', true); // TODO: not used yet
define('MANUAL_LOCAL_FILES_REQUEST', false); // set to true for debugging purposes only
define('MANUAL_DEBUG_NO_FILELIST_REQUEST', false); // set to true for debugging purposes only
define('MANUAL_DEBUG_NO_HTTP_REQUEST', false); // set to true for debugging purposes only
define('MANUAL_FORCE_UPDATE', true); // set to true for debugging purposes only


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
                // if (!$result) debug('path_item', $path_item);
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
} // put_cache()

function put_cache_json($path, $content, $manual_id = null) {
    return put_cache($path, json_encode($content), $manual_id);
} // put_cache_json()

function get_cache_json($path, $manual_id = null) {
    $result = array();
    $path_cache = (isset($manual_id) ? $manual_id.'/' : '').$path;
    // debug('path_cache', $path_cache);
    if (file_exists(MANUAL_CACHE_PATH.$path_cache)) {
        $result = json_decode(file_get_contents(MANUAL_CACHE_PATH.$path_cache), true);
        if ($result === false) {
            $result = array();
        }
    }
    // debug('get_cache_json result', $result);
    return $result;
} // get_cache_json()

function get_content_from_github($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // curl_setopt($ch, CURLOPT_HEADER, true);
    // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $content = curl_exec($ch);
    // debug('curl getinfo', curl_getinfo($ch));
    curl_close($ch);
    // debug('content', $content);
    return $content;
} // get_content_from_github()

/**
 * get the list of files in the local directory
 */
function get_local_file_list($manual_id) {
    $result = array(
        "sha" => "local",
        "url" => "whatever",
        "tree" => array(),
    );
    $queue = array(MANUAL_LOCAL_PATH);
    while (!empty($queue)) {
        $path = array_shift($queue);
        // debug('path', $path);
        // debug('queue', $queue);
        if ($handle = opendir($path)) {
            while (false !== ($filename = readdir($handle))) {
                if ($filename != "." && $filename != ".." && $filename != ".git") {
                    $item = array (
                        "mode" => 0,
                        "type" => "blob",
                        "sha" => "",
                        "path" => substr($path, strlen(MANUAL_LOCAL_PATH), strlen($path)).$filename,
                        "size" => 0,
                        "url" => "",
                    );
                    if (is_dir($path.$filename)) {
                        $item["type"] = "tree";
                        $queue[] = $path.$filename.'/';
                    }
                    // debug('filename', $filename);
                    $result['tree'][] = $item;
                }
            }
            closedir($handle);
        }
    } // while queue
    // debug('result', $result);
    return $result;
} // get_local_file_list()

/**
 * get the list of files in the github repository through the github API
 */
function get_github_file_list($manual_id) {
    $result = array();
    if (MANUAL_LOCAL_FILES_REQUEST) {
        $result = get_local_file_list($manual_id);
    } elseif (!MANUAL_DEBUG_NO_FILELIST_REQUEST) {
        $result = json_decode(get_content_from_github(GITHUB_FILESLIST_URL), true);
        put_cache_json('content_github.json', $result, $manual_id);
    } else {
        $result = get_cache_json('content_github.json', $manual_id);
    }
    // debug('get_github_file_list result', $result);
    return $result;
} // get_github_file_list()

/**
 * read the tree list returned by github and distribute them by chapter
 */
function get_github_file_structure($list) {
    $result = array();
    foreach ($list as $key => $value) {
        $path_segment = explode('/', $value['path']);
        // read the "content" directory
        if ((count($path_segment) >= 2) && (reset($path_segment) == 'content')) {
            // debug('value', $value);
            $path_segment = array_slice($path_segment, 1);
            // debug('path_segment', $path_segment);
            if ($value['type'] == 'tree') {
                if (count($path_segment) == 1) {
                    // add the directory in the first level of the repository as chapters
                    $result[$path_segment[0]] = array (
                        'item' => array(),
                    );
                }
            } elseif ($value['type'] == 'blob') {
                $pathinfo = pathinfo(implode('/', $path_segment));
                // debug('pathinfo', $pathinfo);
                if ($pathinfo['filename'] == 'README') {
                } else {
                    // get the main chapter files
                    if (count($path_segment) == 2) {
                        // debug('content file path_segment', $path_segment);
                        $pathinfo = pathinfo($path_segment[1]);
                        // debug('pathinfo', $pathinfo);
                        if ($pathinfo['extension'] == 'md') { // TODO: accept also other extensions
                            $fileinfo = explode('-', $pathinfo['filename']);
                            // debug('fileinfo', $fileinfo);
                            $language_code = end($fileinfo);
                            $key = implode('-', array_slice($fileinfo, 0, -1));
                            if (strlen($language_code) == 2) {
                                // debug('key', $key);
                                if (array_key_exists($key, $result)) {
                                    $result[$key]['item'][$language_code] = $value;
                                }
                            }
                        }
                    }
                }

            }
        }
    }
    // debug('result', $result);
    return $result;
} // get_github_file_structure()

function get_cache($manual_id) {
    $result = array();
    if (!MANUAL_FORCE_UPDATE) {
        $result = get_cache_json(MANUAL_CACHE_FILE, $manual_id);
    }
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
    $result .= "<ul class=\"toc\">\n";
    foreach ($toc as $item) {
        // debug('item', $item);
        $result .= "<li>";
        if (array_key_exists($language, $item['title'])) {
            $result .= "<a href=\"".MANUAL_HTTP_URL."?man=$manual_id&section=".$item['directory']."&lang=$language\">".$item['title'][$language]."</a>\n";
        } else {
            foreach ($item['title'] as $key => $value) {
                $result .= "<a href=\"".MANUAL_HTTP_URL."?man=$manual_id&section=".$item['directory']."&lang=$key\">".$value."</a>\n";
            }
        }
        $result .= "</li>";
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
    $path = 'content/'.$item['directory'].'/'.$filename; // TODO: find a way to set the content/ in a dynamic way
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
    // debug('book_toc', $book_toc);
    // debug('cache', $cache);
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
function downlad_files($file, $manual_id, $cache) {
    // while (!empty($file)) {
    // remove each processed file, add the files to be processed for images
    // }
    foreach ($file as $key => $value) {
        // if ($key == 'inkscape-userinterface') {
        // debug('key', $key);
        // debug('value', $value);
        foreach ($value['published'] as $kkey => $vvalue) {
            // debug('vvalue', $vvalue);
            if (MANUAL_LOCAL_FILES_REQUEST) {
                // debug('vvalue', $vvalue);
                $content = file_get_contents(MANUAL_LOCAL_CONTENT_PATH.$vvalue['raw']);
            } elseif (!MANUAL_DEBUG_NO_HTTP_REQUEST) {
                // debug('http_request content', GITHUB_RAW_URL.$vvalue['raw']);
                $content = get_content_from_github(GITHUB_RAW_CONTENT_URL.$vvalue['raw']);
            } else {
                $content = "# Introduction";
                /*
                $content = "
## La fenêtre principale

abcd (defgh) [blah]
[test](image/inkscape-user_interface-fr.png)

[test a](image/inkscape-user_interface-fr.png)
                ";
                */
            }
            // debug('content', $content);
            $matches = array();
            if (preg_match_all('/!\[(.*?)\]\((.*?)\)/', $content, $matches)) {
                // debug('matches', $matches);
                for ($i = 0; $i < count($matches[2]); $i++) {
                    $item = $matches[2][$i];
                    if (array_key_exists('content/'.$key.'/'.$item, $cache)) {
                        // debug('url', GITHUB_RAW_CONTENT_URL.$key.'/'.$item);
                        if (MANUAL_LOCAL_FILES_REQUEST) {
                            $image = file_get_contents(MANUAL_LOCAL_CONTENT_PATH.$key.'/'.$item);
                        } else {
                            $image = get_content_from_github(GITHUB_RAW_CONTENT_URL.$key.'/'.$item);
                        }
                        put_cache($key.'/'.$item, $image, $manual_id);
                        $content = str_replace('!['.$matches[1][$i].']('.$item.')', '!['.$matches[1][$i].'](cache/'.$manual_id.'/'.$key.'/'.$item.')', $content); // TODO: find a good way to correctly set the pictures and their paths
                    } else {
                        Manual_log::$warning[] = "The ".$key.'/'.$item." is referenced but can't be found in the repository";
                    }
                }
            }
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
        // }
    }
} // downlad_files()

if (MANUAL_DEBUG_NO_FILELIST_REQUEST) {
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

// debug('_REQUEST', $_REQUEST);
$manual_id = null;
$config_manual = null;
if (array_key_exists('man', $_REQUEST)) {
    foreach ($config['manual'] as $item) {
        if ($item['id'] == $_REQUEST['man']) {
            $manual_id = $_REQUEST['man'];
            $config_manual = $item;
        }
    }
    // debug('config_manual', $config_manual);
}
// debug('manual_id', $manual_id);
if (isset($manual_id)) {
    ensure_directory_writable(MANUAL_CACHE_PATH.$manual_id);

    // debug('config_manual', $config_manual);

    define('MANUAL_LOCAL_PATH', $config_manual['local_path']);
    define('MANUAL_LOCAL_CONTENT_PATH', MANUAL_LOCAL_PATH.'content/');


    define('GITHUB_FILESLIST_URL', strtr(
        'https://api.github.com/repos/$user/$repository/git/trees/master?recursive=1',
        array(
            '$user' => $config_manual['git_user'],
            '$repository' => $config_manual['git_repository'],
        )
    ));
    define('GITHUB_RAW_URL', strtr(
        'https://raw.github.com/$user/$repository/master/',
        array(
            '$user' => $config_manual['git_user'],
            '$repository' => $config_manual['git_repository'],
        )
    ));
    define('GITHUB_RAW_CONTENT_URL', GITHUB_RAW_URL.$config_manual['git_content_directory'].'/');

    $cache = get_cache($manual_id);
    // debug('cache', $cache);

    $content_github = get_github_file_list($manual_id);
    // debug('content_github', $content_github);

    // get the list of chapters in the github directory
    $github_files = get_github_file_structure($content_github['tree']);
    // debug('github_files', $github_files);

    foreach ($github_files as $key => $value) {
        if (empty($value['item'])) {
            Manual_log::$warning[] = "The $key directory is not empty but contains no chapter files";
        }
    }

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

    if (MANUAL_LOCAL_FILES_REQUEST) {
        $book = file_get_contents(MANUAL_LOCAL_PATH.MANUAL_SOURCE_BOOK_FILE);
        $book = Spyc::YAMLLoadString($book);
        // put_cache_json(MANUAL_CACHE_TOC_FILE, $book, $manual_id);
    } elseif (array_key_exists(MANUAL_SOURCE_BOOK_FILE, $cache_update)) {
        if (!MANUAL_DEBUG_NO_HTTP_REQUEST) {
            // debug('book url', GITHUB_RAW_URL.MANUAL_SOURCE_BOOK_FILE);
            $book = get_content_from_github(GITHUB_RAW_URL.MANUAL_SOURCE_BOOK_FILE);
        } else {
            $book = file_get_contents('book_github.yaml');
        }
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

    downlad_files($book_files, $manual_id, $cache);

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
    $form_manual_checkbox[] = "<input type=\"checkbox\" name=\"man\" value=\"".$value['id']."\" id=\"".$value['id']."\"><label for=\"".$value['id']."\">".reset($value['title'])."</label>";
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
