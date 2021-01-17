<?php
/**
 * DokuWiki Plugin extendpage (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Frieder Schrempf <dev@fris.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

use dokuwiki\plugin\extendpage\meta\Assignments;

class admin_plugin_extendpage extends DokuWiki_Admin_Plugin
{

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 600;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly()
    {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle()
    {
        global $INPUT;
        global $ID;

        try {
            $assignments = Assignments::getInstance();
        } catch (ExtendPageException $e) {
            msg($e->getMessage(), -1);
            return false;
        }

        if ($INPUT->str('action') && $INPUT->arr('assignment') && checkSecurityToken()) {
            $assignment = $INPUT->arr('assignment');
            if (!blank($assignment['pattern']) && !blank($assignment['page'])) {
                if ($INPUT->str('action') === 'delete') {
                    $ok = $assignments->removePattern($assignment['pattern'], $assignment['page'],
                                                      $assignment['pos']);
                    if (!$ok) msg('failed to remove pattern', -1);
                } elseif ($INPUT->str('action') === 'add') {
                    if ($assignment['pattern'][0] == '/') {
                        if (@preg_match($assignment['pattern'], null) === false) {
                            msg('Invalid regular expression. Pattern not saved', -1);
                        } else {
                            $ok = $assignments->addPattern($assignment['pattern'], $assignment['page'],
                                                           $assignment['pos']);
                            if (!$ok) msg('failed to add pattern', -1);
                        }
                    } else {
                        $ok = $assignments->addPattern($assignment['pattern'], $assignment['page'],
                                                       $assignment['pos']);
                        if (!$ok) msg('failed to add pattern', -1);
                    }
                }
            }

            send_redirect(wl($ID, array('do' => 'admin', 'page' => 'extendpage'), true, '&'));
        }
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html()
    {
        global $ID;

        try {
            $ass = Assignments::getInstance();
        } catch (ExtendPageException $e) {
            msg($e->getMessage(), -1);
            return false;
        }
        $assignments = $ass->getAllPatterns();

        echo '<form action="' . wl($ID) . '" action="post">';
        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="extendpage" />';
        echo '<input type="hidden" name="sectok" value="' . getSecurityToken() . '" />';
        echo '<table class="inline">';

        // header
        echo '<tr>';
        echo '<th>Pages/Namespace</th>';
        echo '<th>Position</th>';
        echo '<th>Extension Page</th>';
        echo '<th></th>';
        echo '</tr>';

        // existing assignments
        foreach ($assignments as $assignment) {
            $pattern = $assignment['pattern'];
            $pos = $assignment['pos'];
            $page = $assignment['page'];

            $link = wl(
                $ID,
                array(
                'do' => 'admin',
                'page' => 'extendpage',
                'action' => 'delete',
                'sectok' => getSecurityToken(),
                'assignment[pattern]' => $pattern,
                'assignment[pos]' => $pos,
                'assignment[page]' => $page,
                )
            );

            echo '<tr>';
            echo '<td>' . hsc($pattern) . '</td>';
            echo '<td>' . hsc($pos) . '</td>';
            echo '<td>' . hsc($page) . '</td>';
            echo '<td><a class="deletePage" href="' . $link . '">Delete</a></td>';
            echo '</tr>';
        }

        // new assignment form
        echo '<tr>';
        echo '<td><input type="text" name="assignment[pattern]" /></td>';
        echo '<td><select name="assignment[pos]">';
        echo '<option value="top">Top</option>';
        echo '<option value="bottom">Bottom</option></select></td>';
        echo '<td><input type="text" name="assignment[page]" /></td>';
        echo '<td><button type="submit" name="action" value="add">Add</button></td>';
        echo '</tr>';

        echo '</table>';
        echo '</form>';
    }
}

