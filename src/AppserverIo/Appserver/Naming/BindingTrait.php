<?php

/**
 * AppserverIo\Appserver\Naming\BindingTrait
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

namespace AppserverIo\Appserver\Naming;

use AppserverIo\Psr\Naming\NamingException;
use AppserverIo\Psr\Naming\NamingDirectoryInterface;

/**
 * Trait which allows for binding of class instances and callbacks to the class using it.
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
trait BindingTrait
{

    /**
     * Binds the passed instance with the name to the naming directory.
     *
     * @param string $name  The name to bind the value with
     * @param mixed  $value The object instance to bind
     * @param array  $args  The array with the arguments
     *
     * @return void
     * @throws \AppserverIo\Psr\Naming\NamingException Is thrown if the value can't be bound ot the directory
     */
    public function bind($name, $value, array $args = array())
    {
        $origName = $name;
        // strip off the schema
        $name = str_replace(sprintf('%s:', 'php'), '', $name);

        // check if we can find something
        if ($this->hasAttribute($name, ($origName === $name))) {
            // throw an exception if we can't resolve the name
            throw new NamingException(sprintf('Cant\'t bind %s to value of naming directory %s', $name, $this->getIdentifier()));

        } else {
            // bind the value
            return $this->setAttribute($name, array($value, $args), ($origName === $name));
        }

        // throw an exception if we can't resolve the name
        throw new NamingException(sprintf('Cant\'t bind %s to naming directory %s', $token, $this->getIdentifier()));
    }

    /**
     * Binds the passed callback with the name to the naming directory.
     *
     * @param string   $name     The name to bind the callback with
     * @param callable $callback The callback to be invoked when searching for
     * @param array    $args     The array with the arguments passed to the callback when executed
     *
     * @return void
     * @see \AppserverIo\Appserver\Naming\NamingDirectory::bind()
     */
    public function bindCallback($name, callable $callback, array $args = array())
    {
        $this->bind($name, $callback, $args);
    }

    /**
     * Binds a reference with the passed name to the naming directory.
     *
     * @param string $name      The name to bind the reference with
     * @param string $reference The name of the reference
     *
     * @return void
     * @see \AppserverIo\Appserver\Naming\NamingDirectory::bind()
     */
    public function bindReference($name, $reference)
    {
        $this->bindCallback($name, array(&$this, 'search'), array($reference, array()));
    }

    /**
     * Queries the naming directory for the requested name and returns the value
     * or invokes the binded callback.
     *
     * @param string $name The name of the requested value
     * @param array  $args The arguments to pass to the callback
     *
     * @return mixed The requested value
     * @throws \AppserverIo\Psr\Naming\NamingException Is thrown if the requested name can't be resolved in the directory
     */
    public function search($name, array $args = array())
    {

        $origName = $name;
        // strip off the schema
        $name = str_replace(sprintf('%s:', 'php'), '', $name);

        error_log(__METHOD__ . ': $name = ' . $name);

        // tokenize the name
        $token = strtok($name, '/');

        error_log(__METHOD__ . ':' . __LINE__);

        // while we've tokens, try to find a value bound to the token
        while ($token !== false) {

            error_log(__METHOD__ . ':' . __LINE__);

            // check if we can find something
            if ($this->hasAttribute($name)) {
                // load the value
                $found = $this->getAttribute($name);

                // load the binded value/args
                list ($value, $bindArgs) = $found;

                // check if we've a callback method
                if (is_callable($value)) {
                    // if yes, merge the params and invoke the callback
                    foreach ($args as $arg) {
                        $bindArgs[] = $arg;
                    }
                    // invoke the callback
                    error_log(__METHOD__ . ':' . __LINE__);
                    return call_user_func_array($value, $bindArgs);
                }

                // search recursive
                if ($value instanceof NamingDirectoryInterface) {
                    if ($value->getName() !== $name) {
                        // if $value is NOT what we're searching for
                        error_log(__METHOD__ . ':' . __LINE__);
                        return $value->search($name . '/' . $token, $args);
                    }
                }

                // if not, simply return the value/object
                error_log(__METHOD__ . ':' . __LINE__);
                return $value;
            }

            // load the next token
            $token = strtok('/');
        }

        /*
        // delegate the search request to the parent directory
        if ($parent = $this->getParent()) {
            return $parent->search($name, $args);
        }
        */

        error_log(__METHOD__ . ':' . __LINE__);

        // throw an exception if we can't resolve the name
        throw new NamingException(sprintf('Cant\'t resolve %s in naming directory %s', ltrim($name, '/'), $this->getIdentifier()));
    }
}
