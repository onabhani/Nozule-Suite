<?php

namespace Nozule\Core;

/**
 * Base Module class for plugin modules.
 */
abstract class BaseModule {

    protected Container $container;

    public function __construct( Container $container ) {
        $this->container = $container;
    }

    /**
     * Register the module's services, hooks, etc.
     */
    abstract public function register(): void;
}
