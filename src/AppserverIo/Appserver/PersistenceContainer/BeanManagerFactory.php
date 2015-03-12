<?php

/**
 * AppserverIo\Appserver\PersistenceContainer\BeanManagerFactory
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Appserver\PersistenceContainer;

use AppserverIo\Storage\StackableStorage;
use AppserverIo\Storage\GenericStackable;
use AppserverIo\Psr\Application\ApplicationInterface;
use AppserverIo\Appserver\Core\Api\Node\ManagerNodeInterface;

use AppserverIo\Psr\Naming\InitialContext as NamingContext;

/**
 * The bean manager handles the message and session beans registered for the application.
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class BeanManagerFactory
{

    /**
     * The main method that creates new instances in a separate context.
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface         $application          The application instance to register the class loader with
     * @param \AppserverIo\Appserver\Core\Api\Node\ManagerNodeInterface $managerConfiguration The manager configuration
     *
     * @return void
     */
    public static function visit(ApplicationInterface $application, ManagerNodeInterface $managerConfiguration)
    {

        // load the registered loggers
        $loggers = $application->getInitialContext()->getLoggers();

        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));

        // initialize the bean locator
        $beanLocator = new BeanLocator();

        // create the initial context instance
        $initialContext = new NamingContext();
        $initialContext->injectApplication($application);

        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));

        // initialize the stackable for the data, the stateful + singleton session beans and the naming directory
        $data = new StackableStorage();
        $instances = new GenericStackable();
        $statefulSessionBeans = new StackableStorage();
        $singletonSessionBeans = new StackableStorage();

        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        // initialize the default settings for the stateful session beans
        $statefulSessionBeanSettings = new DefaultStatefulSessionBeanSettings();
        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        $statefulSessionBeanSettings->mergeWithParams($managerConfiguration->getParamsAsArray());

        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        // we need a factory instance for the stateful session bean instances
        $statefulSessionBeanMapFactory = new StatefulSessionBeanMapFactory($statefulSessionBeans);
        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        $statefulSessionBeanMapFactory->injectLoggers($loggers);
        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        $statefulSessionBeanMapFactory->start(PTHREADS_INHERIT_ALL | PTHREADS_ALLOW_GLOBALS);



        $gs = call_user_func(APPSERVER_GLOBALSTORAGE_CLASSNAME . '::getInstance');
        error_log(var_export($gs::$storages[APPSERVER_STORAGE_GLOBAL], true));


        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        // create an instance of the object factory
        $objectFactory = new GenericObjectFactory();
        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        $objectFactory->injectInstances($instances);
        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        $objectFactory->injectApplication($application);
        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));
        $objectFactory->start(PTHREADS_INHERIT_ALL | PTHREADS_ALLOW_GLOBALS);




        error_log(__METHOD__ . ':' . __LINE__ . ' ## ' . var_export(get_class($application), true));

        // initialize the bean manager
        $beanManager = new BeanManager();
        $beanManager->injectData($data);
        $beanManager->injectApplication($application);
        $beanManager->injectResourceLocator($beanLocator);
        $beanManager->injectObjectFactory($objectFactory);
        $beanManager->injectInitialContext($initialContext);
        $beanManager->injectStatefulSessionBeans($statefulSessionBeans);
        $beanManager->injectSingletonSessionBeans($singletonSessionBeans);
        $beanManager->injectDirectories($managerConfiguration->getDirectories());
        $beanManager->injectStatefulSessionBeanSettings($statefulSessionBeanSettings);
        $beanManager->injectStatefulSessionBeanMapFactory($statefulSessionBeanMapFactory);

        // attach the instance
        $application->addManager($beanManager, $managerConfiguration);
    }
}
