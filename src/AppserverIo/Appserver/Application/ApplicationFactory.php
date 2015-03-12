<?php

/**
 * AppserverIo\Appserver\Application\ApplicationFactory
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

namespace AppserverIo\Appserver\Application;

use AppserverIo\Appserver\Core\GlobalStorage;
use AppserverIo\Storage\StackableStorage;
use AppserverIo\Storage\GenericStackable;
use AppserverIo\Appserver\Core\Api\Node\ContextNode;
use AppserverIo\Appserver\Core\Interfaces\ContainerInterface;

/**
 * Application factory implementation.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class ApplicationFactory
{

    /**
     * Visitor method that registers the application in the container.
     *
     * @param \AppserverIo\Appserver\Core\Interfaces\ContainerInterface $container The container instance bind the application to
     * @param \AppserverIo\Appserver\Core\Api\Node\ContextNode          $context   The application configuration
     *
     * @return void
     */
    public static function visit(ContainerInterface $container, ContextNode $context)
    {

        error_log(__METHOD__ . ':' . __LINE__);
        // prepare the path to the applications base directory
        $folder = $container->getAppBase() . DIRECTORY_SEPARATOR . $context->getName();

        error_log(__METHOD__ . ':' . __LINE__);
        // declare META-INF and WEB-INF directory
        $webInfDir = $folder . DIRECTORY_SEPARATOR . 'WEB-INF';
        $metaInfDir = $folder . DIRECTORY_SEPARATOR . 'META-INF';

        error_log(__METHOD__ . ':' . __LINE__);
        // check if we've a directory containing a valid application,
        // at least a WEB-INF or META-INF folder has to be available
        if (!is_dir($webInfDir) && !is_dir($metaInfDir)) {
            return;
        }

        error_log(__METHOD__ . ':' . __LINE__);
        // load the naming directory + initial context
        $initialContext = $container->getInitialContext();
        $namingDirectory = $container->getNamingDirectory();

        error_log(__METHOD__ . ':' . __LINE__);
        // load the application service
        $appService = $container->newService('AppserverIo\Appserver\Core\Api\AppService');

        error_log(__METHOD__ . ':' . __LINE__);
        // load the application type
        $contextType = $context->getType();
        $applicationName = $context->getName();


        error_log(__METHOD__ . ':' . __LINE__);
        // create a new application instance

        $application = new $contextType();

        error_log(__METHOD__ . ':' . __LINE__);
        // initialize the storage for managers, virtual hosts an class loaders
        $data = new StackableStorage();
        $managers = new GenericStackable();
        $classLoaders = new GenericStackable();


        error_log(__METHOD__ . ':' . __LINE__);
        // initialize the generic instances and information
        $application->injectData($data);
        $application->injectManagers($managers);
        $application->injectName($applicationName);
        $application->injectClassLoaders($classLoaders);
        $application->injectInitialContext($initialContext);
        $application->injectNamingDirectory($namingDirectory);


        error_log(__METHOD__ . ':' . __LINE__);
        // prepare the application instance
        $application->prepare($context);

        error_log(__METHOD__ . ':' . __LINE__);
        // create the applications temporary folders and cleans the folders up
        $appService->createTmpFolders($application);

        $application->getTmpDir();

        $appService->cleanUpFolders($application);

        error_log(__METHOD__ . ':' . __LINE__);
        // add the default class loader
        $application->addClassLoader(
            $initialContext->getClassLoader(),
            $initialContext->getSystemConfiguration()->getInitialContext()->getClassLoader()
        );

        // add the configured class loaders
        foreach ($context->getClassLoaders() as $classLoader) {
            if ($classLoaderFactory = $classLoader->getFactory()) {
                // use the factory if available
                error_log(__METHOD__ . ':' . __LINE__);
                $classLoaderFactory::visit($application, $classLoader);
            } else {
                error_log(__METHOD__ . ':' . __LINE__);
                // if not, try to instanciate the class loader directly
                $classLoaderType = $classLoader->getType();
                $application->addClassLoader(new $classLoaderType($classLoader), $classLoader);
            }
        }

        // add the configured managers
        foreach ($context->getManagers() as $manager) {
            if ($managerFactory = $manager->getFactory()) {
                // use the factory if available
                error_log(__METHOD__ . ':' . __LINE__);

                $managerFactory::visit($application, $manager);
            } else {
                error_log(__METHOD__ . ':' . __LINE__);
                // if not, try to instanciate the manager directly
                $managerType = $manager->getType();
                $application->addManager(new $managerType($manager), $manager);
            }
        }

        // add the application to the container
        $container->addApplication($application);
    }
}
