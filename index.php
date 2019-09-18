<?php
session_start();

# Set constant ROOT to this directory
defined('ROOT') or define('ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
# Set constant PUBLIC
defined('PUBLIC_DIR') or define('PUBLIC_DIR', ROOT . 'public' . DIRECTORY_SEPARATOR);
# Set constant MODELS
defined('MODELS') or define('MODELS', ROOT . 'app' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR);
# Set constant VIEWS
defined('VIEWS') or define('VIEWS', ROOT . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR);
# Set constant CONTROLLERS
defined('CONTROLLERS') or define('CONTROLLERS', ROOT . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR);

require 'Router.php';

$router = new Router(CONTROLLERS);

# Matches /
$router->addGet('', function() { echo 'Homepage!'; });

# /test gives 2 routes
$router->addGet('test', function() { echo 'Test, first definition!<br>'; });
$router->addGet('test', function() { echo 'Test, second definition!<br>'; });

# Matches /admin/... before 
$router->addBefore('GET', '/admin/(.*)', function() use ($router) { 
    if (!empty($_SESSION['user_level']) && $_SESSION['user_level'] >= 2) {
        # Logged in as admin
    } else {
        header('Location: ' . Router::buildURL('login'));
        exit();
    }
});

# /admin/deleteUser/1673 --> Delete user 1673!
$router->addGet('admin/deleteUser/(\d+)', function($id) { echo "Delete user $id!"; });

# /admin/renameUser/7/Swen=Test --> Rename user 7 from Test to Swen!
$router->add('GET', 'admin/renameUser/(\d+)/(\w+)=(\w+)', function($id, $to, $from) { echo "Rename user $id from $from to $to!"; });

# Matches /login | /logout, calls CONTROLLERS.user->login() or logout()
$router->add('GET', '/login', 'user@login');
$router->addGet('logout', 'user@logout');
$router->add('POST', '/loggedin', 'user@loggedin');

# Guess
$router->add404(function() { exit('404!'); });

# REST Test
$router->add('POST', 'api/showAllUsers', function() {
    # Fake authentification
    $auth = !empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW']);
    if ($auth) {
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");

        # Set up response
        $users = [];
        for ($i = 1; $i <= 10; $i++) {
            $users[] = ['id' => $i, 'name' => 'Name' . $i];
        }
        exit(json_encode($users));
    } else {
        header('HTTP/1.0 403 Forbidden');
        exit();
    }
});

# Send all data back to the client
$router->add('POST', 'api/sendback', function() {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");

    # Get posted data
    $data = json_decode(file_get_contents('php://input'), true);
    exit(json_encode(
        array('response' => [
            'send_data' => $data,
            'auth' => [
                'user' => !empty($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'unknown',
                'pw'   => !empty($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : 'unknown'
            ]
        ])
    ));
});

# Start the router
$numroutes = $router->start();

if ($numroutes > 1) {
    echo "Number of routes: $numroutes";
}
