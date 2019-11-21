<?php

use hannespries\dispatcher\controllers\HTTPController;
use Symfony\Component\HttpFoundation\Request;
use hannespries\response\Response;
use hannespries\response\ResponseOut;
use hannespries\dispatcher\HTTPDispatcher;

class TestController implements HTTPController {
    public function index(Request $req, Response $res) {
        $res->setBody('test23');
        return $res;
    }
}

class TestController2 {
    public function index($number) {
        $res = new Response();
        $res->setBody('test_' . $number);
        return $res;
    }
}

class Test extends \PHPUnit\Framework\TestCase{
    public function test_simple() {
        $value = null;
        $out = new ResponseOut(function ($response) use (&$value) {
            $value = $response->getBody();
        });

        $dispatcher = new HTTPDispatcher(new Request(), $out, $handler = new \hannespries\events\EventHandler());
        $dispatcher->registerController('test', TestController::class);

        $dispatcher->dispatch('test', 'index');

        $this->assertEquals('test23', $value);
    }

    public function test_simple2() {
        $value = null;
        $out = new ResponseOut(function ($response) use (&$value) {
            $value = $response->getBody();
        });

        $dispatcher = new HTTPDispatcher(new Request(['number' => 69]), $out, $handler = new \hannespries\events\EventHandler());
        $dispatcher->registerController('test', TestController2::class);

        $dispatcher->dispatch('test', 'index');

        $this->assertEquals('test_69', $value);
    }
}    