<?php
namespace GenerCodeDev;

class User {

    private $client;

    function __construct($client) {
        $this->client = $client;
    }


    function getUser() {
        $response = $this->client->get("/user/check-user");
        return $response["name"];
    }


    function login($username, $password) {
        $res = $this->client->post("/user/login/accounts", [
            "email"=>$username, 
            "password"=>$password
        ]);
        return $res;
    }
}