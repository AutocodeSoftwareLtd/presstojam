<?php
namespace PressToJam;

class User {

    private $client;

    function __construct($client) {
        $this->client = $client;
    }


    function getUser() {
        $response = $this->client->get("/core/check-user");
        return $response["user"];
    }


    function login($username, $password) {
        $res = $this->client->post("/data/accounts/login", [
            "username"=>$username, 
            "password"=>$password
        ]);
        return $res;
    }
}