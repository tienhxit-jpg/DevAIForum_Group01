<?php

class Router
{
    private $routes = [];

    public function get($uri, $action)
    {
        $this->routes['GET'][$uri] = $action;
    }

    public function post($uri, $action)
    {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch($uri, $method)
    {
        if (isset($this->routes[$method][$uri])) {
            $action = $this->routes[$method][$uri];

            [$controller, $function] = explode('@', $action);

            require_once __DIR__ . '/../Controllers/' . $controller . '.php';

            $controllerObject = new $controller();

            return $controllerObject->$function();
        }

        http_response_code(404);
        echo "404 - Page Not Found";
    }
}