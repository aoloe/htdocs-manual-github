#htdocs-manual-github

Publish a manual written in markdown and hosted on GitHub

# Limitations

- (for now) can only show one level of directories and a specific schema based on chapter / section.


# Specification

The content is stored in a flat structure of directory:
- Each "section" has its own directory containing all the files it uses (text, images, ...).
- Each section is named by the structure leading to it, separated by a dash. 

manage the cache:
- add the new files
- update the existing ones
- delete the deprecated ones
- clear all

the `cache.json` should allow to:
- while updating:
  - check if a directory from github is new.
  - check if a directory from github has not been updated and ignore its substree. (not sure if this is useful)
  - check if a file from github is new.
  - check if a file from github has been updated.
  - check if a file has been removed from github and is still in the cache.
- while displaying:
  - fast check if the path requested does exist.

the github API is returning a flat list with the following structure:
    [i] => Array
        (
            [mode] => 100644
            [type] => [blob|tree]
            [sha] => "12929114085b3bb86e5e48d9a984f28fd382c129"
            [path] => README.md
            [size] => 26
            [url] => https://api.github.com/repos/aoloe/libregraphics-manual-libregraphics_for_ONGs/git/blobs/12929114085b3bb86e5e48d9a984f28fd382c129
        )

`chache.json`'s characteristics:
- an associative array, using the file path as the key for fast comparing with the GitHub API call.
- an associative array, using the file path as the key for fast retrieving the file for display.
- to avoid big file loading for each file viewed, we need a `cache.json` per manual.
- the data structure is:
      [path] => Array
          (
              [sha] => "12929114085b3bb86e5e48d9a984f28fd382c129"
              [path] => README.md
          )
- the `cache.json` is only use to compare the sha and check if a file must be updated and contains the list of files in the repository.

`book.yaml`:

- compiled (for now by the user).
- contains some basic information on each chapter/section
- structure
      title:
          - en: Libre Graphics for ONGs 
          - fr: Graphisme libre pour  NGOs 
      toc:
          - directory: introduction
            level: 1
            title:
              fr: Introduction
          - directory: introduction-requirements
            parent: introduction
            level: 2
            published : false
            title:
              fr: Les pré-requis
              de: Voraussetzungen
            status:
              fr: à relire
  - if _directory_ is an empty string or does not exist, the article will not be published and won't show up in the toc.
  - if _parent_ is not defined, it's a _root_ chapter.
  - if _published_ is not defined, it's published.
  - _published_ may be a boolean or a list of languages and booleans (in the second case, languages that are omitted from the list are published)
  - _level_ is not mandatory and is not used, but may help better understand the structure.
  - the languages defined for the book title, define which languages can be used for the chapters
  - _status_ is a free text field for short notes


compiling the TOC:
- producing a `toc.json` that will be used to render the TOC in `index.php`
- retains all the items in toc.yaml, but marks 

#TODO

- add errors from `ensure_file_writable()`, `file_put_cache_json()` and `file_get_cache_json()` in a log.
- give a warning if a [lang] => title is published but there is no corresponding file.
  - it's probably better to write a script that just checks different things about the toc entries and the files:
    - toc entries with children but no text.
    - toc entries with no children nor text.
    - files that are in git but in the toc.
- check that hash are checked and that only the files to be updated are downloaded.
- optionally also show the other languages in the toc
- remove from the cache the files that do not exist anymore in the github repository
- make an interface that can create (install) and edit the config.js of the site
- how to choose the current language?
- define how to bind the images into the `book.yaml`
  - when parsing the markdown, pick the images used, and add them to the files to be downloaded (probably in a second list (`resources_github`) so that they don't get downloaded twice).
- create a php application that provides the same API as github and delivers files in the same way per http.
