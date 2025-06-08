<?php
/**
 * Router Avanzado para Sistema MVC Sequoia Speed
 * Maneja routing RESTful con middleware y cache
 */

class AdvancedRouter 
{
    private $routes = [];
    private $middleware = [];
    private $cache;
    private $prefix = "";
    
    public function __construct() 
    {
        $this->cache = new CacheManager();
    }
    
    public function setPrefix($prefix) 
    {
        $this->prefix = rtrim($prefix, "/");
        return $this;
    }
    
    public function middleware($middleware) 
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        return $this;
    }
    
    public function get($path, $callback, $middleware = []) 
    {
        $this->addRoute("GET", $path, $callback, $middleware);
        return $this;
    }
    
    public function post($path, $callback, $middleware = []) 
    {
        $this->addRoute("POST", $path, $callback, $middleware);
        return $this;
    }
    
    public function put($path, $callback, $middleware = []) 
    {
        $this->addRoute("PUT", $path, $callback, $middleware);
        return $this;
    }
    
    public function delete($path, $callback, $middleware = []) 
    {
        $this->addRoute("DELETE", $path, $callback, $middleware);
        return $this;
    }
    
    public function resource($name, $controller) 
    {
        $this->get("/$name", [$controller, "index"]);
        $this->get("/$name/create", [$controller, "create"]);
        $this->post("/$name", [$controller, "store"]);
        $this->get("/$name/{id}", [$controller, "show"]);
        $this->get("/$name/{id}/edit", [$controller, "edit"]);
        $this->put("/$name/{id}", [$controller, "update"]);
        $this->delete("/$name/{id}", [$controller, "destroy"]);
        return $this;
    }
    
    private function addRoute($method, $path, $callback, $middleware = []) 
    {
        $path = $this->prefix . $path;
        $this->routes[] = [
            "method" => $method,
            "path" => $path,
            "callback" => $callback,
            "middleware" => array_merge($this->middleware, $middleware),
            "pattern" => $this->pathToRegex($path)
        ];
    }
    
    private function pathToRegex($path) 
    {
        $pattern = preg_replace("/\{([^}]+)\}/", "([^/]+)", $path);
        return "#^" . str_replace("/", "\/", $pattern) . "$#";
    }
    
    public function dispatch() 
    {
        $method = $_SERVER["REQUEST_METHOD"];
        $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        
        // Intentar cache primero
        $cacheKey = "route_" . md5($method . $uri);
        if ($cachedResponse = $this->cache->get($cacheKey)) {
            return $cachedResponse;
        }
        
        foreach ($this->routes as $route) {
            if ($route["method"] === $method && preg_match($route["pattern"], $uri, $matches)) {
                array_shift($matches); // Remove full match
                
                // Ejecutar middleware
                foreach ($route["middleware"] as $middleware) {
                    if (!$this->runMiddleware($middleware)) {
                        return;
                    }
                }
                
                return $this->executeCallback($route["callback"], $matches);
            }
        }
        
        // 404 Not Found
        http_response_code(404);
        echo json_encode(["error" => "Route not found"]);
    }
    
    private function runMiddleware($middleware) 
    {
        if (is_callable($middleware)) {
            return $middleware();
        } elseif (class_exists($middleware)) {
            $instance = new $middleware();
            return $instance->handle();
        }
        return true;
    }
    
    private function executeCallback($callback, $params = []) 
    {
        if (is_callable($callback)) {
            return call_user_func_array($callback, $params);
        } elseif (is_array($callback)) {
            [$controller, $method] = $callback;
            if (is_string($controller)) {
                $controller = new $controller();
            }
            return call_user_func_array([$controller, $method], $params);
        }
    }
    
    public function getRoutes() 
    {
        return $this->routes;
    }
}