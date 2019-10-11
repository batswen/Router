<?php

/**
 * @author      Swen Rühl
 * @license     MIT public license
 */

class Router {
    private $routes = [];
    private $beforeroutes = [];
    private $error404 = null;
    private $controllerpath = null;
    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct($controllerpath) {
        $this->routes = [];
        $this->beforeroutes = [];
        $this->add404(function() { });
        $this->controllerpath = $controllerpath;
    }

    /**
     * REturns base directory
     * 
     * @return string basedir
     */
    public static function getBaseDir() {
        return substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME']) - 9);
    }

    /**
     * @param string $path  The last part of the URL
     * @return string       The URL
     */
    public static function buildURL($path) {
        return self::getBaseDir() . $path;
    }
    
    /**
     * Adds a route to the list
     * 
     * Add a route and a callback function
     * 
     * @param string $method    The method like GET, POST, etc
     * @param string $route     Part of the URI
     * @param callable|string $fn      Callback function or 'Method@Controller'
     * @return void
     */
    public function add($method, $route, $fn) {
        if (strlen($route) > 0 && $route[0] === '/') {
            $route = substr($route, 1);
        }
        foreach (explode('|', $method) as $m) {
            $this->routes[] = Array('method' => $m, 'fn' => $fn, 'route' => $route);
        }
    }
    
    /**
     * Adds a GET route to the list
     * 
     * Add a route and a callback function
     * 
     * @param string $route     Part of the URI
     * @param callable|string $fn      Callback function or 'Method@Controller'
     * @return void
     */
    public function addGet($route, $fn) {
        $this->add('GET', $route, $fn);
    }

    /**
     * Adds a before route to the list
     * 
     * The before routes are executed first
     * 
     * @param string $method    The method like GET, POST, etc
     * @param string $route     Part of the URI
     * @param callable|string $fn      Callback function or 'Method@Controller'
     * @return void
     */
    public function addBefore($method, $route, $fn) {
        if (strlen($route) > 0 && $route[0] === '/') {
            $route = substr($route, 1);
        }
        foreach (explode('|', $method) as $m) {
            $this->beforeroutes[] = Array('method' => $m, 'fn' => $fn, 'route' => $route);
        }
    }

    /**
     * Adds a before route to the list (GET)
     * 
     * The before routes are executed first
     * 
     * @param string $route     Part of the URI
     * @param callable|string $fn      Callback function or 'Method@Controller'
     * @return void
     */
    public function addBeforeGet($route, $fn) {
        $this->addBefore('GET', $route, $fn);
    }
    /**
     * Adds a 404 route to the list
     * 
     * Add a callback
     * 
     * @param callable $fn      Callback function
     * @return void
     */
    public function add404($fn) {
        $this->error404 = $fn;
    }
    /**
     * Starts routing
     * 
     * @return int  Number of routes
     */
    public function start() {
        $port = $_SERVER['SERVER_PORT'];
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_method = $_SERVER['REQUEST_METHOD'];
        $query = $_SERVER['QUERY_STRING'];
        
        $uri = substr($request_uri, 1);

        $found = 0;
        if (!empty($this->beforeroutes)) {
            $this->doRoutes($this->beforeroutes, $uri, $request_method);
        }
        if (!empty($this->routes)) {
            $found = $this->doRoutes($this->routes, $uri, $request_method);
        }

        # Route not found, show 404 message
        if ($found === 0 && $this->error404 !== null) {
            call_user_func($this->error404);
            $found++;
        }
        return $found;
    }
    private function doRoutes($routes, $uri, $request_method) {
        $found = 0;
        
        # Test all stored routes
        foreach ($routes as $route) {
            $method = $route['method'];
            $fn = $route['fn'];

            if ($method === $request_method) {
                if (preg_match('#^' . $route['route'] . '$#', $uri, $matches) === 1) {
                    # Remove first array element
                    $matches = array_slice($matches, 1);
                    # Callback given
                    if (is_callable($fn)) {
                        call_user_func_array($fn, $matches);
                        $found++;
                    } else if (is_string($fn)) {
                    # String 'controller@method' given
                        $splitted = preg_split('/@/', substr($fn, strrpos($fn, '\\')));
                        $path = substr($fn, 0, strrpos($fn, '\\'));
                        $controller = $splitted[0];
                        $method = $splitted[1] ?? '';

                        $filename = $this->controllerpath . $path . $controller . '.php';
                        if (file_exists($filename)) {
                            require_once $filename;
                            $instance = new $controller();
                            if ($method !== '' && method_exists($controller, $method)) {
                                $instance->{$method}($matches);
                            } else {
                                $instance->index();
                            }
                            $found++;
                        }
                    }
                }
            }
        }
        return $found;
    }
}
