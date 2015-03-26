<?php
/**
 * Phergie plugin for syndicating data from feed items to channels or users
 * (https://github.com/phergie/phergie-irc-plugin-react-feedticker)
 *
 * @link https://github.com/phergie/phergie-irc-plugin-react-feedticker for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */

namespace Phergie\Irc\Plugin\React\FeedTicker;

use Zend\Feed\Reader\Entry\EntryInterface;

/**
 * Interface for objects used to format data from feed items prior to
 * syndication.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */
interface FormatterInterface
{
    /**
     * Formats data from an individual feed item for syndication.
     *
     * @param \Zend\Feed\Reader\Entry\EntryInterface $item Feed item to format
     * @return string Formatted feed item
     */
    public function format(EntryInterface $item);
}
