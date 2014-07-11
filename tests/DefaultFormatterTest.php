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

namespace Phergie\Irc\Tests\Plugin\React\FeedTicker;

use Phake;
use Phergie\Irc\Plugin\React\FeedTicker\DefaultFormatter;
use Zend\Feed\Reader\Entry\EntryInterface;

/**
 * Tests for the DefaultFormatter class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */
class DefaultFormatterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Instance of the class under test
     *
     * @var \Phergie\Irc\Plugin\React\FeedTicker\DefaultFormatter
     */
    protected $formatter;

    /**
     * Data provider for testFormat().
     *
     * @return array
     */
    public function dataProviderFormat()
    {
        $data = array();

        // Default
        $data[] = array(null, 'Title [ http://feed/link ] by Author at 2014-07-10T19:56:01-0500');

        // Individual attributes
        $data[] = array('%authorname%', 'Author');
        $data[] = array('%authoremail%', 'author@example.com');
        $data[] = array('%authoruri%', 'http://author.com');
        $data[] = array('%content%', 'content');
        $data[] = array('%datecreated%', '2014-07-10T19:56:00-0500');
        $data[] = array('%datemodified%', '2014-07-10T19:56:01-0500');
        $data[] = array('%description%', 'description');
        $data[] = array('%id%', '1');
        $data[] = array('%link%', 'http://feed/link');
        $data[] = array('%links%', 'http://feed/link1 http://feed/link2');
        $data[] = array('%permalink%', 'http://feed/permalink');
        $data[] = array('%title%', 'Title');
        $data[] = array('%commentcount%', '2');
        $data[] = array('%commentlink%', 'http://feed/link/comments');
        $data[] = array('%commentfeedlink%', 'http://feed/comments');

        // Multiple attributes
        $data[] = array('%title% - %authorname%', 'Title - Author');

        return $data;
    }

    /**
     * Tests format() with timestamps as \DateTime instances and author data
     * present.
     *
     * @param string $pattern
     * @param string $expected
     * @dataProvider dataProviderFormat
     */
    public function testFormat($pattern, $expected)
    {
        $item = $this->getItem();
        $formatter = new DefaultFormatter($pattern);
        $this->assertSame($expected, $formatter->format($item));
    }

    /**
     * Tests format() with timestamps as strings.
     */
    public function testFormatWithTimestampStrings()
    {
        $item = $this->getItem();
        Phake::when($item)->getDateCreated()->thenReturn('2014-07-10T19:56:00-0500');
        Phake::when($item)->getDateModified()->thenReturn('2014-07-10T19:56:01-0500');

        $formatter = new DefaultFormatter('%datecreated%');
        $this->assertSame('2014-07-10T19:56:00-0500', $formatter->format($item));

        $formatter = new DefaultFormatter('%datemodified%');
        $this->assertSame('2014-07-10T19:56:01-0500', $formatter->format($item));
    }

    /**
     * Test format() without author info.
     */
    public function testFormatWithoutAuthorInfo()
    {
        $item = $this->getItem();
        Phake::when($item)->getAuthor()->thenReturn('');

        $formatter = new DefaultFormatter('%authorname%%authoremail%%authoruri%');
        $this->assertSame('', $formatter->format($item));
    }

    /**
     * Returns a mock feed item.
     *
     * @return
     */
    protected function getItem()
    {
        $item = Phake::mock('\Zend\Feed\Reader\Entry\EntryInterface');
        Phake::when($item)->getDateCreated()->thenReturn(new \DateTime('2014-07-10T19:56:00-0500'));
        Phake::when($item)->getDateModified()->thenReturn(new \DateTime('2014-07-10T19:56:01-0500'));
        Phake::when($item)->getAuthor()->thenReturn(array(
            'name' => 'Author',
            'email' => 'author@example.com',
            'uri' => 'http://author.com',
        ));
        Phake::when($item)->getContent()->thenReturn('content');
        Phake::when($item)->getDescription()->thenReturn('description');
        Phake::when($item)->getId()->thenReturn('1');
        Phake::when($item)->getLink()->thenReturn('http://feed/link');
        Phake::when($item)->getLinks()->thenReturn(array('http://feed/link1', 'http://feed/link2'));
        Phake::when($item)->getPermalink()->thenReturn('http://feed/permalink');
        Phake::when($item)->getTitle()->thenReturn('Title');
        Phake::when($item)->getCommentCount()->thenReturn('2');
        Phake::when($item)->getCommentLink()->thenReturn('http://feed/link/comments');
        Phake::when($item)->getCommentFeedLink()->thenReturn('http://feed/comments');
        return $item;
    }
}
