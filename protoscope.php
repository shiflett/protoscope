#!/usr/bin/env php
<?php

$protoscope = new Protoscope;
$protoscope->run();

class Protoscope {

    const VERSION = '0.7.9';

    // Set default config options.
    protected $config = array('ip' => '127.0.0.1',
                              'port' => '4887',
                              'embed' => TRUE);

    public function log($message)
    {
        $now = date('Y-m-d H:i:s');
        echo "[{$now}] {$message}";
    }

    protected function request($url, $request)
    {
        $this->log("    request([{$url}], ...)\n");

        // Parse URL.
        $url = parse_url($url);

        switch ($url['scheme']) {
            case 'http':
                $port = 80;
                break;
            case 'https':
                $port = 443;
                break;
            default:
                $this->log("        URL has no scheme. Assuming port [{$url['port']}].\n");
                $port = $url['port'];
                if (substr($request, 0, 7) == 'CONNECT') {
                    $this->log("        CONNECT not yet supported. Responding with 405. \n");
                    $this->log("        For more info: https://github.com/shiflett/protoscope/issues/2\n");
                    return "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
                }
                break;
        }

        // Open a connection to the server.
        $this->log("        fsockopen([{$url['host']}][{$port}])\n");
        if (!$stream = fsockopen($url['host'], $port, $error, $message)) {
            $this->log("[{$error}][{$message}]\n");
            return;
        }

        // Write the request.
        for ($place = 0; $place < strlen($request); $place += $written) {
            $written = fwrite($stream, substr($request, $place));
        }
        $this->log("        [{$place}] bytes written.\n");

        // Return the response.
        return stream_get_contents($stream);
    }

    public function run()
    {
        // Set default timezone if it's not set in php.ini.
        if (!ini_get('date.timezone')) {
            date_default_timezone_set('America/New_York');
        }

        set_time_limit(0);

        $this->log("Protoscope [{$this->config['ip']}:{$this->config['port']}]\n");

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

                $server['headers'] = array();

                foreach ($client['headers'] as $key => $header) {
                    // Skip the request line.
                    if ($key) {
                        list ($name, $value) = explode(': ', $header);

                        // Use this to omit or modify headers to be sent to the server.
                        switch (strtolower($name)) {
                            case 'accept-encoding':
                            case 'keep-alive':
                            case 'pragma':
                                break;
                            case 'connection':
                            case 'proxy-connection':
                                $server['headers'][] = "Connection: close";
                                break;
                            default:
                                $server['headers'][] = "{$name}: {$value}";
                                break;
                        }
                    }
                }

                // Parse request line.
                list($server['method'], $server['url'], $server['protocol']) = explode(' ', $client['headers'][0]);

                // Build request.
                $server['request'] = $client['headers'][0] . "\r\n" . implode("\r\n", $server['headers']);

                // Add content.
                $server['request'] .= "\r\n\r\n" . $client['content'];

                // Send request to server.
                $this->log("Request\n");
                $this->log("    [{$client['headers'][0]}]\n");
                $server['response'] = $this->request($server['url'], $server['request']);

                // FIXME: Only embed in text/html responses.
                // FIXME: Handle chunked encoding. This will be ignored.
                if ($this->config['embed']) {
                    $server['response'] .= '<div id="protoscope"><pre style="text-align: left; padding: 10px; border-top: #ccc solid 1px; background: #eee">';
                    foreach ($client['headers'] as $header) {
                        $server['response'] .= "{$header}\n";
                    }
                    $server['response'] .= '</pre></div>';
                }

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
