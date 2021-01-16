<?php

  /*
  Author: https://www.facebook.com/ITKnowledgeMemorandum-536520816847831
  Test on Zabbix 5.0 & php 7.4 (Other vsesion is also compatibility)

  Special thank:
  https://www.zabbix.com/forum/zabbix-troubleshooting-and-problems/371254-zabbix-4-0-3-changes-in-api-behaviour
  https://www.stevenrombauts.be/2018/06/read-json-request-data-with-php/
  */

  /*
  How to using
    - Place this file in the same directory of zabbix web ui e.g.: /usr/share/zabbix
    Or for security concern or using this API in other server or directory please edit line no. 182 manulally to your Zabbix web ui

    - Requires request parameters as below
      "format": can be one of ("raw" = Raw PNG binary) or ("http" = PNG with HTTP Content-Type) or ("base64" = Base64 in JSON response)
      "authtype": can be one of ("userpass" = user and password are requires) or ("token" = auth is require)
      "graphid": is require
    - Optional request parameters as below (some special)
      "from": example are "2021-01-15 00:00:00" or now-24h or now-1d/d or zabbix standard
      "to": example are "now-1h" or "2021-01-15 00:00:00" or now-1d/d or zabbix standard
  */

  /*
  # Request
  {
      "jsonrpc": "2.0",
      "method": "graph.image",
      "params": {
          "format": "raw/http/base64",
          "authtype": "userpass/token",
  	      "user": "",
          "password": "",
          "width": 800,
          "height": 200,
          "from": "2021-01-15 00:00:00",
          "to": "now-1h",
          "graphid": 0
      },
      "error": {
        "code": 0,
        "message": null,
        "data": null
      },
      "id": 1,
      "auth": null
  }

  # Response
  {
      "jsonrpc": "2.0",
      "result": {
          "image": ""
      },
      "id": 1
  }

  */

  function url_origin( $s, $use_forwarded_host = false ) {
      $ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
      $sp       = strtolower( $s['SERVER_PROTOCOL'] );
      $protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
      $port     = $s['SERVER_PORT'];
      $port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
      $host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
      $host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
      return $protocol . '://' . $host;
  }

  function full_url( $s, $use_forwarded_host = false ) {
      return url_origin( $s, $use_forwarded_host ) . $s['REQUEST_URI'];
  }

  function response_out($json_response_out, $refid, $errcode) {
    header('Content-Type: application/json');
    $json_response_out["id"] = $refid;
    echo json_encode($json_response_out);
    exit($errcode);
  }

  function userpass_logout($upjson_request, $refid, $uptoken, $upcrequest_url) {
    //Logout if authtype = userpass
    if (isset($upjson_request["params"]["authtype"]) && $upjson_request["params"]["authtype"] == "userpass") {
      $postvars = '{"jsonrpc": "2.0", "method": "user.logout", "params": [], "id": '. $refid .', "auth": "'.$uptoken.'"}';

      $ch = curl_init();
      $url = $upcrequest_url."/api_jsonrpc.php";
      curl_setopt($ch,CURLOPT_URL,$url);
      curl_setopt($ch,CURLOPT_POST, 1);                //0 for a get request
      curl_setopt($ch,CURLOPT_POSTFIELDS,$postvars);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,2);
      curl_setopt($ch,CURLOPT_TIMEOUT, 10);
      curl_setopt($ch,CURLOPT_HTTPHEADER, array('Content-Type: application/json-rpc'));
      $response = curl_exec($ch);

      if (curl_errno($ch)) {
        $json_response["error"]["code"] = -99010;
        $json_response["error"]["message"] = "Userpass logout request error.";
        $json_response["error"]["data"] = "Failed to request logout Userpass.";
        curl_close ($ch);
        response_out($json_response, $refid, $json_response["error"]["code"]);

      }

      curl_close ($ch);
    }
  }

  $json_response_raw = '{
    "jsonrpc": "2.0",
    "result": {
      "image": null
    },
    "error": {
      "code": 0,
      "message": null,
      "data": null
    },
    "id": 0
  }';

  $json_response = json_decode($json_response_raw,true);

  // Only allow POST requests
  if (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') {
    $json_response["error"]["code"] = -99001;
    $json_response["error"]["message"] = "Invalid HTTP request method.";
    $json_response["error"]["data"] = "Only POST requests are allowed";
    response_out($json_response, 0, $json_response["error"]["code"]);
  }

  // Make sure Content-Type is application/json
  $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
  if (stripos($content_type, 'application/json-rpc') === false) {
    $json_response["error"]["code"] = -99002;
    $json_response["error"]["message"] = "Invalid HTTP request content type.";
    $json_response["error"]["data"] = "Content-Type must be application/json-rpc.";
    response_out($json_response, 0, $json_response["error"]["code"]);
  }

  // Read the input stream
  $json_request_raw = file_get_contents("php://input");

  // Decode the JSON object
  $json_request = json_decode($json_request_raw, true);

  // Throw an exception if decoding failed
  if (!is_array($json_request)) {
    $json_response["error"]["code"] = -99003;
    $json_response["error"]["message"] = "Invalid JSON request content body.";
    $json_response["error"]["data"] = "Failed to decode JSON object.";
    response_out($json_response, 0, $json_response["error"]["code"]);
  }

  // Check id request parameter
  $req_id = 0;
  if (isset($json_request["id"]) && filter_var($json_request["id"] ,FILTER_VALIDATE_INT) == $json_request["id"]) {
    $req_id = $json_request["id"];
  }

  // Check output format
  $req_output_format = "base64";
  if (isset($json_request["params"]["format"]) && $json_request["params"]["format"] == "base64") {
    $req_output_format = "base64";
  } else if (isset($json_request["params"]["format"]) && $json_request["params"]["format"] == "http") {
    $req_output_format = "http";
  } else if (isset($json_request["params"]["format"]) && $json_request["params"]["format"] == "raw") {
    $req_output_format = "raw";
  } else {
    $json_response["error"]["code"] = -99005;
    $json_response["error"]["message"] = "Invalid output format request type.";
    $json_response["error"]["data"] = "Failed to set image output format type. (base64/http/raw)";
    response_out($json_response, $req_id, $json_response["error"]["code"]);
  }

  //Get request url or seeting munually for secirity concern or specific path for zabbix url
  //$crequest_url = "https://yourzabbixurl"   // No tail slash required
  $crequest_url = dirname(full_url( $_SERVER ));

  if (!filter_var($crequest_url, FILTER_VALIDATE_URL)) {
    $json_response["error"]["code"] = -99011;
    $json_response["error"]["message"] = "Invalid calling url.";
    $json_response["error"]["data"] = "Invalid calling url manual/auto";
    response_out($json_response, $req_id, $json_response["error"]["code"]);
  }

  // Authentication
  if (isset($json_request["params"]["authtype"]) && $json_request["params"]["authtype"] == "userpass") {
    // Username & Password authentication with WebUI
    if (isset($json_request["params"]["user"]) && filter_var($json_request["params"]["user"], FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/[a-zA-Z0-9]+/"))) == preg_replace('/[^A-Za-z0-9]/', '', $json_request["params"]["user"]) &&  isset($json_request["params"]["password"])) {
      $postvars = '{"jsonrpc": "2.0", "method": "user.login", "params": {"user": "'.preg_replace('/[^A-Za-z0-9]/', '', $json_request["params"]["user"]).'", "password": "'.$json_request["params"]["password"].'"}, "id": '. $req_id .', "auth": null}';

      $ch = curl_init();
      $url = $crequest_url."/api_jsonrpc.php";
      curl_setopt($ch,CURLOPT_URL,$url);
      curl_setopt($ch,CURLOPT_POST, 1);                //0 for a get request
      curl_setopt($ch,CURLOPT_POSTFIELDS,$postvars);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,2);
      curl_setopt($ch,CURLOPT_TIMEOUT, 10);
      curl_setopt($ch,CURLOPT_HTTPHEADER, array('Content-Type: application/json-rpc'));
      $response = curl_exec($ch);
      if (curl_errno($ch)) {
        $json_response["error"]["code"] = -99008;
        $json_response["error"]["message"] = "Userpass login request error.";
        $json_response["error"]["data"] = "Failed to request login Userpass.";
        curl_close ($ch);
        response_out($json_response, $req_id, $json_response["error"]["code"]);
      }

      curl_close ($ch);

      $obj = json_decode($response);
      $token = $obj->{'result'};

    } else {
      $json_response["error"]["code"] = -99005;
      $json_response["error"]["message"] = "Invalid authtype userpass request parameter.";
      $json_response["error"]["data"] = "Parameters user and password requires for userpass Authentication Type. (user/password)";
      response_out($json_response, $req_id, $json_response["error"]["code"]);
    }

  } else if (isset($json_request["params"]["authtype"]) && $json_request["params"]["authtype"] == "token") {
    // Already token
    if (isset($json_request["auth"]) && filter_var($json_request["auth"], FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/[a-zA-Z0-9]+/"))) == preg_replace('/[^A-Za-z0-9]/', '', $json_request["auth"])) {
      $token = preg_replace('/[^A-Za-z0-9]/', '', $json_request["auth"]);
    } else {
      $json_response["error"]["code"] = -99006;
      $json_response["error"]["message"] = "Invalid authtype token request parameter.";
      $json_response["error"]["data"] = "Parameter auth requires for token Authentication Type. (auth)";
      response_out($json_response, $req_id, $json_response["error"]["code"]);
    }

  } else {
    $json_response["error"]["code"] = -99004;
    $json_response["error"]["message"] = "Invalid authtype request type.";
    $json_response["error"]["data"] = "Failed to select Authentication Type. (userpass/token)";
    response_out($json_response, $req_id, $json_response["error"]["code"]);
  }

  if (isset($json_request["params"]["graphid"]) && filter_var($json_request["params"]["graphid"] ,FILTER_VALIDATE_INT) == $json_request["params"]["graphid"]) {

    // Get graph image
    $postvars = [
      'graphid' => 0,
      'from' => "now-1h",
      'to' => "now",
      'width'   => 800,
      'profileIdx' => "web.graphs.filter",
    ];

    $postvars["graphid"] = filter_var($json_request["params"]["graphid"] ,FILTER_VALIDATE_INT);
    $postvars["from"] = (isset($json_request["params"]["from"]) && filter_var($json_request["params"]["from"] ,FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/[a-zA-Z0-9]+/"))) == preg_replace('/[^A-Za-z0-9 \-:\/]/', '', $json_request["params"]["from"])) ? preg_replace('/[^A-Za-z0-9 \-:\/]/', '', $json_request["params"]["from"]) : "now-1h";
    $postvars["to"] = (isset($json_request["params"]["to"]) && filter_var($json_request["params"]["to"] ,FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/[a-zA-Z0-9]+/"))) == preg_replace('/[^A-Za-z0-9 \-:\/]/', '', $json_request["params"]["to"])) ? preg_replace('/[^A-Za-z0-9 \-:\/]/', '', $json_request["params"]["to"]) : "now";
    $postvars["width"] = (isset($json_request["params"]["width"]) && filter_var($json_request["params"]["width"] ,FILTER_VALIDATE_INT) == $json_request["params"]["width"]) ? $json_request["params"]["width"] : 800;

    if (isset($json_request["params"]["height"]) && filter_var($json_request["params"]["height"] ,FILTER_VALIDATE_INT) == $json_request["params"]["height"]) {
      $postvars["height"] = $json_request["params"]["height"];
    }

    $ch = curl_init();
    $url = $crequest_url."/chart2.php";
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_POST, 1);                //0 for a get request
    curl_setopt($ch,CURLOPT_POSTFIELDS,$postvars);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,2);
    curl_setopt($ch,CURLOPT_TIMEOUT, 10);
    curl_setopt($ch,CURLOPT_HTTPHEADER, array('Cookie: zbx_sessionid='. $token));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      $json_response["error"]["code"] = -99009;
      $json_response["error"]["message"] = "Chart2 request error.";
      $json_response["error"]["data"] = "Failed to request Chart2.";
      curl_close ($ch);
      userpass_logout($json_request, $req_id, $token, $crequest_url);
      response_out($json_response, $req_id, $json_response["error"]["code"]);
    }

    //Setting output format
    if ($req_output_format == "base64") {
      $json_response["result"]["image"] = base64_encode($response);
    } else if ($req_output_format == "http") {
      header ('Content-Type: image/png');
      print $response;
      userpass_logout($json_request, $req_id, $token, $crequest_url);
      exit(0);
    } else if ($req_output_format == "raw") {
      print $response;
      exit(0);
    }

    //Example using base64 in HTML code
    //print "<img style='display:block; width:100px;height:100px;' id='base64image' src='data:image/png;base64, " . base64_encode($response) . "' />";
    curl_close ($ch);
    userpass_logout($json_request, $req_id, $token, $crequest_url);

  } else {
    $json_response["error"]["code"] = -99007;
    $json_response["error"]["message"] = "Invalid graphid request parameter.";
    $json_response["error"]["data"] = "Parameter graphid require for get graph image. (graphid)";
    userpass_logout($json_request, $req_id, $token, $crequest_url);
    response_out($json_response, $req_id, $json_response["error"]["code"]);

  }

  response_out($json_response, $req_id, 0);

?>
