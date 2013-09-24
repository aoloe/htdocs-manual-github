<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

function debug($label, $value) {
    echo("<p>$label<br /><pre>".htmlentities(print_r($value, 1))."</pre></p>");
}

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


define('MANUAL_BASE_PATH', dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
debug('MANUAL_BASE_PATH', MANUAL_BASE_PATH);


define('MANUAL_CONFIG_FILE', MANUAL_BASE_PATH.'config.json');
define('MANUAL_CACHE_PATH', MANUAL_BASE_PATH.'cache/');

define('MANUAL_HTTP_URL', sprintf('http://%s%s/', $_SERVER['SERVER_NAME'], dirname(pathinfo($_SERVER['REQUEST_URI'], PATHINFO_DIRNAME))));
define('MANUAL_HTTP_ENGINE_URL', sprintf('http://%s%s/', $_SERVER['SERVER_NAME'], pathinfo($_SERVER['REQUEST_URI'], PATHINFO_DIRNAME)));
define('MANUAL_HTTP_UPDATE_URL', MANUAL_HTTP_ENGINE_URL.'update.php');
// define('MANUAL_MODREWRITE_ENABLED', array_key_exists('HTTP_MOD_REWRITE', $_SERVER));
define('MANUAL_MODREWRITE_ENABLED', true);
define('MANUAL_GITHUB_NOREQUEST', true); // for debugging purposes only
define('MANUAL_FORCE_UPDATE', false); // for debugging purposes only
define('MANUAL_STORE_NOUPDATE', false); // for debugging purposes only

// debug('apache get_env', apache_getenv('HTTP_MOD_REWRITE'));

if (is_file(MANUAL_CONFIG_FILE)) {
    $config = json_decode(file_get_contents(MANUAL_CONFIG_FILE), 1);
} else {
    // header('Location: '.pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME).'/'.'install.php');
}
debug('config', $config);
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

if (!array_key_exists('man', $_REQUEST) || !array_key_exists($_REQUEST['man'], $config['manual'])) :
    echo("<ul>\n");
    foreach ($config['manual'] as $key => $value) :
        echo("<li><a href=\"".MANUAL_HTTP_UPDATE_URL."?man=".$key."\">".reset($value['title'])."</a></li>\n");
    endforeach;
    echo("</ul>\n");
else :
    $manual_id = $_REQUEST['man'];
    $cache_path = MANUAL_CACHE_PATH.$manual_id.'/';
    if (!file_exists($cache_path)) {
        mkdir($cache_path);
    }
    $config_manual = $config['manual'][$manual_id];
    // debug('config_manual', $config_manual);
    $github_url = strtr(
        'https://api.github.com/repos/$user/$repository/git/trees/master?recursive=1',
        array(
            '$user' => $config_manual['git_user'],
            '$repository' => $config_manual['git_repository'],
        )
    );
    $github_url_raw = strtr(
        'https://raw.github.com/$user/$repository/master/',
        array(
            '$user' => $config_manual['git_user'],
            '$repository' => $config_manual['git_repository'],
        )
    );
    // $github_url_raw = "https://raw.github.com/aoloe/libregraphics-manual-libregraphics_for_ONGs/master/";

    $rate_limit = json_decode(get_content_from_github("https://api.github.com/rate_limit"));
    // debug('rate_limit', $rate_limit);

    echo("<p>".$rate_limit->rate->remaining." hits remaining out of ".$rate_limit->rate->limit." for the next hour.</p>");

    if (!MANUAL_GITHUB_NOREQUEST) {
        $content_github = get_content_from_github($github_url);
        file_put_contents("content_github.json", $content_github);
    } else {
        echo('<p class="warning">Requests are from the cache: queries to GitHub are disabled.</p>');
        $content_github = file_get_contents("content_github.json");
    }
    $content_github = json_decode($content_github, true);

    // debug('content_github', $content_github);

    $tree_manual = array();

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

    // for now, only tested with one single path depth
    foreach ($content_github['tree'] as $item) {
        if ($item['type'] == 'tree') {
        } elseif ($item['type'] == 'blob') {
            // debug('item', $item);
            $path = pathinfo($item['path']);
            // debug('path', $path);

            array_set(
                $tree_manual,
                $path['dirname'],
                array(
                    'filename' => $path['basename'],
                    'path' => $path['dirname'] == '.' ? '' : $path['dirname'].'/',
                    'sha' => $item['sha']
                )
            );
        } else {
            debug('unknown type', $item);
        }
    }
    // debug('tree_manual', $tree_manual);
    include_once("spyc.php");
    include_once("markdown.php");
    if (array_key_exists('toc.yaml', $tree_manual)) {
        $tree_toc = $tree_manual['toc.yaml'];
        $toc = get_content_from_github($github_url_raw.$tree_toc['path'].$tree_toc['filename']);
        $toc = Spyc::YAMLLoadString($toc);
        // debug('toc', $toc);
        $content_toc = array();
        $content_toc_chapter = "";
        $test = true;
        foreach ($toc['toc'] as $item) {
            if ($item['directory'] != '') {
                // TODO: we only need the title for one single language at a time(eventually, we can show in gray the items which have not been translated yet)!
                foreach ($item['title'] as $key => $value) {
                    // debug('item', $item);
                    $filename = $item['directory'].'-'.$key.'.md';
                    $path = pathinfo($filename);
                    // debug('path', $path);
                    if (
                        array_key_exists($item['directory'], $tree_manual) &&
                        array_key_exists($filename, $tree_manual[$item['directory']])
                    ) {
                        // debug('item', $item);
                        if ($item['level'] == 1) {
                            $content_toc[$item['directory']] = array(
                                'title' => $value,
                                'item' => array(),
                            );
                            $content_toc_chapter = $item['directory'];
                        } else {
                            $content_toc[$content_toc_chapter]['item'][$item['directory']] = $value;
                        }
                        // if ($test) { // TODO: remove this limit to 1 as soon as it is working
                        $content = get_content_from_github($github_url_raw.$item['directory'].'/'.$filename);
                        $cache_filename = $filename;
                        if ($path['extension'] == 'md') {
                            $content = Markdown($content);
                            $cache_filename = substr($cache_filename, 0, -3).'.html';
                        }
                        // debug('content', $content);
                        $cache_path = MANUAL_CACHE_PATH.$manual_id.'/'.$item['directory'].'/';
                        if (!file_exists($cache_path)) {
                            mkdir($cache_path);
                        }
                        if (is_dir($cache_path) && is_writable($cache_path)) {
                            file_put_contents($cache_path.$cache_filename, $content);
                        }
                        // }
                        $test = false;
                    }
                }
            }
        }
        function get_item_toc($manual, $section, $label) {
            return "<li><a href=\"".MANUAL_HTTP_URL."?man=$manual&section=$section\">$label</a></li>\n";
        }
        function get_content_toc($toc) {
            global $manual_id;
            $result = "";
            if (!empty($toc)) {
                $result = "<ul>\n";
                foreach ($toc as $key => $value) {
                    if (is_array($value)) {
                        $result .= get_item_toc($manual_id, $key, $value['title']);
                        $result .= get_content_toc($value['item']);
                    } else {
                        $result .= get_item_toc($manual_id, $key, $value);
                    }
                }
                $result .= "</ul>\n";
            }
            return $result;
        }
        // debug('content_toc', $content_toc);
        if (empty($content_toc)) {
            $content = "<p>No content found</p>\n";
        } else {
            $content = get_content_toc($content_toc);
        }
        // debug('content', $content);
        $cache_path = MANUAL_CACHE_PATH.$manual_id.'/';
        $cache_filename = "toc.html";
        if (is_dir($cache_path) && is_writable($cache_path)) {
            file_put_contents($cache_path.$cache_filename, $content);
        }
    }

endif;

?>
<form method="post">
<input type="checkbox" name="force" value="yes" id="force_update" /> <label for="force_update">Force</label>
<input type="submit" value="&raquo;" />
</form>
<p>You can now <a href="index.php">view your blog</a>.</p>
</body>
</html>
