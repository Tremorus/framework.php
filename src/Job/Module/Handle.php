<?php

namespace Basis\Job\Module;

use Basis\Application;
use Basis\Context;
use Basis\Dispatcher;
use Basis\Event;
use Basis\Job;
use Basis\Service;
use Exception;

class Handle extends Job
{
    public $event;
    public $eventId;
    public $context;

    public function run(Application $app, Dispatcher $dispatcher, Event $event, Service $service)
    {
        $start = microtime(1);
        $subscription = $event->getSubscription();

        $patterns = [];
        foreach (array_keys($subscription) as $pattern) {
            if ($service->eventMatch($this->event, $pattern)) {
                $patterns[] = $pattern;
            }
        }

        if (!count($patterns)) {
            $service->unsubscribe($info->event);
            return $dispatcher->send('event.feedback', [
                'eventId' => $this->eventId,
                'service' => $service->getName(),
                'result' => [
                    'message' => 'no subscription'
                ],
            ]);
        }

        $listeners = [];
        foreach ($patterns as $pattern) {
            foreach ($subscription[$pattern] as $listener) {
                if (!array_key_exists($listener, $listeners)) {
                    $listeners[$listener] = $app->get('Listener\\'.$listener);
                    $listeners[$listener]->event = $this->event;
                    $listeners[$listener]->eventId = $this->eventId;
                    $listeners[$listener]->context = $this->context;
                }
            }
        }


        $data = [];
        $issues = [];
        foreach ($listeners as $nick => $listener) {
            try {
                $data[$nick] = $app->call([$listener, 'run']);
                $event->fireChanges($nick);
            } catch (Exception $e) {
                $issues[$nick] =  $e->getMessage();
            }
        }

        $dispatcher->send('event.feedback', [
            'eventId' => $this->eventId,
            'service' => $service->getName(),
            'result' => [
                'data' => $data,
                'issues' => $issues,
                'time' => microtime(1) - $start,
            ]
        ]);
    }
}
