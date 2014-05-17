<?php
/**
 * Phergie plugin for syndicating data from feed items to channels or users
 * (https://github.com/phergie/phergie-irc-plugin-react-feedticker)
 *
 * @link https://github.com/phergie/phergie-irc-plugin-react-feedticker for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */

namespace Phergie\Irc\Plugin\React\FeedTicker;

use Zend\Feed\Reader\Entry\EntryInterface;

/**
 * Default feed item formatter implementation.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */
class DefaultFormatter implements FormatterInterface
{
    /** 
     * Pattern used to format feed items
     *
     * @var string
     */
    protected $pattern;

    /**
     * Default pattern used to format feed items if none is provided via
     * configuration
     *
     * @var string
     */
    protected $defaultPattern = '%title% [ %link% ] by %author% at %datemodified%';

    /**
     * Accepts format pattern.
     *
     * @param string $pattern
     */
    public function __construct($pattern = null)
    {
        $this->pattern = $pattern ? $pattern : $this->defaultPattern;
    }

    /**
     * Implements FormatterInterface->format().
     *
     * @param \Zend\Feed\Reader\Entry\EntryInterface $item
     * @return string
     */
    public function format(EntryInterface $item)
    {
        $replacements = array(
            '%author%' => $item->getAuthor(),
            '%authors%' => implode(', ', $item->getAuthors()),
            '%content%' => $item->getContent(),
            '%datecreated%' => $item->getDateCreated(),
            '%datemodified%' => $item->getDateModified(),
            '%description%' => $item->getDescription(),
            '%id%' => $item->getId(),
            '%link%' => $item->getLink(),
            '%links%' => implode(' ', $item->getLinks()),
            '%permalink%' => $item->getPermalink(),
            '%title%' => $item->getTitle(),
            '%commentcount%' => $item->getCommentCount(),
            '%commentlink%' => $item->getCommentLink(),
            '%commentfeedlink%' => $item->getCommentFeedLink(),
        );

        $formatted = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $this->pattern
        );

        return $formatted;
    }
}
