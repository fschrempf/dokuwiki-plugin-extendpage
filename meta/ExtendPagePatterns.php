<?php

namespace dokuwiki\plugin\extendpage\meta;

/**
 * Class ExtendPagePatterns
 *
 * Manages the assignment of header, footer pages to pages and namespaces
 *
 * This is a singleton. Assignment data is only loaded once per request.
 *
 * @package dokuwiki\plugin\extendpage\meta
 */
class ExtendPagePatterns
{
    /** @var ExtendPagePatterns */
    protected static $instance = null;

    /** @var  array All the assignments patterns */
    protected $patterns;

    /** @var conffile */
    protected $conffile = DOKU_CONF . 'extendpage.conf';

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
        $this->loadPatterns();
    }

    /**
     * Load existing assignment patterns
     */
    protected function loadPatterns()
    {
        if (!file_exists($this->conffile)) {
            touch($this->conffile);
        }

        $file = file($this->conffile);
        $this->patterns = array();

        foreach ($file as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line)); //ignore comments
            if (!$line) continue;

            $pat = preg_split('/[ \t]+/', $line);

            //0 is pattern, 1 is pos, 2 is page
            $this->patterns[] = array(
                'pattern' => $pat[0],
                'pos' => $pat[1],
                'page' => $pat[2],
            );
        }
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
        foreach ($this->patterns as $row) {
            if ($row['pattern'] === $pattern && $row['pos'] === $pos) {
                msg('pattern already exists', -1);
                return $false;
            }
        }

        $extend = "$pattern\t$pos\t$page\n";
        $ok = io_saveFile($this->conffile, $extend, true);
        $this->loadPatterns();
        return $ok;
    }

    /**
     * Remove an existing assignment pattern from the pattern table
     *
     * @param string $pattern
     * @param string $pos
     * @return bool
     */
    public function removePattern($pattern, $pos)
    {
        $extend = '^'.preg_quote($pattern, '/').'[ \t]+'.$pos.'[ \t]+.*$';
        $ok = io_deleteFromFile($this->conffile, "/$extend/", true);
        $this->loadPatterns();
        return $ok;
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
     * @return \string[] extensions assigned
     */
    public function getPageAssignments($page, $pos)
    {
        $extensions = array();
        $page = cleanID($page);

        // evaluate patterns
        $pns = ':' . getNS($page) . ':';
        foreach ($this->patterns as $row) {
            if (($this->matchPagePattern($row['pattern'], $page, $pns)) &&
                ($row['pos'] === $pos)) {
                $extensions[] = array(
                    'page' => $row['page']
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
}
