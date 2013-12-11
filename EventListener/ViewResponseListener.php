<?php

namespace Kryn\CmsBundle\EventListener;

use FOS\RestBundle\EventListener\ViewResponseListener as FOSViewResponseListener;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;

class ViewResponseListener extends FOSViewResponseListener
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The service container instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        if ($this->container->get('kryn_cms')->isAdmin()) {
            $view = $event->getControllerResult();
            $view = [
                'status' => 200,
                'data' => $view
            ];
            try {
                $response = new Response(json_encode($view, JSON_PRETTY_PRINT));
                $event->setResponse($response);
            } catch (\Exception $e) {
                throw new \Exception('Can not serialize data. You controller probably return something that contains a resource or object.', 0, $e);
            }
        }
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        if ($this->container->get('kryn_cms')->isAdmin()) {
            parent::onKernelController($event);
        }
    }

}