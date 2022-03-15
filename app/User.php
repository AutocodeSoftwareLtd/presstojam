<?php
namespace PresstoJam;

class User {

    private $client;

    function __construct($client) {
        $this->client = $client;
    }


    function getUser() {
        $response = $this->client->get("/check-user");
        return $response->user;
    }


    function login($username, $password) {
        $res = $this->client->post("/accounts-login", [
            "username"=>$username, 
            "password"=>$password]);
       
    }
}