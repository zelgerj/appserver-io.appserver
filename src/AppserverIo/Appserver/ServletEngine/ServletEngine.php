<?php

/**
 * AppserverIo\Appserver\ServletEngine\ServletEngine
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Appserver\ServletEngine;

use AppserverIo\Http\HttpProtocol;
use AppserverIo\Http\HttpResponseStates;
use AppserverIo\Psr\Application\ApplicationInterface;
use AppserverIo\Psr\HttpMessage\RequestInterface;
use AppserverIo\Psr\HttpMessage\ResponseInterface;
use AppserverIo\Psr\Servlet\ServletException;
use AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface;
use AppserverIo\Server\Dictionaries\ModuleHooks;
use AppserverIo\Server\Dictionaries\ServerVars;
use AppserverIo\Server\Interfaces\RequestContextInterface;
use AppserverIo\Server\Interfaces\ServerContextInterface;
use AppserverIo\Server\Exceptions\ModuleException;
use AppserverIo\Appserver\ServletEngine\Http\Request;
use AppserverIo\Appserver\ServletEngine\Http\Response;


class AThread extends \Thread
{
    public function run() {
        try {

            // register shutdown handler
            register_shutdown_function(array(&$this, "shutdown"));

            // synchronize the application instance
            $application = $this->application;
            $application->lock();

            // register class loaders
            $application->registerClassLoaders();

            // synchronize the valves, servlet request/response
            $valves = $this->valves;
            $servletRequest = $this->servletRequest;

            // initialize servlet session, request + response
            $servletResponse = new Response();
            $servletResponse->init();

            // inject the sapplication and servlet response
            $servletRequest->injectResponse($servletResponse);
            $servletRequest->injectContext($application);

            // prepare the request instance
            $servletRequest->prepare();

            $application->unlock();


            // process the valves
            foreach ($valves as $valve) {
                /*
                $valve->invoke($servletRequest, $servletResponse);
                   */
                if ($servletRequest->isDispatched() === true) {
                    break;
                }
            }

        } catch (\Exception $e) {
                // bind the exception in the respsonse
                $this->exception = $e;
            }
    }

    /**
     * Injects the valves to be processed.
     *
     * @param array $valves The valves to process
     *
     * @return void
     */
    public function injectValves(array $valves)
    {
        $this->valves = $valves;
    }

    /**
     * Injects the application of the request to be handled
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    public function injectApplication(ApplicationInterface $application)
    {
        $this->application = $application;
    }

    /**
     * Inject the actual servlet request.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The actual request instance
     *
     * @return void
     */
    public function injectRequest(HttpServletRequestInterface $servletRequest)
    {
        $this->servletRequest = $servletRequest;
    }

    /**
     * Does shutdown logic for request handler if something went wrong and produces
     * a fatal error for example.
     *
     * @return void
     */
    public function shutdown()
    {

        // check if there was a fatal error caused shutdown
        $lastError = error_get_last();
        if ($lastError['type'] === E_ERROR || $lastError['type'] === E_USER_ERROR) {
            // set the status code and append the error message to the body
            $this->statusCode = 500;
            $this->bodyStream = $lastError['message'];
        }
    }

}

/**
 * A servlet engine implementation.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class ServletEngine extends AbstractServletEngine
{

    /**
     * The unique module name in the web server context.
     *
     * @var string
     */
    const MODULE_NAME = 'servlet';

    /**
     * Timeout to wait for a free request handler: 1 s
     *
     * @var integer
     */
    const REQUEST_HANDLER_WAIT_TIMEOUT = 1000000;

    /**
     * Returns the module name.
     *
     * @return string The module name
     */
    public function getModuleName()
    {
        return ServletEngine::MODULE_NAME;
    }

    /**
     * Initializes the module.
     *
     * @param \AppserverIo\Server\Interfaces\ServerContextInterface $serverContext The servers context instance
     *
     * @return void
     * @throws \AppserverIo\Server\Exceptions\ModuleException
     */
    public function init(ServerContextInterface $serverContext)
    {
        try {
            // set the servlet context
            $this->serverContext = $serverContext;

            // initialize the servlet engine
            $this->initValves();
            $this->initHandlers();
            $this->initApplications();

        } catch (\Exception $e) {
            throw new ModuleException($e);
        }
    }

    /**
     * Process servlet request.
     *
     * @param \AppserverIo\Psr\HttpMessage\RequestInterface          $request        A request object
     * @param \AppserverIo\Psr\HttpMessage\ResponseInterface         $response       A response object
     * @param \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext A requests context instance
     * @param int                                                    $hook           The current hook to process logic for
     *
     * @return bool
     * @throws \AppserverIo\Server\Exceptions\ModuleException
     */
    public function process(
        RequestInterface $request,
        ResponseInterface $response,
        RequestContextInterface $requestContext,
        $hook
    ) {

        // if false hook is coming do nothing
        if (ModuleHooks::REQUEST_POST !== $hook) {
            return;
        }

        // check if we are the handler that has to process this request
        if ($requestContext->getServerVar(ServerVars::SERVER_HANDLER) !== $this->getModuleName()) {
            return;
        }

        // create a copy of the valve instances
        $valves = $this->valves;
        $handlers = $this->handlers;

        // load the application associated with this request
        $application = $this->findRequestedApplication($requestContext);

        // create a new request instance from the HTTP request
        $servletRequest = new Request();
        $servletRequest->fromHttpRequest($request);
        $servletRequest->injectHandlers($handlers);
        $servletRequest->injectServerVars($requestContext->getServerVars());


//        $requestHandler = new AThread();
//        $requestHandler->injectValves($valves);
//        $requestHandler->injectApplication($application);
//        $requestHandler->injectRequest($servletRequest);
//        $requestHandler->start();
//        $requestHandler->join();


        // initialize the request handler instance
        $requestHandler = new RequestHandler();
        $requestHandler->injectValves($valves);
        $requestHandler->injectApplication($application);
        $requestHandler->injectRequest($servletRequest);
        $requestHandler->start();
        $requestHandler->join();

        // copy values to the HTTP response
        $requestHandler->copyToHttpResponse($response);

          // set response state to be dispatched after this without calling other modules process
        $response->setState(HttpResponseStates::DISPATCH);
    }

    /**
     * Tries to find a request handler that matches the actual request and injects it into the request.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The servlet request we need a request handler to handle for
     *
     * @return string The application name of the application to handle the request
     * @deprecated This method is deprecated since 0.8.0
     */
    protected function requestHandlerFromPool(HttpServletRequestInterface $servletRequest)
    {
        // nothing to do here
    }

    /**
     * After a request has been processed by the injected request handler we remove
     * the thread ID of the request handler from the array with the working handlers.
     *
     * @param \AppserverIo\Appserver\ServletEngine\RequestHandler $requestHandler The request handler instance we want to re-attach to the pool
     *
     * @return void
     * @deprecated This method is deprecated since 0.8.0
     */
    protected function requestHandlerToPool($requestHandler)
    {
        // nothing to do here
    }
}
