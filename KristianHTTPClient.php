<?php

class KristianHTTPClient
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

    // output
    public $response_code = 0; // 200, 404, ...
    public $response_body = "";
    public $response_header = array();

    public function execute()
    {
        $method_name = "make_request_" . $this->php_api;
        $this->$method_name();
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
        }
        else if(!$this->ssl_verify && $this->is_https())
        {
            //$options["http"]["verify_peer"] = false;
            //$options["http"]["verify_peer_name"] = false;
            $options["ssl"]["verify_peer"] = false;
            $options["ssl"]["verify_peer_name"] = false;
        }

        if(!empty($this->request_http_proxy))
        {
            $options["http"]["proxy"] = "tcp://".$this->request_http_proxy; // ex: 'tcp://192.168.0.2:3128'
            $options["http"]["request_fulluri"] = true;
        }
        if(is_numeric($this->request_http_timeout))
        {
            $options["http"]["timeout"] = $this->request_http_timeout;
        }

        // make request
        $this->response_body = file_get_contents($this->request_url, false, stream_context_create($options));
        // get code
        $status_line = $http_response_header[0]; // https://www.php.net/manual/en/reserved.variables.httpresponseheader.php
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $this->response_code = $match[1];
        // get headers
        for ($i = 1; $i < count($http_response_header); $i++) // skip first line
        {
            if(strpos($http_response_header[$i], ": ") !== false)
                $this->response_header[] = explode(": ", $http_response_header[$i]);
            else if(strpos($http_response_header[$i], ":") !== false)
                $this->response_header[] = explode(":", $http_response_header[$i]);
            else if($header[$i] != "")
                $this->response_header[] = $http_response_header[$i];
        }
    }

    private function make_request_curl()
    {
        $tryport = parse_url($this->request_url, PHP_URL_PORT);
        if(!empty($tryport) && empty($this->request_port)) $this->request_port = $tryport;

        if( $this->is_json($this->request_body) )
        {
            $this->request_header[] = array("Content-type", "application/json");
        }
        else if( $this->request_body != null && $this->request_body != "" )
        {
            $this->request_header[] = array("Content-type", "application/x-www-form-urlencoded");
        }
        
        $arrHeaders = array();
        foreach ($this->request_header as $header)
        {
            if( is_array($header) && count($header) == 2 )
                $arrHeaders[] = $header[0] . ": " . $header[1];
            else
                $arrHeaders[] = $header;
        }

        if( $this->request_body == null ) $this->request_body = "";

        $ch = curl_init($this->request_url);
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $arrHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => 0, // change to 1 to print http request and response
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
            }
            else
            {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
        }
        else
        {
            curl_setopt($ch, CURLOPT_PORT, 80);
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
        }

        if(!empty($this->request_http_proxy))
        {
            curl_setopt($ch, CURLOPT_PROXY, $this->request_http_proxy); // ex: 'tcp://192.168.0.2:3128'
        }
        if(is_numeric($this->request_http_timeout))
        {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->request_http_timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->request_http_timeout);
        }

        $out = curl_exec($ch);
        //echo "out"; var_dump($out);
        //echo "ch"; var_dump($ch);
        //echo "chinfo"; var_dump(curl_getinfo($ch));
        //echo "error"; var_dump(curl_error($ch));

        $response = $out;
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        $this->response_body = $body;
        $this->response_code = $code;
        // get headers
        $header = explode("\r\n", $header);
        for ($i = 1; $i < count($header); $i++) // skip first line
        {
            if(strpos($header[$i], ": ") !== false)
                $this->response_header[] = explode(": ", $header[$i]);
            else if(strpos($header[$i], ":") !== false)
                $this->response_header[] = explode(":", $header[$i]);
            else if($header[$i] != "")
                $this->response_header[] = $header[$i];
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
        }
        else if( $this->request_body != null && $this->request_body != "" )
        {
            $this->request_header[] = array("Content-type", "application/x-www-form-urlencoded");
        }
        if( $this->request_body == null ) $this->request_body = "";

        $stringHeader = array();

        foreach ($this->request_header as $header)
        {
            if( is_array($header) && count($header) == 2 )
                $stringHeader[] = $header[0] . ": " . $header[1];
            else
                $stringHeader[] = $header;
        }

        // sanitize (because it will be executed on os shell)
        $this->request_url = str_replace("'", "", $this->request_url);
        $this->request_method = str_replace("'", "", $this->request_method);
        foreach ($this->request_header as $key => $value) $this->request_header[$key] = str_replace("'", "", $value);
        $this->request_http_proxy = str_replace("'", "", $this->request_http_proxy);


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
        file_put_contents($file_temp_name_request_body, $this->request_body);
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
            }
        }
        else if(!$this->ssl_verify && $this->is_https())
        {
            $curl_short .= "-k ";
            $curl_long  .= "--insecure ";
            $wget_short .= "--no-check-certificate ";
            $wget_long  .= "--no-check-certificate ";
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
        }

        // timeout
        if(is_numeric($this->request_http_timeout))
        {
            $curl_short .= "-m         '".$this->request_http_timeout."' --connect-timeout '".$this->request_http_timeout."' "; // ex: '-m         5 --connect-timeout 5'
            $curl_long  .= "--max-time '".$this->request_http_timeout."' --connect-timeout '".$this->request_http_timeout."' "; // ex: '--max-time 5 --connect-timeout 5'
            $wget_short .= "--timeout='".$this->request_http_timeout."' ";
            $wget_long  .= "--timeout='".$this->request_http_timeout."' ";
        }

        // response body
        $file_temp_name_response_body = tempnam(sys_get_temp_dir(), "resp_body_curl_");
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
        $curl_short .= "-D            '".$file_temp_name_response_header."' ";
        $curl_long  .= "--dump-header '".$file_temp_name_response_header."' ";
        // wget does not show response header (must be parsed manually)

        $stdout = shell_exec($curl_short);

        if(is_numeric($stdout)) $this->response_code = $stdout;
        $this->response_body = file_get_contents($file_temp_name_response_body);
        $this->response_header = file_get_contents($file_temp_name_response_header);

        // cleanup
        unlink($file_temp_name_response_body);
        unlink($file_temp_name_request_body);
        unlink($file_temp_name_response_header);

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
}
