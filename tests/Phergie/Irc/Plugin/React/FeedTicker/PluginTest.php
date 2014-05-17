<?php
/**
 * Phergie plugin for syndicating data from feed items to channels or users (https://github.com/phergie/phergie-irc-plugin-react-feedticker)
 *
 * @link https://github.com/phergie/phergie-irc-plugin-react-feedticker for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */

namespace Phergie\Irc\Plugin\React\FeedTicker;

use Phake;
use Phergie\Irc\Event\EventInterface as Event;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;

/**
 * Tests for the Plugin class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{


    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $plugin = new Plugin;
        $this->assertInternalType('array', $plugin->getSubscribedEvents());
    }
}
