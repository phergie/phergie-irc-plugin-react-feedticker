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

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\ConnectionInterface;
use Phergie\Irc\Client\React\LoopAwareInterface;
use Phergie\Irc\Event\EventInterface as Event;
use React\EventLoop\LoopInterface;
use WyriHaximus\Phergie\Plugin\Http\Request as HttpRequest;
use Zend\Feed\Reader\Reader as FeedReader;

/**
 * Plugin class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\FeedTicker
 */
class Plugin extends AbstractPlugin implements LoopAwareInterface
{
    /**
     * Event loop used to set up timed callbacks for feed polls 
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Number of seconds to wait between feed polls
     *
     * @var int
     */
    protected $interval;

    /**
     * List of URLs for feeds to poll
     *
     * @var array
     */
    protected $urls;

    /**
     * Mapping of connection masks to lists of channels or users on those
     * connections to which new items should be syndicated
     *
     * @var array
     */
    protected $targets;

    /**
     * Mapping of connection masks to corresponding event queues, used to
     * syndicate new items to channels or users
     *
     * @var array
     */
    protected $queues;

    /**
     * Cache keyed by feed URL of items from the last poll of each feed, used
     * to determine which items are new and should be syndicated to channels or
     * users
     *
     * @var array
     */
    protected $cache;

    /**
     * Exception code used when the 'urls' configuration setting has an invalid
     * value
     */
    const ERR_URLS_INVALID = 1;

    /**
     * Exception code used when the 'targets' configuration setting has an
     * invalid value
     */
    const ERR_TARGETS_INVALID = 2;

    /**
     * Exception code used when the 'interval' configuration setting has an
     * invalid value
     */
    const ERR_INTERVAL_INVALID = 3;

    /**
     * Exception code used when the 'formatter' configuration setting has an
     * invalid value
     */
    const ERR_FORMATTER_INVALID = 4;

    /**
     * Accepts plugin configuration.
     *
     * Supported keys:
     *
     * urls - required list of feed URLs to poll for content
     *
     * targets - required associative array keyed by connection mask
     * referencing enumerated arrays of channels or users to receive syndicated
     * feed items
     *
     * interval - optional integer representing the number of seconds to wait
     * between polls of feeds for new content, defaults to 300 (5 minutes)
     *
     * formatter - optional object used to format data from feed items prior to
     * their syndication
     *
     * @param array $config
     * @throws \DomainException if any configuration setting contains an
     * invalid value
     */
    public function __construct(array $config = array())
    {
        $this->urls = $this->getUrls($config);
        $this->targets = $this->getTargets($config);
        $this->interval = $this->getInterval($config);
        $this->formatter = $this->getFormatter($config);
        $this->queues = array();
        $this->cache = array();
    }

    /**
     * Receives the event loop and performs initial feed polling (which
     * requires that the event loop be set so that a timed callback can be set
     * up later for subsequent feed polling).
     *
     * @param \React\EventLoop\LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop)
    {
        $this->loop = $loop;

        $this->pollFeeds();
    }

    /**
     * Indicates that the plugin monitors USER events because they are one-time
     * per-connection events that enable it to obtain a reference to the event
     * queue for each connection.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            'irc.sent.user' => 'getEventQueue',
        );
    }

    /**
     * Polls feeds for new content to syndicate to channels or users.
     */
    protected function pollFeeds()
    {
        foreach ($this->urls as $url) {
            $this->pollFeed($url);
        }
    }

    /**
     * Polls an individual feed for new content to syndicate to channels or
     * users.
     *
     * @param string $url Feed URL
     */
    public function pollFeed($url)
    {
        $self = $this;
        $eventEmitter = $this->getEventEmitter();
        $logger = $this->getLogger();
        $logger->info('Sending request for feed URL', array('url' => $url));
        $request = new HttpRequest(array(
            'url' => $url,
            'resolveCallback' => function($data) use ($url, $self) {
                $self->processFeed($url, $data);
            },
            'rejectCallback' => function($error) use ($url, $self) {
                $self->processFailure($url, $error);
            }
        ));
        $eventEmitter->emit('http.request', array($request));
    }

    /**
     * Processes content from successfully polled feeds.
     *
     * @param string $url URL of the polled feed
     * @param string $data Content received from the feed poll
     */
    public function processFeed($url, $data)
    {
        $logger = $this->getLogger();
        $logger->info('Processing feed', array('url' => $url));
        $logger->debug('Received feed data', array('data' => $data));

        try {
            $new = iterator_to_array(FeedReader::importString($data));
            $old = isset($this->cache[$url]) ? $this->cache[$url] : array();
            $diff = $this->getNewFeedItems($new, $old);
            if ($old) {
                $this->syndicateFeedItems($diff);
            }
            $this->cache[$url] = $new;
        } catch (\Exception $e) {
            $logger->warning(
                'Failed to process feed',
                array(
                    'url' => $url,
                    'data' => $data,
                    'error' => $e,
                )
            );
        }

        $this->markFeedProcessed($url);
    }

    /**
     * Locates new items in a feed given newer and older lists of items from
     * the feed.
     *
     * @param \Zend\Feed\Reader\Entry\EntryInterface[] $new
     * @param \Zend\Feed\Reader\Entry\EntryInterface[] $old
     */
    protected function getNewFeedItems(array $new, array $old)
    {
        $map = array();
        $getKey = function($item) {
            $id = $item->getPermalink();
            if (!$id) {
                $id = $item->saveXml();
            }
            return $id;
        };
        $logger = $this->getLogger();
        foreach ($new as $item) {
            $key = $getKey($item);
            $logger->debug('New: ' . $key);
            $map[$key] = $item;
        }
        foreach ($old as $item) {
            $key = $getKey($item);
            $logger->debug('Old: ' . $key);
            unset($map[$key]);
        }
        $logger->debug('Diff: ' . implode(' ', array_keys($map)));
        return array_values($map);
    }

    /**
     * Syndicates a given list of feed items to all targets.
     *
     * @param \Zend\Feed\Reader\Entry\EntryInterface[] $items
     */
    protected function syndicateFeedItems(array $items)
    {
        $logger = $this->getLogger();

        $messages = array();
        foreach ($items as $item) {
            $messages[] = $this->formatter->format($item);
        }

        foreach ($this->targets as $connection => $targets) {
            if (!isset($this->queues[$connection])) {
                $logger->notice(
                    'Encountered unknown connection, or USER event not yet received',
                    array(
                        'connection' => $connection,
                        'connections' => array_keys($this->queues),
                    )
                );
                continue;
            }
            $queue = $this->queues[$connection];

            foreach ($targets as $target) {
                foreach ($messages as $message) {
                    $queue->ircPrivmsg($target, $message);
                }
            }
        }
    }

    /**
     * Logs feed poll failures.
     *
     * @param string $url URL of the feed for which a poll failure occurred
     * @param string $error Message describing the failure
     */
    public function processFailure($url, $error)
    {
        $this->getLogger()->warning(
            'Failed to poll feed',
            array(
                'url' => $url,
                'error' => $error,
            )
        );

        $this->markFeedProcessed($url);
    }

    /**
     * Sets up a callback to poll a specified feed.
     *
     * @param string $url Feed URL
     */
    protected function markFeedProcessed($url)
    {
        $self = $this;
        $this->loop->addTimer(
            $this->interval,
            function() use ($url) {
                $self->pollFeed($url);
            }
        );
    }

    /**
     * Stores a reference to the event queue for the connection on which a MOTD
     * (message of the day) event occurs.
     *
     * @param \Phergie\Irc\EventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function getEventQueue(Event $event, Queue $queue)
    {
        $mask = $this->getConnectionMask($event->getConnection());
        $this->queues[$mask] = $queue;
    }

    /**
     * Returns the connection mask for a given connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @return string
     */
    protected function getConnectionMask(ConnectionInterface $connection)
    {
        return sprintf('%s!%s@%s',
            $connection->getNickname(),
            $connection->getUsername(),
            $connection->getServerHostname()
        );
    }

    /**
     * Extracts feed URLs from configuration.
     *
     * @param array $config
     * @return array
     * @throws \DomainException if urls setting is invalid
     */
    protected function getUrls(array $config)
    {
        if (!isset($config['urls'])
            || !is_array($config['urls'])
            || array_filter($config['urls'], 'is_string') != $config['urls']) {
            throw new \DomainException(
                'urls must be a list of strings containing feed URLs',
                self::ERR_URLS_INVALID
            );
        }
        return $config['urls'];
    }

    /**
     * Extracts targets from configuration.
     *
     * @param array $config
     * @return array
     * @throws \DomainException if targets setting is invalid
     */
    protected function getTargets(array $config)
    {
        if (!isset($config['targets'])
            || !is_array($config['targets'])
            || array_filter($config['targets'], 'is_array') != $config['targets']) {
            throw new \DomainException(
                'targets must be an array of arrays',
                self::ERR_TARGETS_INVALID
            );
        }
        return $config['targets'];
    }

    /**
     * Extracts the interval on which to update feeds from configuration.
     *
     * @param array $config
     * @return int
     * @throws \DomainException if interval setting is invalid
     */
    protected function getInterval(array $config)
    {
        if (isset($config['interval'])) {
            if (!is_int($config['interval']) || $config['interval'] <= 0) {
                throw new \DomainException(
                    'interval must reference a positive integer value',
                    self::ERR_INTERVAL_INVALID
                );
            }
            return $config['interval'];
        }
        return 300; // default to 5 minutes
    }

    /**
     * Extracts the feed item formatter from configuration.
     *
     * @param array $config
     * @return \Phergie\Irc\Plugin\React\FeedTicker\FormatterInterface
     * @throws \DomainException if formatter setting is invalid
     */
    protected function getFormatter(array $config)
    {
        if (isset($config['formatter'])) {
            if (!$config['formatter'] instanceof FormatterInterface) {
                throw new \DomainException(
                    'formatter must implement ' . __NAMESPACE__ . '\FormatterInterface',
                    self::ERR_FORMATTER_INVALID
                );
            }
            return $config['formatter'];
        }
        return new DefaultFormatter;
    }
}
