<?php

namespace MatDave\MediumImport;

use voku\helper\HtmlDomParser;

class Import {
    private $modx;
    private $log = false;
    private $print = false;

    private $onSave;
    private $onBeforeSave;

    public function __construct($corePath, $configKey = 'config')
    {
       // check if corePath exists
        if (!file_exists($corePath)) {
            throw new \Exception('Could not find MODX core path: '.$corePath);
        }

        $this->loadMODX($corePath, $configKey);
    }

    public function enableLogging()
    {
        $this->log = true;
    }

    public function enablePrinting()
    {
        $this->print = true;
    }

    public function callbackOnSave(callable $callback)
    {
        $this->onSave = $callback;
    }

    public function callbackBeforeSave(callable $callback)
    {
        $this->onBeforeSave = $callback;
    }

    private function loadMODX($corePath, $configKey)
    {
        $tstart = microtime(true);
        if (!defined('MODX_CORE_PATH')) {
            define('MODX_CORE_PATH', $corePath);
        }
        if (!defined('MODX_CONFIG_KEY')) {
            define('MODX_CONFIG_KEY', $configKey);
        }
        if (!@include_once (MODX_CORE_PATH . "model/modx/modx.class.php")) {
            throw new \Exception('Could not load MODX class');
        }
        ob_start();
        $this->modx = new \modX();
        if (!is_object($this->modx)) {
            throw new \Exception('Could not create MODX object');
        }
        $this->modx->startTime = $tstart;
        $this->modx->initialize();
    }

    public function import($exportPath, $templateId = 0, $parentId = 0, $overwrite = false) {
        if (!file_exists($exportPath)) {
            throw new \Exception('Could not find export folder: '.$exportPath);
        }
        $postsFolder = rtrim($exportPath, '/').'/posts';
        if (!file_exists($postsFolder)) {
            throw new \Exception('Could not find posts folder: '.$postsFolder);
        }
        $posts = glob($postsFolder.'/*.html');
        if (empty($posts)) {
            throw new \Exception('Could not find any posts in folder: '.$postsFolder);
        }
        $parent = $this->modx->getObject('modResource', $parentId);
        if (!$parent) {
            throw new \Exception('Could not find parent resource: '.$parentId);
        }
        foreach ($posts as $post) {
            $alias = basename($post, '.html');
            $checkResource = $this->modx->getObject('modResource', ['alias' => $alias, 'parent' => $parentId, 'template' => $templateId]);
            if ($checkResource && !$overwrite) {
                if ($this->log) {
                    $this->modx->log(1, 'Found existing post: '.$alias);
                }
                if ($this->print) {
                    print "Found existing post: " . $alias . "\n";
                }
                continue;
            }
            if ($checkResource && $overwrite) {
                if ($this->log) {
                    $this->modx->log(1, 'Overwriting post: '.$alias);
                }
                if ($this->print) {
                    print "Overwriting post: " . $alias . "\n";
                }
                $resource = $checkResource;
            } else {
                $resource = $this->modx->newObject('modResource');
            }
            $postHtml = HtmlDomParser::file_get_html($post);
            $pagetitle = $postHtml->find('title', 0)->plaintext;
            $longtitle = $postHtml->find('section[data-field="summary"]', 0)->plaintext;
            $content = $postHtml->find('section[data-field="body"]', 0)->innertext;
            $dtPublished = $postHtml->find('time.dt-published', 0);
            $publishedon = 0;
            if ($dtPublished && $dtPublished->hasAttribute('datetime')) {
                $publishedon = strtotime($dtPublished->getAttribute('datetime'));
            }
            $published = $publishedon > 0;
            $createdon = time();
            $resource->fromArray([
                'pagetitle' => $pagetitle,
                'longtitle' => $longtitle,
                'description' => $longtitle,
                'content' => $content,
                'published' => $published,
                'createdon' => $createdon,
                'createdby' => 1,
                'editedon' => $createdon,
                'alias' => $alias,
                'parent' => $parentId,
                'template' => $templateId,
                'publishedon' => $publishedon,
                'context_key' => $parent->get('context_key'),
            ]);
            if ($this->onBeforeSave) {
                $onBeforeSave = call_user_func($this->onBeforeSave, $resource, $post);
                if ($onBeforeSave === false) {
                    if ($this->log) {
                        $this->modx->log(1, 'OnBeforeSave failed for post: '.$alias);
                    }
                    if ($this->print) {
                        print "OnBeforeSave failed for post: " . $alias . "\n";
                    }
                    continue;
                }
            }
            if ($resource->save()) {
                if ($this->onSave) {
                    call_user_func($this->onSave, $resource, $post);
                }
                if ($this->log) {
                    $this->modx->log(1, 'Imported post: '.$alias);
                }
                if ($this->print) {
                    print "Imported post: " . $alias . "\n";
                }
            } else {
                if ($this->log) {
                    $this->modx->log(1, 'Failed to import post: ' . $alias);
                }
                if ($this->print) {
                    print "Failed to import post: " . $alias . "\n";
                }
            }
        }
    }
}