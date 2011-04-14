#!/usr/bin/env php
<?php

$protoscope = new Protoscope;
// you can alter the default config with something like this:
// $ echo '{"port": 8080}' | ./protoscope.php
$config = json_decode(file_get_contents('php://stdin'), true);
$protoscope->run($config);

class Protoscope {

    const VERSION = '0.7.9';

    // Set default config options.
    protected $config = array('ip' => '127.0.0.1',
                              'port' => '4887',
                              'log' => '/tmp/protoscope.log',
                              'embed' => TRUE);

    public function log($message)
    {
        $now = date('Y-m-d H:i:s');
        error_log("[{$now}] {$message}", 3, $this->config['log']);
    }

    protected function request($method, $url, $content = '', $headers = array())
    {
        // Only deal with GET and POST for now.
        if ($method != 'GET' && $method != 'POST') {
            return;
        }

        // Build $options array based on input.
        $options = array('method' => $method, 'ignore_errors' => TRUE);

        if (!empty($data)) {
            $options['content'] = $content;
        }

        if (!empty($headers)) {
            // FIXME: Sending headers breaks Google. Why?
            // $options['header'] = $headers;
        }

        $options = array('http' => $options);

        // Create context.
        $context = stream_context_create($options);

        if (!$stream = fopen($url, 'rb', FALSE, $context)) {
            $this->log("[{$php_errormsg}]\n");
        } else {
            return stream_get_contents($stream);
        }
    }

    public function run($config)
    {
        // prefer user-supplied config:
        $this->config = (array)$config + $this->config;

        // set default timezone if the user doesn't have it in php.ini:
        if (!ini_get('date.timezone')) {
            date_default_timezone_set('America/New_York');
        }

        set_time_limit(0);

        $this->log("Starting protoscope: {$this->config['ip']}:{$this->config['port']}\n");

        $client = array();
        $server = array();

        $socket = stream_socket_server("{$this->config['ip']}:{$this->config['port']}", $error, $message);

        if (!$socket) {
          $this->log("[{$error}] [{$message}]\n");
        } else {
            // Accept connections indefinitely.
            while ($stream = stream_socket_accept($socket, -1)) {
                $this->log("--\n");

                // Disable blocking, so data can be inspected as it is received.
                stream_set_blocking($stream, 0);

                $client['headers'] = array();

                // Read headers.
                $this->log("Headers\n");
                while ($line = stream_get_line($stream, 8192, "\n")) {
                    $line = trim($line);
                    if (!empty($line)) {
                        $this->log("    [{$line}]\n");
                        $client['headers'][] = $line;
                    }
                }

                // Read content, if any.
                $client['content'] = stream_get_contents($stream);
                if (!empty($client['content'])) {
                    $this->log("Content\n");
                    $this->log("    [{$client['content']}]\n");
                }

                // Build request to send to the server.
                list($server['method'], $server['url'], $server['protocol']) = explode(' ', $client['headers'][0]);

                $server['headers'] = array();

                foreach ($client['headers'] as $key => $header) {
                    // Skip the request line.
                    if ($key) {
                        list ($name, $value) = explode(': ', $header);

                        switch (strtolower($name)) {
                            case 'accept-encoding':
                                // Do not accept encoding.
                                break;
                            case 'connection':
                                // Do not support persistent connections yet.
                                $server['headers'][] = 'Connection: close';
                            default:
                                $server['headers'][] = "{$name}: {$value}";
                                break;
                        }
                    }
                }

                // Send request to server.
                $this->log("Request\n");
                $this->log("    [{$server['method']}] [{$server['url']}]\n");
                $server['response'] = $this->request($server['method'], $server['url'], $client['content'], $server['headers']);

                $server['response'] .= '<div id="protoscope"><pre style="text-align: left; padding: 10px; border-top: #ccc solid 1px">';
                foreach ($client['headers'] as $header) {
                    $server['response'] .= "{$header}\n";
                }
                $server['response'] .= '</pre></div>';

                // Send response to client.
                $this->log("Response\n");
                $this->log('    [' . strlen($server['response']) . " bytes]\n");
                fwrite($stream, $server['response']);

                $client['request'] = '';
                fclose($stream);
                $this->log("--\n");
            }

            fclose($socket);
        }
    }

}

?>
