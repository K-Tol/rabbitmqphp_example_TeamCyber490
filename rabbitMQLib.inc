<?php
class rabbitMQServer
{
	public $machine;
	public $HOST;
	public $PORT;
	public $USER;
	public $PASSWORD;
	public $VHOST;
	public $exchange_name;
	public $exchange_type;
	public $listen_queue_name;
    public $listen_queue;
	public $routing_key;
    public $callback;

	function __construct($ini)
	{
		$this->machine              = parse_ini_file($ini, false);
		$this->HOST                 = $this->machine["MQ_HOST"];
		$this->PORT                 = $this->machine["MQ_PORT"];
		$this->USER                 = $this->machine["MQ_USER"];
		$this->PASSWORD             = $this->machine["MQ_PASSWORD"];
		$this->VHOST                = $this->machine["MQ_VHOST"];
		$this->exchange_name        = $this->machine["MQ_EXCHANGE_NAME"];
		$this->exchange_type        = $this->machine["MQ_EXCHANGE_TYPE"];
		$this->listen_queue_name    = $this->machine["MQ_LISTEN_QUEUE_NAME"];
        $this->listen_routing_key   = $this->machine["MQ_LISTEN_ROUTING_KEY"];
	}

	function process_message($msg)
	{
		$this->listen_queue->ack($msg->getDeliveryTag());
		try
		{
            $body = $msg->getBody();
            $payload = json_decode($body, true);
            if (isset($this->callback))
                $response = call_user_func($this->callback,$payload);

            $params = array();
            $params['host'] = $this->HOST;
            $params['port'] = $this->PORT;
            $params['login'] = $this->USER;
            $params['password'] = $this->PASSWORD;
            $params['vhost'] = $this->VHOST;
            $conn = new AMQPConnection($params);
            $conn->connect();
            $channel = new AMQPChannel($conn);
            $exchange = new AMQPExchange($channel);
            $exchange->setName($this->exchange_name);
            $exchange->setType($this->exchange_type);

            if (!isset($payload["reply_queue_name"]) || !isset($payload["reply_routing_key"]))
                return;

            $reply_queue_name = $payload["reply_queue_name"];
            $reply_routing_key = $payload["reply_routing_key"];

            $send_queue = new AMQPQueue($channel);
            $send_queue->setName($reply_queue_name);
            $send_queue->bind($exchange->getName(),$reply_routing_key);
            $exchange->publish(json_encode($response),$reply_routing_key,AMQP_NOPARAM,array('correlation_id'=>$msg->getCorrelationId()));
		}
		catch(Exception $e)
		{
			// ampq throws exception if get fails...
			echo "error: rabbitMQServer: process_message: exception caught: ".$e;
		}
	}

	function process_requests($callback)
	{
		try
		{
			$this->callback = $callback;
			$params = array();
			$params['host'] = $this->HOST;
			$params['port'] = $this->PORT;
			$params['login'] = $this->USER;
			$params['password'] = $this->PASSWORD;
			$params['vhost'] = $this->VHOST;
			$conn = new AMQPConnection($params);
			$conn->connect();

			$channel = new AMQPChannel($conn);

			$exchange = new AMQPExchange($channel);
			$exchange->setName($this->exchange_name);
			$exchange->setType($this->exchange_type);

			$this->listen_queue = new AMQPQueue($channel);
			$this->listen_queue->setName($this->listen_queue_name);
			$this->listen_queue->bind($this->exchange_name,$this->listen_routing_key);

			$this->listen_queue->consume(array($this,'process_message'));

			// Loop as long as the channel has callbacks registered
			while (count($channel->callbacks))
				$channel->wait();
		}
		catch (Exception $e)
		{
			trigger_error("Failed to start request processor: ".$e,E_USER_ERROR); 
		}
	}
}

class rabbitMQClient
{
	public $machine;
	public $HOST;
	public $PORT;
	public $USER;
	public $PASSWORD;
	public $VHOST;
	public $exchange_name;
	public $exchange_type;
	public $send_queue_name;
	public $reply_queue_name;
	public $send_routing_key;
	public $reply_routing_key;
    public $send_queue;
    public $response;
    public $correlation_id;

	function __construct($ini, $send_queue_name, $send_routing_key)
	{
		$this->machine              = parse_ini_file($ini, false);
		$this->HOST                 = $this->machine["MQ_HOST"];
		$this->PORT                 = $this->machine["MQ_PORT"];
		$this->USER                 = $this->machine["MQ_USER"];
		$this->PASSWORD             = $this->machine["MQ_PASSWORD"];
		$this->VHOST                = $this->machine["MQ_VHOST"];
		$this->exchange_name        = $this->machine["MQ_EXCHANGE_NAME"];
		$this->exchange_type        = $this->machine["MQ_EXCHANGE_TYPE"];
		$this->reply_routing_key    = $this->machine["MQ_REPLY_ROUTING_KEY"];
		$this->reply_queue_name     = $this->machine["MQ_REPLY_QUEUE_NAME"];
		$this->send_queue_name      = $send_queue_name;
		$this->send_routing_key     = $send_routing_key;
	}

	function process_response(AMQPEnvelope $response)
	{
        if (!($this->correlation_id == $response->getCorrelationId()))
            return true;
		$this->send_queue->ack($response->getDeliveryTag());
		$body = $response->getBody();
		$payload = json_decode($body, true);
		if (!(isset($payload)))
			$payload = "[empty response]";
		$this->response = $payload;
		return false;
	}

	function send_request($message)
	{
		$this->correlation_id = uniqid();
        $message["reply_queue_name"] = $this->reply_queue_name;
        $message["reply_routing_key"] = $this->reply_routing_key;
		$json_message = json_encode($message);
		try
		{
			$params = array();
			$params['host'] = $this->HOST;
			$params['port'] = $this->PORT;
			$params['login'] = $this->USER;
			$params['password'] = $this->PASSWORD;
			$params['vhost'] = $this->VHOST;

			$conn = new AMQPConnection($params);
			$conn->connect();

			$channel = new AMQPChannel($conn);

            // exchange, response_queue, conn_queue are assumed to exist on the broker
            // since declare is not called, these should fail if they do not exist
			$exchange = new AMQPExchange($channel);
			$exchange->setName($this->exchange_name);
			$exchange->setType($this->exchange_type);

			$reply_queue = new AMQPQueue($channel);
			$reply_queue->setName($this->reply_queue_name);
			$reply_queue->bind($this->exchange_name,$this->reply_routing_key);

			$this->send_queue = new AMQPQueue($channel);
			$this->send_queue->setName($this->send_queue_name);
			$this->send_queue->bind($this->exchange_name,$this->send_routing_key);

			$exchange->publish($json_message,$this->send_routing_key,AMQP_NOPARAM,array('correlation_id'=>$this->correlation_id));

            $reply_queue->consume(array($this,'process_response'));

			$response = $this->response;
            unset($this->response);

            $conn->disconnect();

			return $response;
		}
		catch(Exception $e)
		{
			die("failed to send message to exchange: ". $e->getMessage()."\n");
		}
	}

	/**
	  @brief send a one-way message to the server.  These are
	  auto-acknowledged and give no response.

	  @param message the body of the request.  This must make sense to the
	  server
	 */
	function publish($message)
	{
		$json_message = json_encode($message);
		try
		{
			$params = array();
			$params['host'] = $this->HOST;
			$params['port'] = $this->PORT;
			$params['login'] = $this->USER;
			$params['password'] = $this->PASSWORD;
			$params['vhost'] = $this->VHOST;
			$conn = new AMQPConnection($params);
			$conn->connect();
			$channel = new AMQPChannel($conn);
			$exchange = new AMQPExchange($channel);
			$exchange->setName($this->exchange_name);
			$exchange->setType($this->exchange_type);
			$this->send_queue = new AMQPQueue($channel);
			$this->send_queue->setName($this->send_queue_name);
			$this->send_queue->bind($this->exchange_name,$this->send_routing_key);
			return $exchange->publish($json_message,$this->send_routing_key);
		}
		catch(Exception $e)
		{
			die("failed to send message to exchange: ". $e->getMessage()."\n");
		}
	}
}
?>