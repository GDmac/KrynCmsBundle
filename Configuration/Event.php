<?php

namespace Kryn\CmsBundle\Configuration;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Asset
 *
 * Paths are relative to `
 *
 * @bundlePath/Resources/public`.
 */
class Event extends Model
{
    protected $attributes = ['key', 'subject'];

    protected $elementToArray = ['clearCache' => 'clearCaches', 'call' => 'calls'];
    protected $arrayIndexNames = ['clearCaches' => 'clearCache', 'calls' => 'call'];

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string
     */
    protected $desc;

    /**
     * @var array
     */
    protected $clearCaches;

    /**
     * @var array
     */
    protected $calls;

    /**
     * @var array
     */
    protected $serviceCalls;

    /**
     * @var Condition
     */
    protected $condition;

    public function call($event)
    {
        if ($this->getCalls()) {
            foreach ($this->getCalls() as $call) {
                call_user_func_array($call, [$event]);
            }
        }
        if ($this->getClearCaches()) {
            foreach ($this->getClearCaches() as $cacheKey) {
                $this->getKrynCore()->invalidateCache($cacheKey);
            }
        }
        if ($this->getServiceCalls()) {
            foreach ($this->getServiceCalls() as $serviceCall) {
                list($service, $method) = explode('::', $serviceCall);
                if ($this->getKrynCore()->has($service)) {
                    $service = $this->getKrynCore()->get($service);
                    $service->$method($event);
                }
            }
        }
    }

    public function isCallable(GenericEvent $event)
    {
        if ($this->getSubject() && $event->getSubject() != $this->getSubject() ){
            return false;
        }

        if ($this->getCondition()) {
            $args = $event->getArguments() ?: [];
            if ($this->getCondition() && !$this->getCondition()->satisfy($args)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param string $subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param array $clearCaches
     */
    public function setClearCaches(array $clearCaches)
    {
        $this->clearCaches = $clearCaches;
    }

    /**
     * @return array
     */
    public function getClearCaches()
    {
        return $this->clearCaches;
    }

    /**
     * @param array $calls
     */
    public function setCalls(array $calls)
    {
        $this->calls = $calls;
    }

    /**
     * @return array
     */
    public function getCalls()
    {
        return $this->calls;
    }

    /**
     * @param array $serviceCalls
     */
    public function setServiceCalls(array $serviceCalls)
    {
        $this->serviceCalls = $serviceCalls;
    }

    /**
     * @return array
     */
    public function getServiceCalls()
    {
        return $this->serviceCalls;
    }

    /**
     * @param Condition $condition
     */
    public function setCondition(Condition $condition = null)
    {
        $this->condition = $condition;
    }

    /**
     * @return Condition
     */
    public function getCondition()
    {
        return $this->condition;
    }

    /**
     * @param string $desc
     */
    public function setDesc($desc)
    {
        $this->desc = $desc;
    }

    /**
     * @return string
     */
    public function getDesc()
    {
        return $this->desc;
    }

}