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

namespace Phergie\Irc\Tests\Plugin\React\FeedTicker;

use Phake;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Event\EventInterface as Event;
use Phergie\Irc\Plugin\React\FeedTicker\FormatterInterface;
use Phergie\Irc\Plugin\React\FeedTicker\Plugin;
use Zend\Feed\Reader\Entry\EntryInterface;
use Zend\Feed\Writer\Feed;

/**
 * Tests for the Plugin class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Instance of the class under test with valid configuration
     *
     * @var \Phergie\Irc\Plugin\React\FeedTicker\Plugin
     */
    protected $plugin;

    /**
     * Mock event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Mock event
     *
     * @var \Phergie\Irc\Event\EventInterface
     */
    protected $event;

    /**
     * Mock event queue
     *
     * @var \Phergie\Irc\Bot\React\EventQueueInterface
     */
    protected $queue;

    /**
     * Mock logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Mock event emitter
     *
     * @var \Evenement\EventEmitterInterface
     */
    protected $emitter;

    /**
     * Test feed URL
     *
     * @var string
     */
    protected $url = 'http://google.com';

    /**
     * Test feed data
     *
     * @var string
     */
    protected $data;

    /**
     * Last identifier assigned to a new feed item
     *
     * @var int
     */
    protected $id;

    /**
     * Instantiates the class under test with valid configuration.
     */
    protected function setUp()
    {
        $this->plugin = new Plugin(array(
            'urls' => array($this->url),
            'targets' => array('nick!user@host' => array('#channel')),
        ));
        $this->loop = $this->getMockLoop();
        $this->event = $this->getMockEvent();
        $this->queue = $this->getMockQueue();
        $this->plugin->setLogger($this->logger = $this->getMockLogger());
        $this->plugin->setEventEmitter($this->emitter = $this->getEventEmitter());
        $this->id = 0;
    }

    /**
     * Data provider for testConstructWithInvalidConfiguration().
     *
     * @return array
     */
    public function dataProviderConstructWithInvalidConfiguration()
    {
        $data = array();
        $config = array();

        // 'urls' is not set
        $data[] = array($config, Plugin::ERR_URLS_INVALID);

        // 'urls' is not an array
        $config['urls'] = 'foo';
        $data[] = array($config, Plugin::ERR_URLS_INVALID);

        // 'urls' is an array containing a non-URL value
        $config['urls'] = array(1);
        $data[] = array($config, Plugin::ERR_URLS_INVALID);

        // 'targets' is not set
        $config['urls'] = array('http://google.com');
        $data[] = array($config, Plugin::ERR_TARGETS_INVALID);

        // 'targets' is not an array
        $config['targets'] = 'foo';
        $data[] = array($config, Plugin::ERR_TARGETS_INVALID);

        // 'interval' is not an integer
        $config['targets'] = array('nick!user@host' => array('#channel'));
        $config['interval'] = 3.14;
        $data[] = array($config, Plugin::ERR_INTERVAL_INVALID);

        // 'interval' is not a positive integer
        $config['interval'] = -1;
        $data[] = array($config, Plugin::ERR_INTERVAL_INVALID);

        // 'formatter' is not an object implementing FormatterInterface
        $config['interval'] = 1;
        $config['formatter'] = new \stdClass;
        $data[] = array($config, Plugin::ERR_FORMATTER_INVALID);

        return $data;
    }

    /**
     * Tests the constructor with invalid configuration.
     *
     * @param array $config
     * @param int $code
     * @dataProvider dataProviderConstructWithInvalidConfiguration
     */
    public function testConstructWithInvalidConfiguration(array $config, $code)
    {
        try {
            $plugin = new Plugin($config);
            $this->fail('Expected exception was not thrown');
        } catch (\DomainException $e) {
            $this->assertSame($code, $e->getCode());
        }
    }

    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $this->assertInternalType('array', $this->plugin->getSubscribedEvents());
    }

    /**
     * Tests a successful feed poll with no new items.
     */
    public function testSuccessfulFeedPollWithNoNewItems()
    {
        // Simulate the Http plugin response for the first poll
        $feed = $this->getFeed();
        $feedString = $this->getFeedString($feed);
        $this->emitter->on('http.request', function($request) use ($feedString) {
            $request->callResolve($feedString, array(), 200);
        });

        // Invoke the first poll
        $this->plugin->setLoop($this->loop);
        $this->plugin->setEventQueue($this->event, $this->queue);
        Phake::inOrder(
            Phake::verify($this->logger)->info('Sending request for feed URL', array('url' => $this->url)),
            Phake::verify($this->logger)->info('Processing feed', array('url' => $this->url)),
            Phake::verify($this->logger)->debug('Received feed data', array('data' => $feedString))
        );

        // Verify the next poll is queued
        Phake::verify($this->loop)->addTimer(300, Phake::capture($callback));

        // Invoke the next poll
        $callback();

        // Verify no items are sent because the feed hasn't been updated
        Phake::verify($this->queue, Phake::times(0))->ircPrivmsg(Phake::anyParameters());
    }

    /**
     * Tests a successful feed poll with a new item.
     */
    public function testSuccessfulFeedPollWithANewItem()
    {
        // Simulate the Http plugin response for the first poll
        $feed = $this->getFeed();
        $feedString = $this->getFeedString($feed);
        $this->emitter->once('http.request', function($request) use ($feedString) {
            $request->callResolve($feedString, array(), 200);
        });

        // Invoke the first poll
        $this->plugin->setLoop($this->loop);
        $this->plugin->setEventQueue($this->event, $this->queue);

        // Verify the next poll is queued
        Phake::verify($this->loop)->addTimer(300, Phake::capture($callback));

        // Simulate the Http plugin response for the next poll
        $this->addFeedEntry($feed);
        $feedString = $this->getFeedString($feed);
        $this->emitter->once('http.request', function($request) use ($feedString) {
            $request->callResolve($feedString, array(), 200);
        });

        // Invoke the next poll
        $callback();

        // Verify that the new item is sent
        Phake::verify($this->queue, Phake::times(1))->ircPrivmsg(
            '#channel',
            'Title 2 [ http://localhost/feed.xml?id=2 ] by Name 2 at 2014-07-09T20:54:01+0000'
        );
    }

    /**
     * Tests a failed feed poll.
     */
    public function testFailedFeedPoll()
    {
        // Simulate the Http plugin response for the failed poll
        $this->emitter->once('http.request', function($request) {
            $request->callReject('error message');
        });

        // Invoke the poll
        $this->plugin->setLoop($this->loop);
        $this->plugin->setEventQueue($this->event, $this->queue);

        // Verify that the failure is logged
        Phake::verify($this->logger)->warning(
            'Failed to poll feed',
            array(
                'url' => $this->url,
                'error' => 'error message',
            )
        );

        // Verify the next poll is queued
        Phake::verify($this->loop)->addTimer(300, $this->isType('callable'));
    }

    /**
     * Tests a successful poll for a malformed feed.
     */
    public function testSuccessfulPollWithMalformedFeed()
    {
        // Simulate the Http plugin response for the poll
        $feedString = '<foo>test<</foo';
        $this->emitter->once('http.request', function($request) use ($feedString) {
            $request->callResolve($feedString, array(), 200);
        });

        // Invoke the poll
        $this->plugin->setLoop($this->loop);
        $this->plugin->setEventQueue($this->event, $this->queue);

        // Verify that the failure is logged
        Phake::verify($this->logger)->warning('Failed to process feed', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertArrayHasKey('url', $params);
        $this->assertSame($this->url, $params['url']);
        $this->assertArrayHasKey('data', $params);
        $this->assertSame($feedString, $params['data']);
        $this->assertArrayHasKey('error', $params);
        $this->assertInstanceOf('\Exception', $params['error']);

        // Verify the next poll is queued
        Phake::verify($this->loop)->addTimer(300, $this->isType('callable'));
    }

    /**
     * Tests a successful poll with a custom formatter.
     */
    public function testSuccessfulPollWithCustomFormatter()
    {
        // Configure plugin with formatter
        $this->plugin = new Plugin(array(
            'urls' => array($this->url),
            'targets' => array('nick!user@host' => array('#channel')),
            'formatter' => new TestFormatter,
        ));
        $this->plugin->setLogger($this->logger);
        $this->plugin->setEventEmitter($this->emitter);

        // Simulate the Http plugin response for the first poll
        $feed = $this->getFeed();
        $feedString = $this->getFeedString($feed);
        $this->emitter->once('http.request', function($request) use ($feedString) {
            $request->callResolve($feedString, array(), 200);
        });

        // Invoke the first poll
        $this->plugin->setLoop($this->loop);
        $this->plugin->setEventQueue($this->event, $this->queue);

        // Verify the next poll is queued
        Phake::verify($this->loop)->addTimer(300, Phake::capture($callback));

        // Simulate the Http plugin response for the next poll
        $this->addFeedEntry($feed);
        $feedString = $this->getFeedString($feed);
        $this->emitter->once('http.request', function($request) use ($feedString) {
            $request->callResolve($feedString, array(), 200);
        });

        // Invoke the next poll
        $callback();

        // Verify that the new item is sent using the custom formatter
        Phake::verify($this->queue, Phake::times(1))->ircPrivmsg('#channel', 'Title 2');
    }

    /**
     * Returns a mock event loop.
     *
     * @return \React\EventLoop\LoopInterface
     */
    protected function getMockLoop()
    {
        return Phake::mock('\React\EventLoop\LoopInterface');
    }

    /**
     * Returns a mock event queue.
     *
     * @return \Phergie\Irc\Bot\React\EventQueueInterface
     */
    protected function getMockQueue()
    {
        return Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
    }

    /**
     * Returns a mock event.
     *
     * @return \Phergie\Irc\Event\EventInterface
     */
    protected function getMockEvent()
    {
        $event = Phake::mock('\Phergie\Irc\Event\EventInterface');
        Phake::when($event)->getConnection()->thenReturn($this->getMockConnection());
        return $event;
    }

    /**
     * Returns a mock connection.
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnection()
    {
        $connection = Phake::mock('\Phergie\Irc\ConnectionInterface');
        Phake::when($connection)->getNickname()->thenReturn('nick');
        Phake::when($connection)->getUsername()->thenReturn('user');
        Phake::when($connection)->getServerHostname()->thenReturn('host');
        return $connection;
    }

    /**
     * Returns a mock logger.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getMockLogger()
    {
        return Phake::mock('\Psr\Log\LoggerInterface');
    }

    /**
     * Returns an event emitter.
     *
     * @return \Evenement\EventEmitterInterface
     */
    protected function getEventEmitter()
    {
        return new \Evenement\EventEmitter;
    }

    /**
     * Returns a data object for a feed.
     *
     * @return \Zend\Feed\Writer\Feed
     */
    protected function getFeed()
    {
        $feed = new Feed;
        $feed->setTitle('Title');
        $feed->setDateModified(time());
        $feed->setFeedLink('http://localhost/feed.xml', 'atom');
        $this->addFeedEntry($feed);
        return $feed;
    }

    /**
     * Converts a feed data object to a string.
     *
     * @param \Zend\Feed\Writer\Feed $feed
     * @return string
     */
    protected function getFeedString(Feed $feed)
    {
        return $feed->export('atom', true);
    }

    /**
     * Adds an entry to a feed data object.
     *
     * @param \Zend\Feed\Writer\Feed $feed
     * @return \Zend\Feed\Writer\Entry
     */
    protected function addFeedEntry(Feed $feed)
    {
        $entry = $feed->createEntry();
        $id = ++$this->id;
        $datetime = new \DateTime('2014-07-09T20:53:59+0000');
        $time = $datetime->format('U');
        $entry->setId((string) $id);
        $entry->setTitle('Title ' . $id);
        $entry->setDescription('Description ' . $id);
        $entry->setDateModified($time + $id);
        $entry->addAuthor(array(
            'name' => 'Name ' . $id,
            'email' => $id . '@example.com',
            'uri' => 'http://localhost/feed.xml?author=' . $id,
        ));
        $entry->setLink('http://localhost/feed.xml?id=' . $id);
        $entry->setContent('Content ' . $id);
        $feed->addEntry($entry);
        return $entry;
    }
}

/**
 * Custom formatter implementation.
 */
class TestFormatter implements FormatterInterface
{
    public function format(EntryInterface $item)
    {
        return $item->getTitle();
    }
}
