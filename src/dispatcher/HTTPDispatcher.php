<?php

namespace hannespries\dispatcher;

use Symfony\Component\HttpFoundation\Request;
use hannespries\response\Response;
use hannespries\events\EventHandler;
use hannespries\response\ResponseOut;

use hannespries\dispatcher\controllers\ZendStyle;
use hannespries\dispatcher\controllers\MkclStyle;
use hannespries\dispatcher\controllers\HTTPController;

class HTTPDispatcher {
    /** @var Request */
    private $request = null;

    /** @var EventHandler */
    private $events = null;

    /** @var ResponseOut */
    private $out = null;

    private $controllerRegister = [];

    public function __construct(Request $request, ResponseOut $out, EventHandler $events) {
        $this->request = $request;
        $this->events = $events;
        $this->out = $out;

        $controllers = [];
        try{
            $controllers = $this->events->fireFilterEvent('register_controllers', $controllers);
        }
        catch(\Exception $e){
            //TODO
        }
        $this->controllerRegister = array_merge($controllers, $this->controllerRegister);        
    }

    public function registerController($name, $clazz) {
        $this->controllerRegister[$name] = $clazz;
    }

    public function dispatch($controllerKey, $actionName) {
        if(isset($this->controllerRegister[$controllerKey])) {
            $controllerClass = $this->controllerRegister[$controllerKey];
            $refController = new \ReflectionClass($controllerClass);
            $controller = $refController->newInstance();

            $resp = new Response();

            $this->events->fireFilterEvent('dispatcher_predispatch_' . $controllerKey, null, ['subject' => $controller, 'action' => $actionName, 'request' => $this->request, 'response' => $resp]);

            if($controller instanceof ZendStyle){
                $controller->request = $this->request;
                $controller->response = $resp;                

                try{
                    $ref = new \ReflectionClass($controller);
                    if($ref->hasMethod($actionName . 'Action')){
                        $this->events->fireFilterEvent('dispatcher_predispatch_' . $controllerKey . '_' . $actionName, null, ['subject' => $controller, 'action' => $actionName, 'request' => $this->request, 'response' => $resp]);
                        
                        $method = $ref->getMethod($actionName . 'Action');
                        $method->invoke($controller);

                        $resp = $this->events->fireFilterEvent('dispatcher_postdispatch_' . $controllerKey . '_' . $actionName, $resp, ['subject' => $controller, 'action' => $actionName, 'request' => $this->request, 'response' => $resp]);
                    }
                    else{
                        $resp->setCode(404);
                    }
                }
                catch(\Exception $e){

                } 
            }
            else if($controller instanceof MkclStyle) {
                $this->events->fireFilterEvent('dispatcher_predispatch_' . $controllerKey . '_' . $actionName, null, ['subject' => $controller, 'action' => $actionName, 'request' => $this->request, 'response' => $resp]);
                
                $result = $controller->handleAction($actionName);
                if($result instanceof response) {
                    $resp = $result;
                }
                else {
                    $resp->setBody((string) $result);
                }

                $resp = $this->events->fireFilterEvent('dispatcher_postdispatch_' . $controllerKey . '_' . $actionName, $resp, ['subject' => $controller, 'action' => $actionName, 'request' => $this->request, 'response' => $resp]);
            }
            else if ($controller instanceof HTTPController) {
                $ref = new \ReflectionClass($controller);
                if($ref->hasMethod($actionName)){
                    $this->events->fireFilterEvent('dispatcher_predispatch_' . $controllerKey . '_' . $actionName, null, ['subject' => $controller, 'action' => $actionName, 'request' => $this->request, 'response' => $resp]);
                    
                    $method = $ref->getMethod($actionName);
                    $result = $method->invoke($controller, $this->request, $resp);
                    if($result instanceof response) {
                        $resp = $result;
                    }
                    else {
                        $resp->setBody((string) $result);
                    }
                }
                else{
                    $resp->setCode(404);
                }

                $resp = $this->events->fireFilterEvent('dispatcher_postdispatch_' . $controllerKey . '_' . $actionName, $resp, ['subject' => $controller, 'action' => $actionName, 'request' => $this->request, 'response' => $resp]);
            }

            $this->events->fireFilterEvent('dispatcher_postdispatch_' . $controllerKey, [], ['subject' => $controller, 'action' => $actionName, 'request' => $this->request, 'response' => $resp]);

            $this->out->out($resp);
        }
        else {
            //TODO throw exception
        }
    }

}