<?php

/**
 * \AppserverIo\Appserver\Core\Api\ScannerService
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
namespace AppserverIo\Appserver\Core\Api;

use AppserverIo\Configuration\ConfigurationException;
use AppserverIo\Psr\Application\ApplicationInterface;
use AppserverIo\Appserver\Core\Api\Node\CronNode;
use AppserverIo\Appserver\Core\Api\Node\ContextNode;
use AppserverIo\Appserver\Core\Api\Node\DeploymentNode;
use AppserverIo\Appserver\Core\Interfaces\ContainerInterface;

/**
 * A service that handles scanner configuration data.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class ScannerService extends AbstractFileOperationService
{

    /**
     * Returns the node with the passed UUID.
     *
     * @param integer $uuid UUID of the node to return
     *
     * @return \AppserverIo\Configuration\Interfaces\NodeInterface The node with the UUID passed as parameter
     */
    public function load($uuid)
    {
        // not implemented yet
    }

    /**
     * Initializes the available CRON configurations and returns them.
     *
     * @return array The array with the available CRON configurations
     */
    public function findAll()
    {
        try {

            // initialize the array with the CRON instances
            $cronInstances = array();

            // validate the base context file
            /** @var AppserverIo\Appserver\Core\Api\ConfigurationService $configurationService */
            $configurationService = $this->newService('AppserverIo\Appserver\Core\Api\ConfigurationService');
            $configurationService->validateFile($baseCronPath, null);

            // we will need to test our CRON configuration files
            $configurationTester = new ConfigurationService($this->getInitialContext());
            $baseCronPath = $this->getConfdDir('cron.xml');

            // validate the base CRON file and load it as default if validation succeeds
            $cronInstance = new CronNode();
            $cronInstance->initFromFile($baseCronPath);

            // iterate over all jobs to configure the directory where they has to be executed
            /** @var \AppserverIo\Appserver\Core\Api\Node\JobNodeInterface $jobNode */
            foreach ($cronInstance->getJobs() as $job) {
                // load the execution information
                $execute = $job->getExecute();
                // query whether or not a base directory has been specified
                if ($execute && $execute->getDirectory() == null) {
                    // set the directory where the cron.xml file located as base directory, if not
                    $execute->setDirectory(dirname($baseCronPath));
                }
            }

            // add the default CRON configuration
            $cronInstances[] = $cronInstance;

            // iterate over all applications and create the CRON configuration
            foreach (glob($this->getWebappsDir() . '/*', GLOB_ONLYDIR) as $webappPath) {
                // iterate through all CRON configurations (cron.xml), validate and merge them
                foreach ($this->globDir($webappPath . '/META-INF/cron.xml') as $cronFile) {
                    try {
                        // validate the file, but skip it if validation fails
                        $configurationService->validateFile($cronFile, null);

                        // create a new CRON node instance
                        $cronInstance = new CronNode();
                        $cronInstance->initFromFile($cronFile);

                        // iterate over all jobs to configure the directory where they has to be executed
                        /** @var \AppserverIo\Appserver\Core\Api\Node\JobNodeInterface $jobNode */
                        foreach ($cronInstance->getJobs() as $job) {
                            // load the execution information
                            $execute = $job->getExecute();
                            // query whether or not a base directory has been specified
                            if ($execute && $execute->getDirectory() == null) {
                                // set the directory where the cron.xml file located as base directory, if not
                                $execute->setDirectory($webappPath);
                            }
                        }

                        // append it to the other CRON configurations
                        $cronInstances[] = $cronInstance;

                    } catch (ConfigurationException $ce) {

                        // load the logger and log the XML validation errors
                        $systemLogger = $this->getInitialContext()->getSystemLogger();
                        $systemLogger->error($ce->__toString());

                        // additionally log a message that CRON configuration will be missing
                        $systemLogger->critical(
                            sprintf('Will skip app specific CRON configuration %s, configuration might be faulty.', $cronFile)
                        );
                    }
                }
            }

        } catch (ConfigurationException $ce) {

            // load the logger and log the XML validation errors
            $systemLogger = $this->getInitialContext()->getSystemLogger();
            $systemLogger->error($ce->__toString());

            // additionally log a message that DS will be missing
            $systemLogger->critical(
                sprintf('Problems validating base CRON file %s, this might affect app configurations badly.', $baseCronPath)
            );
        }

        // return the array with the CRON instances
        return $cronInstances;
    }
}
