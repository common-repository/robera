<?php


namespace Recommender;


use Firebase\JWT\JWT;

class utils
{
    public static function randomString($count=32) {
        $character_pool = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@$%^&*()_=~`:;./[]{}|";
        $str = "";
        for ($i = 0; $i < $count; $i++)
            $str .= $character_pool[rand(0, strlen($character_pool) - 1)];
        return $str;
    }

    public static function generateInteractionIds($count, $site_name, $secret, $length=32) {
        $interactionIds = array();
        for ($i = 0; $i < $count; $i++) {
            $interactionId = self::randomString($length);
            $payload = array(
                "site_name" => $site_name,
                "interaction_id" => $interactionId
            );

            $jwt = JWT::encode($payload, $secret);
            $interactionIds[$i] = array(
                "interaction_id" => $interactionId,
                "jwt" => $jwt
            );
        }
        return $interactionIds;
    }

}