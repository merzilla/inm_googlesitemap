<?php
/***************************************************************
 *  Copyright notice
 *  (c) 2016 Ralf Merz <ralf.merz@inm.ch>, INM AG
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * @package SitemapCommandController.php
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */

namespace INM\InmGooglesitemap\Command;

use INM\InmGooglesitemap\Generators\SitemapGenerator;
use TYPO3\CMS\Extbase\Annotation\Inject;

class SitemapCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
{

    /**
     * objectManager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     * @Inject
     */
    protected $objectManager = null;

    /**
     * @param string $url The URL entry point for crawling.
     * @param string $sitemapFileName File name of the XML file. Default is "sitemap.xml".
     * @param string $regexFileEndings Regular expression for file endings to skip
     * @param string $regexDirectoryExclude Regular expression for directories to skip.
     * @param bool $obeyRobotsTxt Check to obey rules from robots.txt
     * @param int $requestLimit Max number of URLs to crawl.
     * @param boolean $countOnlyProcessed Check if only fetched URLs should count for $requestLimit.
     * @param int $phpTimeLimit Value in seconds for setting time limit. Default = 10000.
     * @param boolean $htmlSuffix Default true: will only allow .htm|.html endings. Will also exclude query strings
     * @param string $linkExtractionTags By default the crawler searches for links in the following html-tags: href, src, url, location, codebase, background, data, profile, action and open.
     * @param string $useTransferProtocol Enter transfer protocol to use: http (=default) or https. URLs with wrong protocol will not be written.
     * @param float $requestDelay time in seconds (float, e.g. 0.5 or 60/100 for 100 request per minute). Sets a delay for every HTTP-requests the crawler executes.
     * @param string $username HTTP Auth username
     * @param string $password HTTP Auth password
     * @param string $urlRegexHttpAuth URL to send authentication information to, e.g. "#http://www\.foo\.com/protected_path/#"
     */
    public function generateSitemapCommand(
            $url = 'http://example.com',
            $sitemapFileName = 'sitemap.xml',
            $regexFileEndings = "#\.(jpg|jpeg|gif|png|mp3|mp4|gz|ico)$# i",
            $regexDirectoryExclude = "#\/(typo3conf|fileadmin|uploads)\/.*$# i",
            $obeyRobotsTxt = false,
            $requestLimit = 0,
            $countOnlyProcessed = true,
            $phpTimeLimit = 10000,
            $htmlSuffix = true,
            $linkExtractionTags = 'href, src, url, location, codebase, background, data, profile, action, open',
            $useTransferProtocol = 'http',
            $requestDelay = 2.0,
            $username = '',
            $password = '',
            $urlRegexHttpAuth = ''
    ) {
        // It may take a whils to crawl a site ...
        set_time_limit($phpTimeLimit);

        /** @var \INM\InmGooglesitemap\Generators\SitemapGenerator $crawler */
        $crawler = $this->objectManager->get(SitemapGenerator::class);
        $crawler->setSitemapOutputFile($sitemapFileName); // Set output-file, but temporary, until created.
        $crawler->setURL($url);
        $crawler->setUseTransferProtocol(trim($useTransferProtocol));
        $crawler->setRequestDelay(floatval($requestDelay));
        $crawler->setUserAgentString('INM Google Sitemap Crawler');
        $crawler->addContentTypeReceiveRule("#text/html#");
        // exclude file endings for assets
        $crawler->addURLFilterRule($regexFileEndings);
        // exclude css and js which have unique timestamps, e.g. like "some.css?12345678"
        $crawler->addURLFilterRule("#(css|js).*$# i");

        // exclude urls with get (?) Parameter
        $crawler->addURLFilterRule("#\?# i");

        // exclude unnecessary directories
        $crawler->addURLFilterRule($regexDirectoryExclude);
        // only html files to crawl
        $test = (boolean)$htmlSuffix;
        if ((boolean)$htmlSuffix === true) {
            $crawler->addURLFollowRule("#(htm|html)$# i");
        }

        $crawler->obeyRobotsTxt($obeyRobotsTxt);

        // ... apply all other options and rules to the crawler

        // process $linkExtractionTags
        $linkExtractionTagsArray = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $linkExtractionTags, true);
        $crawler->setLinkExtractionTags($linkExtractionTagsArray);

        if (strlen($username) >= 2 && strlen($password) >= 2) {
            $crawler->addBasicAuthentication($urlRegexHttpAuth, $username, $password);
        }

        $crawler->setRequestLimit($requestLimit, $countOnlyProcessed); // Just for testing
        //$crawler->goMultiProcessed(5); // Or use go() if you don't want multiple processes
        $crawler->go();
        $crawler->closeFile();

        // now that we are finished, hopefully, put the file into right place / name
        $crawler->publishFile();
    }
}