<?php
// phpinfo();
// debug('server', $_SERVER);
// debug('__FILE__', __FILE__);

// the path accessed by the readers. if nothing has been defined, the parent directory
if (!defined('MANUAL_BASE_PATH')) {
    define('MANUAL_BASE_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');
}
// debug('MANUAL_BASE_PATH', MANUAL_BASE_PATH);

define('MANUAL_CONFIG_FILE', MANUAL_BASE_PATH.'config.json');

define('MANUAL_CACHE_PATH', MANUAL_BASE_PATH.'cache/');
define('MANUAL_CACHE_FILE', 'cache.json');
define('MANUAL_CACHE_TOC_HTML_FILE', 'toc_html.json');
define('MANUAL_CACHE_SECTION_FILE', 'section.json');

define('MANUAL_CACHE_GITHUB_FILE', 'cache.json');
define('MANUAL_SOURCE_BOOK_FILE', 'book.yaml');
define('MANUAL_CACHE_TOC_FILE', 'toc.json');
define('MANUAL_CACHE_LANGUAGE_FILE', 'language.json');

define('MANUAL_CONTENT_PATH', MANUAL_BASE_PATH.'/content.json'); // TODO: ???
define('MANUAL_LIST_PATH', MANUAL_BASE_PATH.'list.json'); // TODO: ????


define('MANUAL_HTTP_URL', sprintf('http://%s%s', $_SERVER['SERVER_NAME'], pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME)));
define('MANUAL_HTTP_UPDATE_URL', MANUAL_HTTP_URL.'update.php');
