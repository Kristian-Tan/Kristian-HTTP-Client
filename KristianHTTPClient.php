<?php

class KristianHTTPClient
{
    // input
    public $request_url = "http://localhost/"; // http://kristian.ax.lt/api/handler.php
    public $request_header = array(); // ['Authorization' => 'Bearer vwhr....xxyz']
    public $request_method = "GET"; // GET/POST/DELETE/PATCH/PUT/HEAD/...
    public $request_body = null;
    public $php_api = "file_get_contents"; // file_get_contents ATAU curl
    public $ssl_cacert_file = "cacert.pem"; // use path to cacert file
    public $ssl_verify = true; // change to false to disable ssl verification (insecure)

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
        $options["http"] = array(
            'header'  => $stringHeader,
            'method'  => $this->request_method,
            'content' => $this->request_body,
            'ignore_errors' => true, // silence warning if response code = 404
        );
        if($this->ssl_verify && $this->is_https())
        {
            $options["http"] = array(
                'cafile'  => $this->ssl_cacert_file_path(),
                'verify_peer'  => true,
                'verify_peer_name' => true,
            );
        }

        // make request
        $this->response_body = file_get_contents($this->request_url, false, stream_context_create($options));
        // get code
        $status_line = $http_response_header[0];
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
        $arrHeaders = array();
        foreach ($this->request_header as $header)
        {
            if( is_array($header) && count($header) == 2 )
                $arrHeaders[] = $header[0] . ": " . $header[1];
            else
                $arrHeaders[] = $header;
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
                curl_setopt ($ch, CURLOPT_CAINFO, $this->ssl_cacert_file_path());
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

        if( $this->request_body != null && $this->request_body != "")
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request_body);
        }

        $out = curl_exec($ch);
        //var_dump($out);
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
        return getcwd() . $this->ssl_cacert_file;
    }
}