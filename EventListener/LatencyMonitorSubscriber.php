<?php

namespace Kryn\CmsBundle\EventListener;

use Kryn\CmsBundle\Core;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class LatencyMonitorSubscriber implements EventSubscriberInterface
{

    /**
     * @var Core
     */
    protected $krynCore;

    protected $start = 0;
    
    protected $latency = [];

    function __construct(Core $krynCore)
    {
        $this->krynCore = $krynCore;
    }

    public static function getSubscribedEvents()
    {
        return [
            'kernel.request' => [
                'onRequestPre', 2048
            ],
            'kernel.terminate' => [
                'onTerminatePre', 128
            ],
            'kernel.finish_request' => [
                'onFinishRequestPre', 128
            ]
        ];
    }

    public function onRequestPre(GetResponseEvent $event)
    {
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) return;
        $this->start = microtime(true);
    }

    public function onFinishRequestPre(FinishRequestEvent $event)
    {
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) return;
        $key = $this->krynCore->isAdmin() ? 'backend' : 'frontend';

        $this->log($key, microtime(true) - $this->start);
    }

    public function onTerminatePre(PostResponseEvent $event)
    {
        $this->saveLatency();
    }

    public function log($area, $ms)
    {
        $this->latency[$area][] = $ms;
    }

    public function saveLatency()
    {
        $lastLatency = $this->krynCore->getFastCache()->get('core/latency');

        $max = 20;
        $change = false;
        foreach (array('frontend', 'backend', 'cache', 'session') as $key) {
            if (!@$this->latency[$key]) {
                continue;
            }

            $this->latency[$key] = array_sum($this->latency[$key]) / count($this->latency[$key]);

            $lastLatency[$key] = (array)@$lastLatency[$key] ? : array();
            array_unshift($lastLatency[$key], @$this->latency[$key]);
            if ($max < count($lastLatency[$key])) {
                array_splice($lastLatency[$key], $max);
            }
            $change = true;
        }

        if ($change) {
            $this->latency = array();
            $this->krynCore->getFastCache()->set('core/latency', $lastLatency);
        }
    }

}