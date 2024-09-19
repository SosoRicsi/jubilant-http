<?php

    namespace Jubilant;

    use Jubilant\Router;
    use Jubilant\Template;

    class Application {
        public Router $router;

        public function __construct() {
            $this->router = new Router();
        }

        public function start() {
           $this->router->run();
        }
    }