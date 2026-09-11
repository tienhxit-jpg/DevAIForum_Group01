<?php

require_once __DIR__ . '/../Core/Session.php';

class Auth
{
    public static function login($userId)
    {
        Session::set('user_id', $userId);
    }

    public static function check()
    {
        return Session::has('user_id');
    }

    public static function userId()
    {
        return Session::get('user_id');
    }

    public static function logout()
    {
        Session::destroy();
    }
}