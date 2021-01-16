# zabbixgraphapi

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
