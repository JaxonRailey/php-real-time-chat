<?php

class Chat {

    protected $server;
    protected string $address;
    protected int $port;
    protected array $clients = [];
    protected array $users   = [];


    /**
     * Connection
     *
     * @param string $address
     * @param int $port
     *
     * @return void
     */

    public function connect(string $address, int $port): void {

        $this->server  = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->address = $address;
        $this->port    = $port;

        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->server, $address, $port);
        socket_listen($this->server);
    }


    /**
     * Send messages to all clients
     *
     * @param string $message
     *
     * @return bool
     */

    public function send(string $message): bool {

        $raw = $this->mask($message);

        foreach ($this->clients as $client) {
            @socket_write($client, $raw, strlen($raw));
        }

        return true;
    }


    /**
     * Unmask socket data
     *
     * @param string $data
     *
     * @return string
     */

    protected function unmask(string $value): string {

        $length = ord($value[1]) & 127;

        if ($length == 126) {
            $masks = substr($value, 4, 4);
            $data  = substr($value, 8);
        } elseif ($length == 127) {
            $masks = substr($data, 10, 4);
            $data  = substr($value, 14);
        } else {
            $masks = substr($value, 2, 4);
            $data  = substr($value, 6);
        }

        $value = '';

        for ($i = 0; $i < strlen($data); ++$i) {
            $value .= $data[$i] ^ $masks[$i % 4];
        }

        return $value;
    }


    /**
     * Mask socket data
     *
     * @param string $data
     *
     * @return string
     */

    protected function mask(string $value): string {

        $byte   = 10000000 | (00000001 & 00001111);
        $length = strlen($value);

        if ($length <= 125) {
            $header = pack('CC', $byte, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $byte, 126, $length);
        } elseif ($length >= 65536) {
            $header = pack('CCNN', $byte, 127, $length);
        }

        return $header . $value;
    }


    /**
     * Sends handshake headers to connected clients
     *
     * @param string $header
     * @param $socket
     * @param string $address
     * @param int $port
     *
     * @return void
     */

    protected function handshake(string $header, $socket, string $address, int $port): void {

        $headers = [];
        $lines   = preg_split("/\r\n/", $header);

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $secKey    = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $buffer    = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n";
        $buffer   .= "Upgrade: websocket\r\n";
        $buffer   .= "Connection: Upgrade\r\n";
        $buffer   .= "WebSocket-Origin: $address\r\n";
        $buffer   .= "WebSocket-Location: ws://$address:$port/server.php\r\n";
        $buffer   .= "Sec-WebSocket-Accept:$secAccept\r\n\r\n";

        socket_write($socket, $buffer, strlen($buffer));
    }


    /**
     * Running websocket server
     *
     * @return void
     */

    public function run(): void {

        $this->clients = [
            $this->server
        ];

        $address = $this->address;
        $port    = $this->port;

        echo "In ascolto della richiesta in arrivo sulla porta {$this->port} ..\n";

        while (true) {
            $joined = $this->clients;
            socket_select($joined, $null, $null, 0, 10);

            // if user is joined in chat
            if (in_array($this->server, $joined)) {
                $socket = socket_accept($this->server);
                $this->clients[] = $socket;
                $header = socket_read($socket, 1024);
                $this->handshake($header, $socket, $address, $port);
                socket_getpeername($socket, $ip);
                echo "Utente con IP {$ip} appena entrato \n";
                $index = array_search($this->server, $joined);
                unset($joined[$index]);
            }

            foreach ($joined as $resource) {
                while (socket_recv($resource, $data, 1024, 0) >= 1) {
                    if ($data) {
                        $data = $this->unmask($data);
                        $data = json_decode($data);

                        // if user is logged
                        if (isset($data->open)) {
                            $this->users[$ip] = [
                                'id'     => $data->id,
                                'name'   => trim(strip_tags($data->name)),
                                'avatar' => trim(strip_tags($data->avatar)),
                                'date'   => date('Y-m-d H:i:s')
                            ];

                            $this->send(json_encode(['users' => array_values($this->users), 'last' => trim(strip_tags($data->name))], true));
                        }

                        // if user has sent message
                        if (isset($data->id) && isset($data->text)) {
                            $keys         = array_keys($this->users);
                            $key          = array_search($data->id, array_column($this->users, 'id'));
                            $ip           = $keys[$key];
                            $user         = $this->users[$ip];
                            $data->name   = trim(strip_tags($user['name']));
                            $data->avatar = trim(strip_tags($user['avatar']));
                            $data->text   = trim(strip_tags($data->text));
                            $data->date   = date('Y-m-d H:i:s');

                            $this->send(json_encode($data));
                        }

                        break 2;
                    }
                }

                $data = @socket_read($resource, 1024, PHP_NORMAL_READ);

                // if user is disconnected
                if ($data === false) {
                    socket_getpeername($resource, $ip);
                    $user = $this->users[$ip];
                    unset($this->users[$ip]);
                    $this->send(json_encode(['leave' => $user, 'users' => array_values($this->users)]));
                    echo "Utente con IP {$ip} appena uscito \n";
                    $index = array_search($resource, $this->clients);
                    unset($this->clients[$index]);
                }
            }
        }

        socket_close($this->server);
    }
}
