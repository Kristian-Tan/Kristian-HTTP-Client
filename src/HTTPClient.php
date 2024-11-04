<?php
namespace KristianTan;

class HTTPClient
{
    // input
    public $request_url = "http://localhost/"; // http://kristian.ax.lt/api/handler.php
    public $request_port = null; // if null then use default port (80 for http and 443 for https)
    public $request_header = array(); // ['Authorization' => 'Bearer vwhr....xxyz']
    public $request_method = "GET"; // GET/POST/DELETE/PATCH/PUT/HEAD/...
    public $request_body = null;
    public $php_api = "file_get_contents"; // file_get_contents ATAU curl
    public $ssl_cacert_file = "cacert.pem"; // use path to cacert file
    public $ssl_cacert_directory = "/etc/ssl/certs"; // use path to cacert directory
    public $ssl_verify = true; // change to false to disable ssl verification (insecure)
    public $request_http_proxy = null; // "localhost:3128" (for example: squid http proxy)
    public $request_http_timeout = null; // null = use default, positive integer = number of second until request timed out
    public $debug = null; // null = discard all debug information, "var" = save to variable, "echo" = print debug information to stdout/echo command
    public $debug_level = "NONE"; // see map_debug_level

    // output
    public $response_code = 0; // 200, 404, ...
    public $response_body = "";
    public $response_header = array();
    public $debug_output = "";

    protected $map_debug_level = array(
        "NONE" => 0, // do not print anything
        "ERROR" => 1, // only show error
        "WARNING" => 2, // only show warning and error (recoverable error, might actually work as intended)
        "INFO" => 3, // informational message
        "DEBUG" => 9, // all debug messages
    );

    public function execute()
    {
        $method_name = "make_request_" . $this->php_api;
        $this->debug_dump("http execute start", "INFO");
        $this->debug_dump("dump: ".print_r($this, true), "DEBUG");
        $this->debug_dump($method_name, "DEBUG");
        $this->$method_name();
        $this->debug_dump("http execute end", "INFO");
        $this->debug_dump("dump: ".print_r($this, true), "DEBUG");
    }

    private function make_request_file_get_contents()
    {
        if($this->request_header == null)
        {
            $this->request_header = array();
        }

        if( $this->is_json($this->request_body) )
        {
            $this->request_header[] = array("Content-type", "application/json");
        }
        else if( $this->request_body != null && $this->request_body != "" )
        {
            $this->request_header[] = array("Content-type", "application/x-www-form-urlencoded");
        }
        if( $this->request_body == null ) $this->request_body = "";

        $stringHeader = "";

        foreach ($this->request_header as $header)
        {
            if( is_array($header) && count($header) == 2 )
                $stringHeader .= $header[0] . ": " . $header[1] . "\r\n";
            else
                $stringHeader .= $header . "\r\n";
        }

        $this->debug_dump("api (".$this->php_api."): header = ".print_r($stringHeader, true), "DEBUG");

        // use key 'http' even if you send the request to https://...
        $options = array();
        $options["http"] = array();
        $options["http"]["header"] = $stringHeader;
        $options["http"]["method"] = $this->request_method;
        $options["http"]["content"] = $this->request_body;
        $options["http"]["ignore_errors"] = true; // silence warning if response code = 404
        if($this->ssl_verify && $this->is_https())
        {
            $temp = $this->ssl_cacert_file_path();
            if(!empty($temp)) $options["http"]["cafile"] = $this->ssl_cacert_file_path();
            //$options["http"]["verify_peer"] = true;
            //$options["http"]["verify_peer_name"] = true;
            $options["ssl"]["verify_peer"] = true;
            $options["ssl"]["verify_peer_name"] = true;
            $this->debug_dump("api (".$this->php_api."): SSL mode ON", "DEBUG");
        }
        else if(!$this->ssl_verify && $this->is_https())
        {
            //$options["http"]["verify_peer"] = false;
            //$options["http"]["verify_peer_name"] = false;
            $options["ssl"]["verify_peer"] = false;
            $options["ssl"]["verify_peer_name"] = false;
            $this->debug_dump("api (".$this->php_api."): SSL mode OFF", "DEBUG");
        }

        if(!empty($this->request_http_proxy))
        {
            $options["http"]["proxy"] = "tcp://".$this->request_http_proxy; // ex: 'tcp://192.168.0.2:3128'
            $options["http"]["request_fulluri"] = true;
            $this->debug_dump("api (".$this->php_api."): PROXY mode ON", "DEBUG");
        }
        if(is_numeric($this->request_http_timeout))
        {
            $options["http"]["timeout"] = $this->request_http_timeout;
            $this->debug_dump("api (".$this->php_api."): TIMEOUT mode ON", "DEBUG");
        }

        // make request
        $this->response_body = file_get_contents($this->request_url, false, stream_context_create($options));
        $this->debug_dump("api (".$this->php_api."): completed", "INFO");
        if($this->response_body === false) $this->debug_dump("api (".$this->php_api."): request failed", "ERROR");
        // get code
        $status_line = $http_response_header[0]; // https://www.php.net/manual/en/reserved.variables.httpresponseheader.php
        $this->debug_dump("api (".$this->php_api."): http_response_header = ".print_r($http_response_header,true), "DEBUG");
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $this->response_code = $match[1];
        // get headers
        for ($i = 1; $i < count($http_response_header); $i++) // skip first line
        {
            if(strpos($http_response_header[$i], ": ") !== false)
            {
                $this->response_header[] = explode(": ", $http_response_header[$i]);
                $this->debug_dump("api (".$this->php_api."): reponse header type 1 parsed", "DEBUG");
            }
            else if(strpos($http_response_header[$i], ":") !== false)
            {
                $this->response_header[] = explode(":", $http_response_header[$i]);
                $this->debug_dump("api (".$this->php_api."): reponse header type 2 parsed", "DEBUG");
            }
            else if($header[$i] != "")
            {
                $this->response_header[] = $http_response_header[$i];
                $this->debug_dump("api (".$this->php_api."): reponse header type 3 parsed", "DEBUG");
            }
        }
    }

    private function make_request_curl()
    {
        $tryport = parse_url($this->request_url, PHP_URL_PORT);
        if(!empty($tryport) && empty($this->request_port))
        {
            $this->request_port = $tryport;
            $this->debug_dump("api (".$this->php_api."): port parsed from url: ".$tryport, "DEBUG");
        }

        if( $this->is_json($this->request_body) )
        {
            $this->request_header[] = array("Content-type", "application/json");
            $this->debug_dump("api (".$this->php_api."): JSON mode ON", "DEBUG");
        }
        else if( $this->request_body != null && $this->request_body != "" )
        {
            $this->request_header[] = array("Content-type", "application/x-www-form-urlencoded");
            $this->debug_dump("api (".$this->php_api."): FORM mode ON", "DEBUG");
        }
        
        $arrHeaders = array();
        foreach ($this->request_header as $header)
        {
            if( is_array($header) && count($header) == 2 )
                $arrHeaders[] = $header[0] . ": " . $header[1];
            else
                $arrHeaders[] = $header;
        }
        $this->debug_dump("api (".$this->php_api."): header = ".print_r($arrHeaders, true), "DEBUG");

        if( $this->request_body == null )
        {
            $this->request_body = "";
            $this->debug_dump("api (".$this->php_api."): body initialized with empty string", "DEBUG");
        }

        $verbose = 0;
        if($this->debug == "echo")
        {
            $verbose = 1;
            $this->debug_dump("api (".$this->php_api."): CURLOPT_VERBOSE mode ON", "DEBUG");
        }

        $ch = curl_init($this->request_url);
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $arrHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => $verbose, // change to 1 to print http request and response
            CURLOPT_HEADER => true,
        ));

        if($this->is_https())
        {
            curl_setopt($ch, CURLOPT_PORT, 443);
            if($this->ssl_verify)
            {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); //use this for more secure SSL verification
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                $temp = $this->ssl_cacert_file_path();
                if(!empty($temp)) curl_setopt ($ch, CURLOPT_CAINFO, $this->ssl_cacert_file_path());
                $temp = $this->ssl_cacert_directory_path();
                if(!empty($temp)) curl_setopt ($ch, CURLOPT_CAPATH, $this->ssl_cacert_directory_path());
                $this->debug_dump("api (".$this->php_api."): SSL mode ON", "DEBUG");
            }
            else
            {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $this->debug_dump("api (".$this->php_api."): SSL mode OFF", "DEBUG");
            }
        }
        else
        {
            curl_setopt($ch, CURLOPT_PORT, 80);
            $this->debug_dump("api (".$this->php_api."): SSL mode NONE", "DEBUG");
        }
        if(!empty($this->request_port))
        {
            curl_setopt($ch, CURLOPT_PORT, $this->request_port);
        }

        if( $this->request_body != null && $this->request_body != "")
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request_body);
        }

        if( strtoupper($this->request_method) == "GET")
        {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        else if( strtoupper($this->request_method) == "POST")
        {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        else if( strtoupper($this->request_method) == "PUT")
        {
            //curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        }
        else if( strtoupper($this->request_method) == "DELETE")
        {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        else if( strtoupper($this->request_method) == "HEAD")
        {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        else
        {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($this->request_method));
            $this->debug_dump("api (".$this->php_api."): set to CURLOPT_CUSTOMREQUEST method = ".strtoupper($this->request_method), "DEBUG");
        }

        if(!empty($this->request_http_proxy))
        {
            curl_setopt($ch, CURLOPT_PROXY, $this->request_http_proxy); // ex: 'tcp://192.168.0.2:3128'
            $this->debug_dump("api (".$this->php_api."): PROXY mode ON", "DEBUG");
        }
        if(is_numeric($this->request_http_timeout))
        {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->request_http_timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->request_http_timeout);
            $this->debug_dump("api (".$this->php_api."): TIMEOUT mode ON", "DEBUG");
        }

        $out = curl_exec($ch);
        $this->debug_dump("api (".$this->php_api."): completed", "INFO");
        if($out === false) $this->debug_dump("api (".$this->php_api."): request failed", "ERROR");
        //echo "out"; var_dump($out);
        //echo "ch"; var_dump($ch);
        //echo "chinfo"; var_dump(curl_getinfo($ch));
        //echo "error"; var_dump(curl_error($ch));

        $response = $out;
        $this->debug_dump("api (".$this->php_api."): out = ".$out, "DEBUG");
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->debug_dump("api (".$this->php_api."): header_size = ".$header_size, "DEBUG");
        $header = substr($response, 0, $header_size);
        $this->debug_dump("api (".$this->php_api."): http_response_header = ".print_r($header,true), "DEBUG");
        $body = substr($response, $header_size);
        $this->debug_dump("api (".$this->php_api."): http_response_body = ".print_r($body,true), "DEBUG");
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        $this->response_body = $body;
        $this->response_code = $code;
        // get headers
        $header = explode("\r\n", $header);
        for ($i = 1; $i < count($header); $i++) // skip first line
        {
            if(strpos($header[$i], ": ") !== false)
            {
                $this->response_header[] = explode(": ", $header[$i]);
                $this->debug_dump("api (".$this->php_api."): reponse header type 1 parsed", "DEBUG");
            }
            else if(strpos($header[$i], ":") !== false)
            {
                $this->response_header[] = explode(":", $header[$i]);
                $this->debug_dump("api (".$this->php_api."): reponse header type 2 parsed", "DEBUG");
            }
            else if($header[$i] != "")
            {
                $this->response_header[] = $header[$i];
                $this->debug_dump("api (".$this->php_api."): reponse header type 3 parsed", "DEBUG");
            }
        }
    }

    private function make_request_gnu_curl()
    {
        if($this->request_header == null)
        {
            $this->request_header = array();
        }

        if( $this->is_json($this->request_body) )
        {
            $this->request_header[] = array("Content-type", "application/json");
            $this->debug_dump("api (".$this->php_api."): JSON mode ON", "DEBUG");
        }
        else if( $this->request_body != null && $this->request_body != "" )
        {
            $this->request_header[] = array("Content-type", "application/x-www-form-urlencoded");
            $this->debug_dump("api (".$this->php_api."): FORM mode ON", "DEBUG");
        }
        if( $this->request_body == null )
        {
            $this->request_body = "";
            $this->debug_dump("api (".$this->php_api."): body initialized with empty string", "DEBUG");
        }

        $stringHeader = array();

        foreach ($this->request_header as $header)
        {
            if( is_array($header) && count($header) == 2 )
                $stringHeader[] = $header[0] . ": " . $header[1];
            else
                $stringHeader[] = $header;
        }
        $this->debug_dump("api (".$this->php_api."): header = ".print_r($stringHeader, true), "DEBUG");

        // sanitize (because it will be executed on os shell)
        $this->request_url = str_replace("'", "", $this->request_url);
        $this->request_method = str_replace("'", "", $this->request_method);
        foreach ($this->request_header as $key => $value) $this->request_header[$key] = str_replace("'", "", $value);
        $this->request_http_proxy = str_replace("'", "", $this->request_http_proxy);
        $this->debug_dump("api (".$this->php_api."): dump after sanitized for shell = ".print_r($this, true), "DEBUG");


        $curl_short = "curl '".$this->request_url."' ";
        $curl_long  = "curl '".$this->request_url."' ";
        $wget_short = "wget '".$this->request_url."' ";
        $wget_long  = "wget '".$this->request_url."' ";

        // request method (note that wget can only use GET method)
        $curl_short .= "-X        '".$this->request_method."' ";
        $curl_long  .= "--request '".$this->request_method."' ";

        // request header
        foreach ($stringHeader as $header) {
            $curl_short .= "-H       '".$header."' ";
            $curl_long  .= "--header '".$header."' ";
            $wget_short .= "--header '".$header."' ";
            $wget_long  .= "--header '".$header."' ";
        }

        // request body
        $file_temp_name_request_body = tempnam(sys_get_temp_dir(), "req_body_curl_");
        $file_put_contents = file_put_contents($file_temp_name_request_body, $this->request_body);
        $this->debug_dump("api (".$this->php_api."): saving request_body to temp file: ".$file_temp_name_request_body." , bytes written: ".print_r($file_put_contents,true), "DEBUG");

        $curl_short .= "--data '@".$file_temp_name_request_body."' ";
        $curl_long  .= "--data '@".$file_temp_name_request_body."' ";
        $wget_short .= "--post-file='".$file_temp_name_request_body."' ";
        $wget_long  .= "--post-file='".$file_temp_name_request_body."' ";

        // ssl
        if($this->ssl_verify && $this->is_https())
        {
            $temp = $this->ssl_cacert_file_path();
            if(!empty($temp) && file_exists($this->ssl_cacert_file_path()))
            {
                $curl_short .= "--cacert '".$this->ssl_cacert_file_path()."' ";
                $curl_long  .= "--cacert '".$this->ssl_cacert_file_path()."' ";
                $wget_short .= "--ca-certificate='".$this->ssl_cacert_file_path()."' ";
                $wget_long  .= "--ca-certificate='".$this->ssl_cacert_file_path()."' ";
                $this->debug_dump("api (".$this->php_api."): SSL mode ON", "DEBUG");
            }
        }
        else if(!$this->ssl_verify && $this->is_https())
        {
            $curl_short .= "-k ";
            $curl_long  .= "--insecure ";
            $wget_short .= "--no-check-certificate ";
            $wget_long  .= "--no-check-certificate ";
            $this->debug_dump("api (".$this->php_api."): SSL mode OFF", "DEBUG");
        }

        // proxy
        if(!empty($this->request_http_proxy))
        {
            $curl_short .= "-x      '".$this->request_http_proxy."' "; // ex: '-x      192.168.0.2:3128'
            $curl_long  .= "--proxy '".$this->request_http_proxy."' "; // ex: '--proxy 192.168.0.2:3128'
            $wget_short .= "-e        use_proxy=yes -e        http_proxy='".$this->request_http_proxy."' ";
            $wget_long  .= "--execute use_proxy=yes --execute http_proxy='".$this->request_http_proxy."' ";
            $wget_short = "http_proxy='".$this->request_http_proxy."' ".$wget_short;
            $wget_long  = "http_proxy='".$this->request_http_proxy."' ".$wget_long;
            $this->debug_dump("api (".$this->php_api."): PROXY mode ON", "DEBUG");
        }

        // timeout
        if(is_numeric($this->request_http_timeout))
        {
            $curl_short .= "-m         '".$this->request_http_timeout."' --connect-timeout '".$this->request_http_timeout."' "; // ex: '-m         5 --connect-timeout 5'
            $curl_long  .= "--max-time '".$this->request_http_timeout."' --connect-timeout '".$this->request_http_timeout."' "; // ex: '--max-time 5 --connect-timeout 5'
            $wget_short .= "--timeout='".$this->request_http_timeout."' ";
            $wget_long  .= "--timeout='".$this->request_http_timeout."' ";
            $this->debug_dump("api (".$this->php_api."): TIMEOUT mode ON", "DEBUG");
        }

        // response body
        $file_temp_name_response_body = tempnam(sys_get_temp_dir(), "resp_body_curl_");
        $this->debug_dump("api (".$this->php_api."): directing response_body to temp file: ".$file_temp_name_response_body, "DEBUG");

        $curl_short .= "-o       '".$file_temp_name_response_body."' ";
        $curl_long  .= "--output '".$file_temp_name_response_body."' ";
        $wget_short .= "-O                '".$file_temp_name_response_body."' ";
        $wget_long  .= "--output-document '".$file_temp_name_response_body."' ";

        // response code
        $curl_short .= "-w          %{http_code} ";
        $curl_long  .= "--write-out %{http_code} ";
        // wget does not show response code (must be parsed manually)

        // response header
        $file_temp_name_response_header = tempnam(sys_get_temp_dir(), "resp_header_curl_");
        $this->debug_dump("api (".$this->php_api."): directing response_header to temp file: ".$file_temp_name_response_header, "DEBUG");

        $curl_short .= "-D            '".$file_temp_name_response_header."' ";
        $curl_long  .= "--dump-header '".$file_temp_name_response_header."' ";
        // wget does not show response header (must be parsed manually)

        if(!is_null($this->debug))
        {
            $file_temp_name_stderr = tempnam(sys_get_temp_dir(), "stderr_");
            $curl_short .= " 2> '".$file_temp_name_stderr."' ";
            $curl_long  .= " 2> '".$file_temp_name_stderr."' ";
            $wget_short .= " 2> '".$file_temp_name_stderr."' ";
            $wget_long  .= " 2> '".$file_temp_name_stderr."' ";
            $this->debug_dump("api (".$this->php_api."): directing STDERR to temp file: ".$file_temp_name_stderr, "DEBUG");
        }

        $this->debug_dump("api (".$this->php_api."): shell command curl_short (used) = ".$curl_short, "DEBUG");
        $this->debug_dump("api (".$this->php_api."): shell command curl_long (alternative 1) = ".$curl_long, "DEBUG");
        $this->debug_dump("api (".$this->php_api."): shell command wget_short (just for comparison, not feature complete) = ".$wget_short, "DEBUG");
        $this->debug_dump("api (".$this->php_api."): shell command wget_long (just for comparison, not feature complete) = ".$wget_long, "DEBUG");
        $stdout = shell_exec($curl_short);
        $this->debug_dump("api (".$this->php_api."): completed", "INFO");
        if(!empty($file_temp_name_stderr) && is_string($file_temp_name_stderr))
        {
            $this->debug_dump("api (".$this->php_api."): STDERR content: ".file_get_contents($file_temp_name_stderr), "WARNING");
        }

        if(is_numeric($stdout)) $this->response_code = $stdout;
        else $this->debug_dump("api (".$this->php_api."): stdout not numeric = ".$stdout, "ERROR");
        $this->response_body = file_get_contents($file_temp_name_response_body);
        if($this->response_body === false) $this->debug_dump("api (".$this->php_api."): cannot read temp file response_body", "ERROR");
        $this->response_header = file_get_contents($file_temp_name_response_header);
        if($this->response_header === false) $this->debug_dump("api (".$this->php_api."): cannot read temp file response_header", "ERROR");

        // cleanup
        $unlink = unlink($file_temp_name_response_body);
        if($unlink === false) $this->debug_dump("api (".$this->php_api."): cannot delete temp file response_body", "WARNING");
        $unlink = unlink($file_temp_name_request_body);
        if($unlink === false) $this->debug_dump("api (".$this->php_api."): cannot delete temp file request_body", "WARNING");
        $unlink = unlink($file_temp_name_response_header);
        if($unlink === false) $this->debug_dump("api (".$this->php_api."): cannot delete temp file response_header", "WARNING");

    }

    private function is_json($string)
    {
        json_decode($string);
        $notError = (json_last_error() == JSON_ERROR_NONE);
        $firstLetter = substr($string, 0, 1);
        return ($notError && $firstLetter == "{");
    }

    private function is_https()
    {
        $fiveFirstCharInUrl = strtoupper(substr($this->request_url, 0, 5));
        $isHttps = $fiveFirstCharInUrl == "HTTPS";
        return $isHttps;
    }

    protected function ssl_cacert_file_path()
    {
        $result = getcwd() . $this->ssl_cacert_file;
        if(file_exists($result)) return $result;
        else return null;
    }

    protected function ssl_cacert_directory_path()
    {
        $result = getcwd() . $this->ssl_cacert_directory;
        if(file_exists($result)) return $result;
        else return null;
    }

    protected function debug_dump($string, $level)
    {
        if( $this->map_debug_level[$level] > $this->map_debug_level[$this->debug_level] ) return;

        if(is_null($this->debug)) return;
        else if($this->debug == "echo") { echo $string."\r\n"; return; }
        else if($this->debug == "var") { $this->debug_output .= $string."\r\n" ; return; }
        else if(is_callable($this->debug)) { $func = $this->debug; $func($string); return; }
    }
}
