<?php

namespace React\Http\Io;

use React\Socket\ConnectionInterface;
use React\Stream\DuplexStreamInterface;

/**
 * @internal
 */
class DuplexBodyStream extends ReadableBodyStream implements DuplexStreamInterface
{
    private $connection;
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        parent::__construct($connection);
    }

    public function isWritable()
    {
        return $this->connection->isWritable();
    }

    public function write($data)
    {
        return $this->connection->write($data);
    }

    public function end($data = null)
    {
        return $this->connection->end($data);
    }
}
