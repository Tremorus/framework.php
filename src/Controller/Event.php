<?php

namespace Basis\Controller;

use Basis\Application;
use Basis\Event as BasisEvent;
use Basis\Service;
use Exception;

class Event
{
    public function index(Application $app, BasisEvent $event, Service $service)
    {
        try {
            $context = $this->getRequestContext();
            $subscription = $event->getSubscription();

            if (!array_key_exists($_REQUEST['event'], $subscription)) {
                $service->unsubscribe($_REQUEST['event']);
                throw new Exception("No subscription on event ".$_REQUEST['event'], 1);
            }

            $result = [];

            foreach ($subscription[$_REQUEST['event']] as $listener) {
                $instance = $app->get('Listener\\'.$listener);
                $result[$listener] = $this->handleEvent($instance, $context);
            }

            return [
                'success' => true,
                'data' => $result,
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function getRequestContext()
    {
        if (!array_key_exists('event', $_REQUEST)) {
            throw new Exception('No event defined');
        }

        if (!array_key_exists('context', $_REQUEST)) {
            throw new Exception('No context defined');
        }

        $context = json_decode($_REQUEST['context']);

        if (!$context) {
            throw new Exception('Invalid context');
        }

        return $context;
    }

    private function handleEvent($instance, $context)
    {
        foreach ($context as $k => $v) {
            $instance->$k = $v;
        }
        if (!method_exists($instance, 'run')) {
            throw new Exception('No run method for '.$class);
        }

        return $app->call([$instance, 'run']);
    }
}
