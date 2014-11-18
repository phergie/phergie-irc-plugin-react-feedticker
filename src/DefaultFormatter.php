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
     * Pattern used to format date values within feed items
     *
     * @var string
     */
    protected $datePattern;

    /**
     * Default pattern used to format feed items if none is provided via
     * configuration
     *
     * @var string
     */
    protected $defaultPattern = '%title% [ %link% ] by %authorname% at %datemodified%';

    /**
     * Accepts format pattern.
     *
     * @param string $pattern
     * @param string $datePattern
     */
    public function __construct($pattern = null, $datePattern = null)
    {
        $this->pattern = $pattern ? $pattern : $this->defaultPattern;
        $this->datePattern = $datePattern ? $datePattern : \DateTime::ISO8601;
    }

    /**
     * Implements FormatterInterface->format().
     *
     * @param \Zend\Feed\Reader\Entry\EntryInterface $item
     * @return string
     */
    public function format(EntryInterface $item)
    {
        $created = $item->getDateCreated();
        if ($created instanceof \DateTime) {
            $created = $created->format($this->datePattern);
        }

        $modified = $item->getDateModified();
        if ($modified instanceof \DateTime) {
            $modified = $modified->format($this->datePattern);
        }

        $author = $item->getAuthor();
        if (is_array($author)) {
            $authorname = $author['name'];
            $authoremail = isset($author['email']) ? $author['email'] : null;
            $authoruri = isset($author['uri']) ? $author['uri'] : null;
        } else {
            $authorname = '';
            $authoremail = '';
            $authoruri = '';
        }

        $replacements = array(
            '%authorname%' => $authorname,
            '%authoremail%' => $authoremail,
            '%authoruri%' => $authoruri,
            '%content%' => $item->getContent(),
            '%datecreated%' => $created,
            '%datemodified%' => $modified,
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
