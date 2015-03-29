<?php
namespace Uitox\PHPDaemon;
require_once __DIR__ . '/AMQPConsumer.php';

/**
 * A PHP Simple Daemon.
 * Use a background worker to continuously poll for consuming queue
 *
 * @author Gordon Yeu <gordon.yeu@uitox.com>
 */
class Daemon extends \Core_Daemon
{
    const WORKERS = 2;
    const TIMEOUT = 30;
    const LOG_DIR = '/tmp/';
    const CONFIG_FILE = '/config.ini';

    protected $loop_interval = 3;

    /**
     * This will hold the results returned by our AMQPConsumer
     * @var array
     */
    protected $results = array();

    /**
     * Create a Lock File plugin to ensure we're not running duplicate processes, and load
     * the config file with all of our AMQPConsumer connection details
     */
	protected function setup_plugins()
	{
          $this->plugin('Lock_File');

	      $ini = $this->plugin('ini');
	      $this->ini->filename = __DIR__ . self::CONFIG_FILE;
	      $this->ini->required_sections = array('amqp','redis','redlock');
	}

    protected function setup_workers()
    {
        $this->worker('AmqpConsumer', new AMQPConsumer);

        $this->AmqpConsumer->workers(self::WORKERS);
        $this->AmqpConsumer->timeout(self::TIMEOUT);

        // Handle timeout
        $this->AmqpConsumer->onTimeout(function($call, $log) {
            $log("AMQPConsumer Timeout Reached");
        });

        $that = $this;
        $this->AmqpConsumer->onReturn(function($call, $log) use($that) {
            if ($call->method == 'poll') {
                $that->set_results($call->return);
            }
        });

    }

	/**
	 * The setup method is called only in your parent daemon class, 
     * after plugin_setup and worker_setup and before execute()
     *
	 * @return void
	 * @throws Exception
	 */
	protected function setup()
	{
        // We don't need any additional setup.
        // Implement an empty method to satisfy the abstract base class
	}
	
	/**
	 * This daemon will perform a continuous long-poll request against an AMQPConsumer.
     * When the AMQPConsumer returns, we'll update our $results array, then start the 
     * next polling request.
     * There will always be a background worker polling for updated results.
     *
	 * @return void
	 */
	protected function execute()
	{
        if (!$this->AmqpConsumer->is_idle()) {
            $this->log("Event Loop Iteration: AMQPConsumer running in the background worker process.");
            return;
        }

        // If the Worker is idle, it means it just returned our stats.
        // Log them and start another request

        // If there isn't results yet, don't display incorrect (empty) values:
        if (!empty($this->results['data']))
        {
            // TODO: do something here is you need to process worker returns
        }

        $this->AmqpConsumer->poll($this->results);
	}

    public function set_results(Array $results)
    {
        $this->results = $results;
    }
	
	/**
	 * Dynamically build the file name for the log file. This simple algorithm 
	 * will rotate the logs once per day and try to keep them in a central /var/log location. 
	 * @return string
	 */
	protected function log_file()
	{	
		$dir = self::LOG_DIR;
		if (@file_exists($dir) == false)
			@mkdir($dir, 0777, true);
		
		if (@is_writable($dir) == false)
			$dir = __DIR__ . '/logs';
		
		return $dir . '/log_' . date('Ymd');
	}
}
