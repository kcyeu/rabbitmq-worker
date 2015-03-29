<?php
namespace Uitox\PHPDaemon;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * AMQP Consumer class implementing the PHP Simple Daemon Worker interface.
 *
 * @author: Gordon Yeu <gordon.yeu@uitox.com>
 */
class AMQPConsumer implements \Core_IWorker
{
    const USE_REDIS_CLUSTER = TRUE;

    /**
     * Provided Automatically
     * @var \Core_Worker_Mediator
     */
    public $mediator;

    /**
     * AMQP connection
     * @var mixed
     */
    private $_amqp_conn;

    /**
     * AMQP chanel
     * @var mixed
     */
    private $_amqp_ch;

    /**
     * AMQP constants
     */
    const AMQP_EXCHANGE = 'router';                                                                                                                                             
    const AMQP_QUEUE = 'msgs';
    const AMQP_CONSUMER_TAG = 'consumer';

    /**
     * Redis
     * @var String
     */
    private $_redis;

    /**
     * RedLock
     * @var String
     */
    private $_redlock;

    /**
     * RedLock timeout
     * @var Integer
     */
    private $_redlock_timeout;


    /**
     * Array of results
     * @var array
     */
    private $_results = array();

    /**
     * Data persistence for callback access
     * @var array
     */
    private $_data;

    /**
     * Called on Construct or Init
     * @return void
     */
    public function setup()
    {
        // Read AMQP details from the INI file
        // The ini plugin is created in the Daemon::setup() method
        $ini = $this->mediator->daemon('ini');
        $this->_redlock_timeout   = $ini['redlock']['timeout']; 

        $this->_amqp_setup();
        $this->_redis_setup();
    }

    /**
     * Called on Destruct
     * @return void
     */
    public function teardown()
    {
    }

    /**
     * This is called during object construction to validate any dependencies
     *
     * @return Array    Return array of error messages 
     *                  (e.g."GD Library Extension Required" or
     *                  "Cannot open /tmp for Writing") or an empty array
     */
    public function check_environment(Array $errors = array())
    {
        $errors = array();

        // Check if extention loaded
        if (! extension_loaded('Redis') )
            $errors[] = 'Redis extension required: Please install Redis PECL.';

        // Check if redis cluster supported
        if (self::USE_REDIS_CLUSTER):
            if ( ! class_exists('RedisCluster') || ! method_exists('RedisCluster','__construct'))
                $errors[] = 'RedisCluster not supported: Please install Redis PECL with cluster support or use normal mode.';
        endif;

        return $errors;
    }

    /**
     * Poll the AMQPConsumer for updated information
     *
     * @param   Array    (Optional) Associative array of results
     * @return  Array    Return associative array of results
     */
    public function poll(Array $existing_results = array())
    {
        // uncomment this if you need to handle accumulated result
        //$this->_results = $existing_results;

        // Start main logic of workers
        $this->mediator->log('Calling AMQPConsumer...');

        // Consuming queue
        $result = $this->_consume_jobs();

        // Save to redis
        $result = $this->_save_to_redis();

        return $this->_results;
    }

    private function _amqp_setup()
    {
        $ini = $this->mediator->daemon('ini');

        $this->_amqp_conn = new AMQPConnection(
            $ini['amqp']['host'],
            $ini['amqp']['port'],
            $ini['amqp']['username'],
            $ini['amqp']['password'],
            $ini['amqp']['vhost']
        );

        $this->_amqp_ch = $this->_amqp_conn->channel();

        /*
            name: $queue
            passive: false
            durable: true // the queue will survive server restarts
            exclusive: false // the queue can be accessed in other channels
            auto_delete: false //the queue won't be deleted once the channel is closed.
        */
        $this->_amqp_ch->queue_declare(
            self::AMQP_QUEUE,
            false, 
            true,
            false,
            false
        );

        /*
            name: $exchange
            type: direct
            passive: false
            durable: true // the exchange will survive server restarts
            auto_delete: false //the exchange won't be deleted once the channel is closed.
        */
        $this->_amqp_ch->exchange_declare(
            self::AMQP_EXCHANGE,
            'direct',
            false,
            true,
            false
        );

        $this->_amqp_ch->queue_bind(self::AMQP_QUEUE, self::AMQP_EXCHANGE);

        /*
            queue: Queue from where to get the messages
            consumer_tag: Consumer identifier
            no_local: Don't receive messages published by this consumer.
            no_ack: Tells the server if the consumer will acknowledge the messages.
            exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
            nowait:
            callback: A PHP Callback
        */
        $this->_amqp_ch->basic_consume(
            self::AMQP_QUEUE,
            self::AMQP_CONSUMER_TAG,
            false,
            false,
            false,
            false,
            array($this, 'mq_callback')
        );
    }

    private function _redis_setup()
    {
        $ini = $this->mediator->daemon('ini');

        /**
         * Create connection to RedisCluster
         * Format: $servers = array('host1:port1', 'host2:port2');
         */
        $servers = array(
            "{$ini['redis']['host1']}:{$ini['redis']['port1']}",
            "{$ini['redis']['host2']}:{$ini['redis']['port2']}",
            "{$ini['redis']['host3']}:{$ini['redis']['port3']}",
        );
        $this->_redis = new \RedisCluster('cluster1', $servers);

        /**
         * Create connection to RedisLock
         */
        $redlock_config = array();
        foreach ($servers as $server)
        {
            $redlock_config[] = explode(':', $server);
        }
        $this->_redlock = new \RedLock($redlock_config);
    }

    public function mq_callback($msg)
    {
        // Extract info from message body
        $msg_json = $msg->body;
        $this->mediator->log("Message body: {$msg_json}");

        // Put info into $this->_data
        // keep callback short and clean,
        // leave complex logics in _consume_jobs()
        $this->_data = array('sm_seq' => 'bar');

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }

    private function _consume_jobs()
    {
        // Trigger mq_callback
        $this->_amqp_ch->wait();

        // Process info from MQ
        $tmp = $this->_data['sm_seq'];

        //
    }

    private function _save_to_redis()
    {
        $key = $this->_data['sm_seq'];

        // Use RedLock if needed before access Redis
        while (true)
        {
            $lock = $this->_redlock->lock("{$key}.lock", $this->_redlock_timeout);

            if ($lock)
            {
                // Do something here
                /*
                $result = $this->_redis->get($key);
                $this->mediator->log("Read result : {$result}");

                $result = $this->_redis->set($key, time());
                $this->mediator->log("Write result : {$result}");

                $result = $this->_redis->get($key);
                $this->mediator->log("Read result : {$result}");
                */

                $this->_redlock->unlock($lock);
                break;
            }

            // (Optional) Buffer before next attempt
            //usleep((time() % 11) * 100000);
        }
    }
}
