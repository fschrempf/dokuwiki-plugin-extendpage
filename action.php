<?php
/**
 * DokuWiki Plugin extendpage (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Frieder Schrempf <dev@fris.de>
 */

use dokuwiki\plugin\extendpage\meta\ExtendPagePatterns;

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_extendpage extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('PARSER_WIKITEXT_PREPROCESS', 'AFTER', $this, 'extend_page');
    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function extend_page(Doku_Event $event, $param)
    {
        global $ID;
        $positions = array('replace', 'top', 'bottom');

        if (!page_exists($ID)) return;

        try {
            $assignments = ExtendPagePatterns::getInstance();
        } catch (RuntimeException $e) {
            return false;
        }

        foreach ($positions as $pos) {
            $idx = $pos === 'bottom' ? strlen($event->data):0;
            $extensions = $assignments->getPageAssignments($ID, $pos);
            if (!$extensions) continue;

            foreach ($extensions as $ext) {
                if ($pos === 'replace') {
                    $event->data = rawWiki($ext['page']);
                } else {
                    $event->data = substr_replace($event->data,
                        ($pos === 'top' ? '':'\\\ ') . rawWiki($ext['page']) . ($pos === 'top' ? '\\\ ':''),
                        $idx, 0
                    );
                }
            }
        }
    }
}

