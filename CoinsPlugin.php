<?php

/**
 * @file CoinsPlugin.php
 *
 * Copyright (c) 2013-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class CoinsPlugin
 * @brief COinS plugin class
 */

namespace APP\plugins\generic\coins;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\config\Config;
use APP\core\Application;
use APP\template\TemplateManager;

class CoinsPlugin extends GenericPlugin {
    /**
     * Called as a plugin is registered to the registry
     * @param $category String Name of category plugin was registered to
     * @return boolean True iff plugin initialized successfully; if false,
     *     the plugin will not be registered.
     */
    function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
        if ($success && $this->getEnabled()) {
            Hook::add('Templates::Common::Footer::PageFooter', [$this, 'insertFooter']);
        }
        return $success;
    }

    /**
     * Get the display name of this plugin
     * @return string
     */
    function getDisplayName() {
        return __('plugins.generic.coins.displayName');
    }

    /**
     * Get the description of this plugin
     * @return string
     */
    function getDescription() {
        return __('plugins.generic.coins.description');
    }

    /**
     * Insert COinS tag.
     */
    function insertFooter(string $hookName, array $params) : bool
    {
        if (!$this->getEnabled()) return false;
        $request = Application::get()->getRequest();

        // Ensure that the callback is being called from a page COinS should be embedded in.
        if (!in_array($request->getRequestedPage() . '/' . $request->getRequestedOp(), [
            'article/view',
        ])) return false;

        $smarty =& $params[1];
        $output =& $params[2];
        $templateMgr = TemplateManager::getManager($request);

        $article = $templateMgr->getTemplateVars('article');
        $journal = $templateMgr->getTemplateVars('currentJournal');
        $issue = $templateMgr->getTemplateVars('issue');
        $publication = $article->getCurrentPublication();

        $vars = [
            ['ctx_ver', 'Z39.88-2004'],
            ['rft_id', $request->url(null, 'article', 'view', $article->getId())],
            ['rft_val_fmt', 'info:ofi/fmt:kev:mtx:journal'],
            ['rft.language', $article->getLocale()],
            ['rft.genre', 'article'],
            ['rft.title', $journal->getLocalizedName()],
            ['rft.jtitle', $journal->getLocalizedName()],
            ['rft.atitle', $article->getFullTitle($article->getLocale())],
            ['rft.artnum', $article->getBestArticleId()],
            ['rft.stitle', $journal->getLocalizedSetting('abbreviation')],
            ['rft.volume', $issue->getVolume()],
            ['rft.issue', $issue->getNumber()],
        ];

        $authors = $publication->getData('authors');
        if ($firstAuthor = $authors->first()) {
            $vars = array_merge($vars, [
                ['rft.aulast', $firstAuthor->getFamilyName($article->getLocale())],
                ['rft.aufirst', $firstAuthor->getGivenName($article->getLocale())],
            ]);
        }

        $datePublished = $article->getDatePublished();
        if (!$datePublished) {
            $datePublished = $issue->getDatePublished();
        }

        if ($datePublished) {
            $vars[] = ['rft.date', date('Y-m-d', strtotime($datePublished))];
        }

        foreach ($authors as $author) {
            $vars[] = ['rft.au', $author->getFullName()];
        }

        if ($doi = $article->getStoredPubId('doi')) {
            $vars[] = ['rft_id', 'info:doi/' . $doi];
        }
        if ($article->getPages()) {
            $vars[] = ['rft.pages', $article->getPages()];
        }
        if ($journal->getSetting('printIssn')) {
            $vars[] = ['rft.issn', $journal->getSetting('printIssn')];
        }
        if ($journal->getSetting('onlineIssn')) {
            $vars[] = ['rft.eissn', $journal->getSetting('onlineIssn')];
        }

        $title = '';
        foreach ($vars as $entries) {
            list($name, $value) = $entries;
            $title .= $name . '=' . urlencode($value) . '&';
        }
        $title = htmlentities(substr($title, 0, -1));

	$output .= "<span class=\"Z3988\" title=\"$title\"></span>\n";

	return false;
    }
}

