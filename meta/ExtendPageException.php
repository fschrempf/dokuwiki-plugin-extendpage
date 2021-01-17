<?php

namespace dokuwiki\plugin\extendpage\meta;

/**
 * Class ExtendPageException
 *
 * @package dokuwiki\plugin\extendpage\meta
 */
class ExtendPageException extends \RuntimeException
{

    protected $trans_prefix = 'Exception ';

    /**
     * ExtendPageException constructor.
     *
     * @param string $message
     * @param ...string $vars
     */
    public function __construct($message)
    {
        /** @var \helper_plugin_extendpage $plugin */
        $plugin = plugin_load('helper', 'extendpage');

        $args = func_get_args();
        array_shift($args);

        $trans = vsprintf($message, $args);

        parent::__construct($message, -1, null);
    }
}
