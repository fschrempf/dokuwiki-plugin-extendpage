<?php

namespace dokuwiki\plugin\extendpage\meta;

/**
 * Class Assignments
 *
 * Manages the assignment of header, footer pages to pages and namespaces
 *
 * This is a singleton. Assignment data is only loaded once per request.
 *
 * @package dokuwiki\plugin\extendpage\meta
 */
class Assignments
{

    /** @var \helper_plugin_sqlite|null */
    protected $sqlite;

    /** @var  array All the assignments patterns */
    protected $patterns;

    /** @var Assignments */
    protected static $instance = null;

    /**
     * Get the singleton instance of the Assignments
     *
     * @param bool $forcereload create a new instace to reload the assignment data
     * @return Assignments
     */
    public static function getInstance($forcereload = false)
    {
        if (is_null(self::$instance) or $forcereload) {
            $class = get_called_class();
            self::$instance = new $class();
        }
        return self::$instance;
    }

    /**
     * Assignments constructor.
     *
     * Not public. Use Assignments::getInstance() instead
     */
    protected function __construct()
    {
        /** @var \helper_plugin_extendpage_db $helper */
        $helper = plugin_load('helper', 'extendpage_db');
        $this->sqlite = $helper->getDB();

        $this->loadPatterns();
    }

    /**
     * Load existing assignment patterns
     */
    protected function loadPatterns()
    {
        $sql = 'SELECT * FROM assignments_patterns ORDER BY pattern';
        $res = $this->sqlite->query($sql);
        $this->patterns = $this->sqlite->res2arr($res);
        $this->sqlite->res_close($res);
    }

    /**
     * Add a new assignment pattern to the pattern table
     *
     * @param string $pattern
     * @param string $page
     * @return bool
     */
    public function addPattern($pattern, $page, $pos)
    {
        // add the pattern
        $sql = 'REPLACE INTO assignments_patterns (pattern, page, pos) VALUES (?,?,?)';
        $ok = (bool) $this->sqlite->query($sql, array($pattern, $page, $pos));

        $sql = 'SELECT last_insert_rowid()';
        $res = $this->sqlite->query($sql);

        // reload patterns
        $this->loadPatterns();
        $this->propagatePageAssignments($this->sqlite->res2single($res));

        return $ok;
    }

    /**
     * Remove an existing assignment pattern from the pattern table
     *
     * @param string $pattern
     * @param string $page
     * @return bool
     */
    public function removePattern($id)
    {
        // remove the pattern
        $sql = 'DELETE FROM assignments_patterns WHERE id = ?';
        $ok = (bool) $this->sqlite->query($sql, array($id));

        // reload patterns
        $this->loadPatterns();

        // fetch possibly affected pages
        $sql = 'SELECT pid FROM assignments WHERE pattern_id = ?';
        $res = $this->sqlite->query($sql, $id);
        $pagerows = $this->sqlite->res2arr($res);
        $this->sqlite->res_close($res);

        // reevalute the pages and unassign when needed
        foreach ($pagerows as $row) {
            $pages = $this->getPageAssignments($row['pid'], $row['pos'], true);
            if (!in_array($page, $pages)) {
                $this->deassignPageExtension($row['pid'], $row['pos']);
            }
        }

        return $ok;
    }

    /**
     * Clear all patterns - deassigns all pages
     *
     * This is mostly useful for testing and not used in the interface currently
     *
     * @param bool $full fully delete all previous assignments
     * @return bool
     */
    public function clear($full = false)
    {
        $sql = 'DELETE FROM assignments_patterns';
        $ok = (bool) $this->sqlite->query($sql);

        if ($full) {
            $sql = 'DELETE FROM assignments';
        } else {
            $sql = 'UPDATE assignments SET assigned = 0';
        }
        $ok = $ok && (bool) $this->sqlite->query($sql);

        // reload patterns
        $this->loadPatterns();

        return $ok;
    }

    /**
     * Add page to assignments
     *
     * @param string $page
     * @param string $ext
     * @return bool
     */
    public function assignPageExtension($page, $pattern)
    {
        $sql = 'REPLACE INTO assignments (pid, pattern_id, assigned) VALUES (?, ?, 1)';
        return (bool) $this->sqlite->query($sql, array($page, $pattern));
    }

    /**
     * Remove page from assignments
     *
     * @param string $page
     * @param string $ext
     * @return bool
     */
    public function deassignPageExtension($page, $pattern)
    {
        $sql = 'REPLACE INTO assignments (pid, pattern_id, assigned) VALUES (?, ?, 0)';
        return (bool) $this->sqlite->query($sql, array($page, $pattern));
    }

    /**
     * Get the whole pattern table
     *
     * @return array
     */
    public function getAllPatterns()
    {
        return $this->patterns;
    }

    /**
     * Returns a list of extension page names assigned to the given page and position
     *
     * @param string $page
     * @param string $pos
     * @param bool $checkpatterns Should the current patterns be re-evaluated?
     * @return \string[] extensions assigned
     */
    public function getPageAssignments($page, $pos, $checkpatterns = true)
    {
        $extensions = array();
        $page = cleanID($page);

        if ($checkpatterns) {
            // evaluate patterns
            $pns = ':' . getNS($page) . ':';
            foreach ($this->patterns as $row) {
                if (($this->matchPagePattern($row['pattern'], $page, $pns)) &&
                    ($row['pos'] === $pos)) {
                    $extensions[] = array('page' => $row['page']);
                }
            }
        } else {
            // just select
            $sql = 'SELECT assignments_patterns.page
                    FROM assignments, assignments_patterns
                    WHERE assignments.pattern_id = assignments_patterns.id
                    AND assignments.pid = ?
                    AND assignments_patterns.pos = ?
                    AND assignments.assigned = 1';
            $res = $this->sqlite->query($sql, array($page, $pos));
            $list = $this->sqlite->res2arr($res);
            $this->sqlite->res_close($res);
            foreach ($list as $row) {
                $extensions[] = array(
                    'page' => $row['assignments_patterns.page']
                );
            }
        }

        return array_unique($extensions);
    }

    /**
     * Check if the given pattern matches the given page
     *
     * @param string $pattern the pattern to check against
     * @param string $page the cleaned pageid to check
     * @param string|null $pns optimization, the colon wrapped namespace of the page, set null for automatic
     * @return bool
     */
    protected function matchPagePattern($pattern, $page, $pns = null)
    {
        if (trim($pattern, ':') == '**') return true; // match all

        // regex patterns
        if ($pattern[0] == '/') {
            return (bool) preg_match($pattern, ":$page");
        }

        if (is_null($pns)) {
            $pns = ':' . getNS($page) . ':';
        }

        $ans = ':' . cleanID($pattern) . ':';
        if (substr($pattern, -2) == '**') {
            // upper namespaces match
            if (strpos($pns, $ans) === 0) {
                return true;
            }
        } elseif (substr($pattern, -1) == '*') {
            // namespaces match exact
            if ($ans == $pns) {
                return true;
            }
        } else {
            // exact match
            if (cleanID($pattern) == $page) {
                return true;
            }
        }

        return false;
    }

    /**
     * fetch all pages where the extension page isn't assigned, yet and reevaluate the page assignments for those pages and assign when needed
     *
     * @param $page
     */
    public function propagatePageAssignments($pattern)
    {
        $sql = 'SELECT assignments.pid, assignments.pattern_id, assignments_patterns.pos
                FROM assignments, assignments_patterns
                WHERE assignments.pattern_id = assignments_patterns.id
                AND assignments.assigned != 1 AND assignments.pattern_id = ?';
        $res = $this->sqlite->query($sql, $pattern);
        $pagerows = $this->sqlite->res2arr($res);
        $this->sqlite->res_close($res);

        foreach ($pagerows as $row) {
            $pages = $this->getPageAssignments(
                $row['assignments.pid'],
                $row['assignments_patterns.pos'], true
            );
            if (in_array($row['assignments_patterns.page'], $pages)) {
                $this->assignPageExtension($row['assignments.pid'], $pattern);
            }
        }
    }
}
