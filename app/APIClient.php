<?php

namespace PresstoJam;

class APIClient {

    private $domain;
    private $verify_ssl = false;
    private $bearer_token;
    private $cookies;
    private $headers;
    private $debug = false;
    private $custom_headers=[];
    private $status_cbs=[];
    private $referer;
 
    function __construct($domain) {
        $this->domain = rtrim($domain, "/");
        if (!isset($_SESSION)) session_start();
        if (isset($_SESSION['api_cookies'])) {
            $this->cookies = unserialize($_SESSION['api_cookies']);
        }
    }

    function __set($name, $value) {
        if (property_exists($this, $name)) $this->$name = $value;
    }


    function __get($name) {
        return (property_exists($this, $name)) ? $this->$name : null;
    }

    function regHeader($header, $value) {
        $this->custom_headers[$header] = $header . ": " . $value;
    }

    function regStatusCB($status, $cb) {
        $this->status_cbs[$status] = $cb;
    }

    function clearCookies() {
        if (isset($_SESSION['api_cookies'])) {
            unset($_SESSION['api_cookies']);
        }
    }

    function getApiAuth() {
        return $this->cookies["api-auth"];
    }

   
    private function parseCookieLine($cookie_line) {
        $line = trim(str_ireplace("Set-Cookie:", "", $cookie_line));
        $cookies = [];
        parse_str(strtr($line, array('&' => '%26', '+' => '%2B', ';' => '&')), $cookies);
        if (isset($cookies["api-auth"])) $this->cookies["api-auth"] = trim($cookies["api-auth"]);
        if (isset($cookies["api-refresh"])) $this->cookies["api-refresh"] = trim($cookies["api-refresh"]);
    }

    private function readCookies($header) {
        $lines = explode("\n", $header);
        $headers = array();
        $body = "";
        foreach($lines as $num => $line){
            $l = str_replace("\r", "", $line);
            //Empty line indicates the start of the message body and end of headers
            if(trim($l) == ""){
                $headers = array_slice($lines, 0, $num);
                $body = $lines[$num + 1];
                //Pull only cookies out of the headers
                $cookies = preg_grep('/^Set-Cookie:/i', $headers); //add i flag for case insensitive
                foreach($cookies as $num=>$cookie_line) {
                    $this->parseCookieLine($cookie_line);
                }
                $_SESSION['api_cookies'] = serialize($this->cookies);
                break;
            }
        }
    }

    private function sendCookie() {
        $url = $this->domain .  "/core-switch-tokens";

        $auth_headers=array('Accept: application/json');
        if ($this->bearer_token) $auth_headers[] = "Authorization: Bearer " . $this->bearer_token;

        $auth_headers = array_merge($auth_headers, $this->custom_headers);

        $cookie_str = "api-refresh=" . $this->cookies["api-refresh"];
      
        $ch =  curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_str);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_REFERER, $this->referer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $auth_headers);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        $response = @curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $this->readCookies($header);
        return $ch;
    }


    private function init($url) {
        $url = $this->domain . "/" . ltrim($url, "/");
        $auth_headers=array('Accept: application/json');
        if ($this->bearer_token) $auth_headers[] = "Authorization: Bearer " . $this->bearer_token;

        $auth_headers = array_merge($auth_headers, $this->custom_headers);

        $ch =  curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_REFERER, $this->referer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $auth_headers);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        if (isset($this->cookies["api-auth"])) {
            echo "\n\n-----------------------------Sending Cookies!!!-----\n\n";
            $cookie_str = "api-auth=" . $this->cookies["api-auth"];
            curl_setopt($ch, CURLOPT_COOKIE, $cookie_str);
        }
        return $ch;
    }


    private function processCurl($ch) {
        $response = @curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
       
        if (isset($this->status_cbs[$status])) {
            $this->status_cbs[$status]($response);
        } else if ($status == "401") {
            $this->sendCookie();
            $response = curl_exec($ch);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

            //now try again
            $response = curl_exec($ch);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } 
        
        $header = substr($response, 0, $header_size);
        $this->readCookies($header);
        $body = substr($response, $header_size);
        curl_close($ch);
        return $body;
    }

    public function processCurlAsJSON($response) {
        $result = null;
        if ($response) {
           $result = json_decode($response, true);
        }

        if ($this->debug) {
            var_dump($this->headers);
        } else if ($result === null) {
            switch(json_last_error()) {
                case JSON_ERROR_DEPTH:
                    echo ' - Maximum stack depth exceeded';
                break;
                case JSON_ERROR_CTRL_CHAR:
                    echo ' - Unexpected control character found';
                break;
                case JSON_ERROR_SYNTAX:
                    echo ' - Syntax error, malformed JSON';
                break;
                case JSON_ERROR_NONE:
                    echo ' - No errors';
                break;
            }
           // $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
           // var_dump(curl_getinfo($ch));
            echo "<br>An error has occured with the API: ::";
            var_dump($response);
            echo "::";
            exit;
        }
        return $result;
    }


    public function get($url, $data=null)
    {
        if ($data) {

            if (strpos($url, "?") === false) {
                $url .= "?";
            } else {
                $url .= "&";
            }

            $url .= http_build_query($data);
        }

        $ch =  $this->init($url);
        $response = $this->processCurl($ch);
        return $this->processCurlAsJSON($response);
    }


    public function getRaw($url, $data=null)
    {
        if ($data) {

            if (strpos($url, "?") === false) {
                $url .= "?";
            } else {
                $url .= "&";
            }

            $url .= http_build_query($data);
        }

        $ch =  $this->init($url);
        $response = $this->processCurl($ch);
        return $response;
    }

    public function post($url, $data = [], $raw = false)
    {
        $ch =  $this->init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
        $response = $this->processCurl($ch);
        if ($raw) return $response;
        else return $this->processCurlAsJSON($response);
    }

    public function put($url, $data)
    {
        $ch =  $this->init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
        $response = $this->processCurl($ch);
        return $this->processCurlAsJSON($response);
    }

    public function delete($url, $data)
    {
        $ch =  $this->init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
        $response = $this->processCurl($ch);
        return $this->processCurlAsJSON($response);
    }


    public function pushAsset($url, $data, $blob) {
        $auth_headers=array('Accept: application/json');
        $ch =  $this->init($url);
        $data["__blob"] = $blob;
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
        $response = $this->processCurl($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($ch) curl_close($ch);
        if ($response) {
            return json_decode($response, true);
        } else {
            return null;
        }
    }


    public function getAsset($url, $data) {
        if ($data) {
            if (strpos($url, "?") === false) {
                $url .= "?";
            } else {
                $url .= "&";
            }

            $url .= http_build_query($data);
        }
        $ch =  $this->init($url);
        $response = $this->processCurl($ch);
        return $response;
    }


    function getAsData($arr) {
        if (!$arr) return array();
        else if (isset($arr['__data'])) return $arr['__data'];
        else return $arr;
    }
}
