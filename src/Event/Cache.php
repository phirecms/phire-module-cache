<?php
/**
 * Phire Cache Module
 *
 * @link       https://github.com/phirecms/phire-cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.phirecms.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Phire\Cache\Event;

use Phire\Cache\Model;
use Pop\Application;

/**
 * Cache Event class
 *
 * @category   Phire\Cache
 * @package    Phire\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.phirecms.org/license     New BSD License
 * @version    1.0.0
 */
class Cache
{

    /**
     * Load content from cache
     *
     * @param  Application $application
     * @return void
     */
    public static function load(Application $application)
    {
        if (($application->router()->getController() instanceof \Phire\Content\Controller\IndexController) &&
            empty($_SERVER['QUERY_STRING']) && (!$_POST)) {
            $sess  = $application->services()->get('session');
            $uri   = $application->router()->getController()->request()->getRequestUri();
            $cache = (new Model\Cache())->getCacheAdapter();

            if ((null !== $cache) && !isset($sess->user)) {
                if ($cache->load($uri) !== false) {
                    $content = $cache->load($uri);
                    $application->router()->getController()->response()->setHeader(
                        'Cache-Control', 'max-age=' . $cache->getLifetime($uri) . ', public, must-revalidate'
                    );
                    $application->router()->getController()->response()->setHeader(
                        'Last-Modified', date('D, j M Y H:i:s T', $cache->getStart($uri))
                    );
                    $application->router()->getController()->response()->setHeader(
                        'Expires', date('D, j M Y H:i:s T', $cache->getExpiration($uri))
                    );
                    $application->router()->getController()->response()->setHeader(
                        'Etag', '"' . sha1($uri . '-' . $cache->getStart($uri)) . '"'
                    );
                    $application->router()->getController()->response()->setBody($content['body']);
                    $application->router()->getController()->send(200, ['Content-Type' => $content['content-type']]);
                    exit();
                }
            }
        }
    }

    /**
     * Save content to cache
     *
     * @param  Application $application
     * @return void
     */
    public static function save(Application $application)
    {
        if (($application->router()->getController() instanceof \Phire\Content\Controller\IndexController) &&
            ($application->router()->getController()->response()->getCode() == 200) &&
            empty($_SERVER['QUERY_STRING']) && (!$_POST)) {

            $sess    = $application->services()->get('session');
            $uri     = $application->router()->getController()->request()->getRequestUri();
            $cache   = (new Model\Cache())->getCacheAdapter();
            $exclude = $application->module('phire-cache')['exclude'];

            if ((null !== $cache) && !isset($sess->user) && !in_array($uri, $exclude)) {
                $contentType = $application->router()->getController()->response()->getHeader('Content-Type');
                $body        = $application->router()->getController()->response()->getBody();

                if ($contentType == 'text/html') {
                    $body .= PHP_EOL . PHP_EOL .
                        '<!-- Generated by the phire-cache module on ' . date('M j, Y H:i:s') . '. //-->' .
                        PHP_EOL . PHP_EOL;
                } else if (stripos($contentType, 'xml') !== false) {
                    $body .= PHP_EOL . PHP_EOL .
                        '<!-- Generated by the phire-cache module on ' . date('M j, Y H:i:s') . '. -->' .
                        PHP_EOL . PHP_EOL;
                }

                $cache->save($uri, [
                    'content-type' => $contentType,
                    'body'         => $body
                ]);
            }
        }
    }

}
