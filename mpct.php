#!/usr/bin/php
<?php
/**
 * My mpc wrapper.
 *
 * Automates my music selection by adding random music to my playlist.
 *
 * My randomness isn't the most bad ass. Rather than pulling from the collection
 * in a truly random fashion (where every track has an equal chance of being 
 * played) I list the starting directory, choose a dir at random, then a random 
 * item from within that directory, going deeper into the tree until I randomly 
 * select a track. This is not truly the most random, but I don't think I'm too 
 * bothered by it.
 *
 * Before this implementation, I was getting teh full list of albums from mpc 
 * (and caching it) but this approach had its drawbacks.
 *
 * I'd love to hear about any and all comments / improvements!
 *
 * @author thread <thethread@gmail.com>
 * @website http://www.threadbox.net/
 */
MPCWorker::runFromCLIArguments($argv);

class MPCWorker
{
    const VERSION = '0.5';

    /**
     * The parameter defaults, overridden by the config file & command line
     *
     * @var array
     */
    protected static $paramDefaults = array(
        'func'       => null,   // Actual function to call. It will gather the target(s)
        'action'     => 'mpc',  // 'mpc' to add to mpd, 'deadbeef' to send it there,
                                //   or 'list' to simply list the hits

        'host'       => 'localhost',
        'port'       => '6600',
        'mpc'        => '/usr/bin/mpc',
        'refresh'    => false,  // refresh MPD's latestRoot first
        'num'        => null,   // num of results. defaults depending on action.
        'append'     => false,  // just add to the end of the playlist
        'choose'     => false,  // choose from the results !
        'bt'         => null,   // short code for "by toplevel"
        'simpleOut'  => false,  // Simple listing, used if action is list
        'exe'        => null,   // execute cmd for each hit. X is replaced with result.
        'exclude'    => array(),  // array of dirs to skip over
        'modes'      => array(),  // label => array of key/vals to override parameters
        'mode'       => null,   // selected label of modes array, if any
        'fullPaths'  => false,  // show system-absolute pathnames in output
        'debug'      => false,
        'quiet'      => false,

        'rawCmd'     => null,   // overrides everything, execute args for mpc

        // latest-mode params
        'latestRoot' => '/storage/music/tmp/stage2',  // root dir for latest music
        'mpdRoot'    => '/storage/music',             // root dir of mpd
        'deep'       => 2,      // how many dirs deep to look

        // internal only
        'mpcCmd'     => null,   // full mpc cmd prefix: binary, host, port
    );

    /**
     * Top-level directory map (short code => full path)
     *
     * The keys will be the short codes usable with the --by-toplevel/-bt flag. 
     *
     * ALSO: When you do regular random (not by toplevel) these dirs will also 
     * be used for randomness. I've left a few that I am not generally 
     * interested in out of this list. If you want to start at the root and 
     * have random music selected from the entire collection, simply empty this
     * array.
     *
     * @var array
     */
    protected static $toplevelMap = array();

    /**
     * Just the values from $toplevelMap
     *
     * @var array
     */
    protected static $toplevelDirs = array();

    /**
     * Are we picking music from only defined toplevels? if false, 
     * full-collection randomness is used.
     *
     * @var boolean
     */
    protected static $btRandom = null;

    /**
     * Resulting array of "mpc ls /" just so we don't have to keep asking for it.
     *
     * @var array
     */
    protected static $rootCache = array();

    /**
     * Actual params being used
     *
     * @var array
     */
    protected $params = array();

    /**
     * Protected contructor. Invoke this object with runFromCLIArguments()
     *
     * @param array $params
     */
    protected function __construct(array $params)
    {
        $this->params = $params;

        $this->params['mpcCmd'] = sprintf('%s -h %s -p %s',
            $this->params['mpc'],
            $this->params['host'],
            $this->params['port']);

        if ($this->params['debug']) {
            print_r($this->params);
        }

        if ($this->params['refresh']) {
            $this->refreshMpd();
        }
    }

    /**
     * Do yo shit (from the command line arguments)
     *
     * @param array $argv
     * @param array $toplevelMap
     * @return null
     */
    public static function runFromCLIArguments($argv)
    {
        @include 'mpct.conf.php';
        @include '~/.mpct.conf.php';

        self::$toplevelMap  = isset($map) && is_array($map) ? $map : array();
        self::$toplevelDirs = array_values(self::$toplevelMap);
        self::$btRandom     = (bool)self::$toplevelMap;

        $params = array();
        $myargs = array();

        array_shift($argv);  // take off the script name. useless.

        // I don't like this code much at all. It splits up a single arg into 
        // multiple ones so my alfred extension works. (Alfred sends all 
        // parameters, including spaces, as a single argument.)
        foreach ($argv as $av) {
            foreach (split(' ', $av) as $c) {
                $myargs[] = $c;
            }
        }

        while ($myargs) {
            switch ($arg = array_shift($myargs)) {
            case '--host': case '-h':
                $params['host'] = array_shift($myargs);
                break;
            case '--port': case '-p':
                $params['host'] = array_shift($myargs);
                break;
            case '--mpc':
                $params['mpc'] = array_shift($myargs);
                break;
            case '--refresh': case '-r':
                $params['refresh'] = true;
                break;
            case '--list': case '-l':
                $params['action'] = 'list';
                break;
            case '--simple': case '-i':
                $params['action'] = 'list';
                $params['simpleOut'] = true;
                break;
            case '--raw':
                $params['func'] = 'raw';
                $params['rawCmd'] = implode(' ', $myargs);
                $myargs = array();  // all done!
                break;
            case '--latest': case '-la':
                if (!isset($params['num'])) $params['num'] = 20;
                $params['func'] = 'latest';
                break;
            case '--search-artist': case '-sa':
                $params['func']       = 'search';
                $params['searchType'] = 'artist';
                $params['search']     = implode(' ', $myargs);
                $myargs = array();  // all done !
                break;
            case '--search-album': case '-sb':
                $params['func']       = 'search';
                $params['searchType'] = 'album';
                $params['search']     = implode(' ', $myargs);
                $myargs = array();  // all done !
                break;
            case '--search-title': case '-st':
                $params['func']       = 'search';
                $params['searchType'] = 'title';
                $params['search']     = implode(' ', $myargs);
                $myargs = array();  // all done !
                break;
            case '--random-tracks': case '-rt':
                if (!isset($params['num'])) $params['num'] = 10;
                $params['func'] = 'randomTracks';
                break;
            case '--random-albums': case '-ra':
                if (!isset($params['num'])) $params['num'] = 1;
                $params['func'] = 'randomAlbums';
                break;
            case '--this-album': case '-ta':
                $params['func'] = 'playThisAlbum';
                break;
            case '--choose': case '-c':
                $params['choose'] = true;
                break;
            case '--append': case '-a':
                $params['append'] = true;
                break;
            case '--execute': case '-x':
                $params['exe'] = implode(' ', $myargs);
                $myargs = array();  // all done !
                break;
            case '--num': case '-n':
                if (!($arg = array_shift($myargs))
                  || !ctype_digit($arg)
                ) {
                    die("--num must be followed by a number\n");
                }
                $params['num'] = $arg;
                break;
            case '--by-toplevel': case '-bt':
                $params['bt'] = true;
                if ($arg = array_shift($myargs)) {
                    if (self::getToplevel($arg)) {
                        $params['bt'] = $arg;
                    } else {
                        array_unshift($myargs, $arg); // put it back
                    }
                }
                break;
            case '--get-toplevels':
                // imma keep dis secret. only really needed for the web interface.
                foreach (self::$toplevelMap as $k => $v) {
                    echo "$k $v\n";
                }
                die();
                break;
            case '--full-paths': case '-f':
                $params['fullPaths'] = true;
                break;
            case '--debug': case '-d':
                $params['debug'] = true;
                break;
            case '--quiet': case '-q':
                $params['quiet'] = true;
                break;
            case '--mode': case '-o':
                if (!$arg = array_shift($myargs)) {
                    die("Mode parameter was missing.\n");
                }
                $params['mode'] = $arg;
                break;
            case '--help': case '-?':
                $params['func'] = 'help';
                break;
            default:
                die("what?: $arg");
            }
        }

        $configFileParams = isset($p) && is_array($p) ? $p : array();

        $final = array_merge(self::$paramDefaults, $configFileParams);

        if (array_key_exists('modes', $final) && isset($params['mode'])) {
            $final = array_merge($final, $final['modes'][$params['mode']]);
        }

        // command-line params last !
        $final = array_merge($final, $params);

        $w = new self($final);
        $w->invoke();
    }

    /**
     * Display help text and exit
     *
     * @return null
     */
    protected function help()
    {
        $version = self::VERSION;
        echo "
DJ Thread's MPC Tool, v$version

The default action is to replace your MPD playlist and hit play.

Action Overrides:
 -b,  --deadbeef       Add to deadbeef instead of MPD
 -l,  --list           List only mode
 -i,  --simple         Simple list only mode
      --raw            The rest of the command line will go straight to mpc

General:
 -la, --latest         Use latest albums
 -rt, --random-tracks  Use random tracks 
 -ra, --random-albums  Use random albums
 -sa, --search-artist  The rest of the command line is an artist search term
 -sb, --search-album   The rest of the command line is an artist search term
 -st, --search-title   The rest of the command line is a track title search term
 -ta, --this-album     Use the currently playing album
 -bt, --by-toplevel    Ask which toplevel dir to use (a short code CAN follow)
 -n,  --num            Number of tracks to add (default depends on action)
 -c,  --choose         Select one or more from the results
 -a,  --append         Add tunage, preserving the current playlist
 -x,  --execute        The rest of the command line is a command to execute on each
                       result. X is replaced with the absolute location for each.
      --mpc            Full path to mpc executable
 -h,  --host           set the target host (default: localhost)
 -p,  --port           set the target port (default: 6600)
 -r,  --refresh        Refresh MPD's latestRoot first
 -f,  --full-paths     Show system-absolute pathnames in output
 -d,  --debug          echo debugging information
 -q,  --quiet          Sssshhh
 -o,  --mode           Specify the key of the \$p['modes'] array in the config
                       file to overwrite default params with.
 -?,  --help           this.

";
        exit;
    }

    /**
     * Invoke the func param and get on with it !
     *
     * @return null
     */
    public function invoke()
    {
        $func = $this->params['func'] ?: 'help';
        $this->$func();
    }

    /**
     * Add some number of random tunes to the playlist. Play.
     *
     * @param array $params
     * @return null
     */
    public function randomTracks()
    {
        $tracks = array();
        for ($i=0; $i<$this->params['num']; $i++) {
            $tracks[] = $this->getRandomTrack();
        }

        $this->act($tracks);
    }

    /**
     * @return null
     */
    public function randomAlbums()
    {
        $albums = array();

        for ($i=0; $i<$this->params['num']; $i++) {
            $track = $this->getRandomTrack();
            if (!preg_match('/^(.+)\//', $track, $matches)) {
                $this->out("Couldn't strip dir off of file: $track\n");
                $i--; continue;
            }

            $albums[] = $matches[1];
        }

        $this->act($albums);
    }

    /**
     * Add to the playlist and play the album that the current song belongs to.
     *
     * @return null
     */
    public function playThisAlbum()
    {
        $file = $this->mpc('-f %file% current');

        if (!$file || !isset($file[0])
          || !preg_match('/^(.+)\//', $file[0], $matches)
        ) {
            $this->out("The current track doesn't belong to an album!",
                array('fatal' => true));
        }

        $this->act(array($matches[1]));
    }

    /**
     * Execute a raw command with mpc
     *
     * @return null
     */
    public function raw()
    {
        $this->mpc($this->params['rawCmd'], false, true);
    }

    /**
     * Returns the full path to a random track
     *
     * @return null
     */
    public function getRandomTrack()
    {
        $dirs = $this->lsStartDir();
        $cur  = $dirs[rand(0, count($dirs) - 1)];

        while (!preg_match('/(\.flac|\.mp3|\.ogg)$/i', $cur)) {
            if (!$dirs) {
                $this->out("Hit a dead end: $cur", array('debug' => true));
                $dirs = $this->lsStartDir();
                $cur  = $dirs[rand(0, count($dirs) - 1)];
                continue;
            }
            $dirs = $this->lsDir($cur);
            $cur  = $dirs[rand(0, count($dirs) - 1)];
        }

        return $cur;
    }

    /**
     * List the contents of the starting dir in our spider into the collection.
     *
     * @return array
     */
    protected function lsStartDir()
    {
        $dirs = array();

        if (self::$btRandom) {

            $tl = $this->params['bt']
                ? self::getToplevel($this->params['bt'])
                : self::$toplevelDirs[rand(0, count(self::$toplevelDirs) - 1)];

            if (!$dirs = $this->lsDir($tl)) {
                // ok, some toplevel is empty or something.
                // no more by-toplevel randomness anymore.
                $this->out("Toplevel '$tl' is empty or missing. Toplevel dirs "
                    . 'are disabled.', array('debug' => true));
                self::$btRandom = false;
            }
        }

        return $dirs ?: $this->lsDir('/');
    }

    /**
     * List a directory
     *
     * If we're doing it by toplevel, and we find a dir that doesn't exist, 
     * switch by-toplevel off.
     *
     * @param string $dir
     * @return array
     */
    protected function lsDir($dir)
    {
        if ($dir == '/') {
            if (!self::$rootCache) {
                self::$rootCache = $this->mpc('ls /');
            }
            return self::$rootCache;
        }

        return $this->mpc('ls "' . self::quotefix($dir) . '"');
    }

    /**
     * @param string $str
     * @return string
     */
    protected static function quotefix($str)
    {
        return str_replace(
            array('"', '$'),
            array('\\"', '\\$'),
            $str);
    }

    /**
     * Echo the MPC info that I want
     *
     * @return null
     */
    protected function echoStatus()
    {
        $mpc = $this->mpc();
        if (isset($mpc[0]) && isset($mpc[1])) {
            $this->out("{$mpc[0]}\n{$mpc[1]}");
        } else {
            $this->out(implode("\n", $mpc));
        }
    }

    /**
     * Take the chosen action on a given item with an mpd-relative path !
     *
     * @param string|array $items
     * @return null
     */
    protected function act($items)
    {
        if ($this->params['debug']) echo "\n";

        if ($this->params['choose']) {
            $items = $this->getSelectionFrom($items, true);
        }

        $c = count($items);
        for ($i=0; $i<$c; $i++) {
            $x = is_string($items[$i]) ? array('name' => $items[$i]) : $items[$i];

            if ($this->params['action'] == 'mpc') {
                if ($i == 0 && !$this->params['append']) $this->mpc('clear');
                $this->mpc('add "' . self::quotefix($x['name']) . '"',
                    !$this->params['quiet']);
                if ($i == 0 && !$this->params['append']) $this->mpc('play');
            } else if ($this->params['action'] == 'deadbeef') {
                $this->cmd('deadbeef "' . self::quotefix($x['name']) . '"',
                    !$this->params['quiet']);
            } else if ($this->params['action'] == 'list') {
                $disp = isset($x['disp']) ? $x['disp'] : $x['name'];
                echo "$disp\n";
            } else {
                $this->out("Action is what ({$this->action}) ?", array('fatal' => true));
            }

            if ($this->params['exe']) {
                $cmd = str_replace('X',
                    '"' . self::quotefix($this->params['mpdRoot'] . '/' . $x['name']) . '"',
                    $this->params['exe']);
                $this->cmd($cmd, true, true);
            }
        }

        if ($this->params['action'] == 'mpc') {
            $this->echoStatus();
        }
    }

    protected function refreshMpd()
    {
        $it = str_replace($this->params['mpdRoot'] . '/', '',
            $this->params['latestRoot']);
        $g = $this->mpc('update "' . self::quotefix($it) . '"', true);
        echo "Waiting for DB to refresh..";
        do {
            echo '.';
            sleep(1);
            list($in, $out) = $this->mpc();
            $lines = split("\n", $out);
            foreach ($lines as $l) {
                $found = false;
                if (strpos($l, 'Updating DB') === 0) {
                    $found = true;
                    break;
                }
            }
        } while ($found);
        echo "DB Updated!\n";
    }

    /**
     * @param string $cmd
     * @param boolean $echoCmd
     * @param boolean $echoResult
     * @return array
     */
    public function mpc($cmd = null, $echoCmd = false, $echoResult = false)
    {
        $cmd = $this->params['mpcCmd'] .  ' ' . $cmd;
        return $this->cmd($cmd, $echoCmd, $echoResult);
    }

    /**
     * Run a command. Return the lines of returned output in an array.
     *
     * @param string $cmd
     * @return array
     */
    protected function cmd($cmd, $echoCmd, $echoResult)
    {
        $list = array();

        if ($echoCmd || $this->params['debug']) {
            $this->out(' -> ' . $cmd);
        }

        exec($cmd, $list);

        if ($list && ($echoResult || $this->params['debug'])) {
            $this->out(implode("\n", $list));
        }

        return $list;
    }

    /**
     * Output a line
     *
     * @param string $msg
     * @param array $o
     * @return null
     */
    protected function out($msg, $o = array())
    {
        $o = array(
            'debug' => false,
            'fatal' => false,
        ) + $o;

        if ($o['debug'] && !$this->params['debug']) return;

        echo "$msg\n";

        if ($o['fatal']) die();
    }

    /**
     * @param string $short
     * @return string (the full toplevel dir name)
     */
    protected static function getToplevel($short = null)
    {
        if ($short && $short !== true) {
            if (array_key_exists($short, self::$toplevelMap)) {
                return self::$toplevelMap[$short];
            } else {
                return false;
            }
        }

        return self::getSelectionFrom(self::$toplevelMap);
    }

    /**
     * Allow the user to select one or more items from a list
     *
     * @param array $options
     * @param boolean $multi
     * @return array|string
     */
    protected static function getSelectionFrom(array $options, $multi = false)
    {
        // is the array associative?
        $isAssoc = array_keys($options) !== range(0, count($options) - 1);

        echo "\n";
        foreach ($options as $k => $v) {
            if (is_array($v)) $v = isset($v['disp']) ? $v['disp'] : $v['name'];
            if ($isAssoc) {
                echo "    $k. $v\n";
            } else {
                echo sprintf('%5d. ', $k+1) . "$v\n";
            }
        }
        echo "\n";

        $stdin = fopen('php://stdin', 'r');
        $ret   = null;
        do {
            echo "  > ";
            $choice = trim(fgets($stdin));
            $fail   = false;
            if ($multi) {
                $ret = array();
                foreach (preg_split('/[, ]+/', $choice) as $bit) {
                    if ($isAssoc) {                  // associative arrays
                        if (!array_key_exists($bit, $options)) {
                            $this->out("Invalid input: $bit");
                            $fail = true;
                        }
                        $ret[] = $options[$bit];
                    } else if (ctype_digit($bit)) {  // numeric-indexed array
                        $b = $bit - 1;
                        if (!array_key_exists($b, $options)) {
                            $this->out("Invalid input: $bit");
                            $fail = true;
                        }
                        $ret[] = $options[$b];
                    } else if (preg_match('/^(\d+)-(\d+)$/', $bit, $m)
                      && $m[1] > 0 && $m[1] <= count($options)
                      && $m[2] > 0 && $m[2] <= count($options)
                      && $m[1] <= $m[2]
                    ) {                             // also for numeric-indexed arrays
                        for ($i=$m[1]; $i<=$m[2]; $i++) {
                            $ret[] = $options[$i-1];
                        }
                    } else {
                        $this->out("Invalid input: $bit");
                        $fail = true;
                    }
                }
            } else {
                $c = $isAssoc ? $choice : $choice-1;
                if (!array_key_exists($c, $options)) {
                    $this->out("Invalid input: $choice");
                    $fail = true;
                }
                $ret = $options[$c];
            }
        } while ($fail);
        fclose($stdin);

        return $ret;
    }

    /**
     * Find my latest music by modification time!
     *
     * @return null
     */
    public function latest()
    {
        $dirs = $this->recurse($this->params['latestRoot']);

        // Sort them by mod time
        usort($dirs, function($a, $b) {
            return $a['mtime'] < $b['mtime'];
        });

        $n = $this->params['num'] < count($dirs) ? $this->params['num'] : count($dirs);
        $target = array_slice($dirs, 0, $n);

        foreach ($target as &$t) {
            $t['disp'] = str_replace($this->params['latestRoot'] . '/', '', $t['name']);
            if (!$this->params['simpleOut']) {
                $t['disp'] .= ' [' . date('Y-m-d', $t['mtime']) . ']';
            }
            $t['name'] = str_replace($this->params['mpdRoot'] . '/', '', $t['name']);
        }

        $this->act($target);
    }

    /**
     * Gather a list of targets via mpc search
     *
     * @return null
     */
    public function search()
    {
        if ($this->params['searchType'] == 'title') {
            $list = $this->mpc('search title "'
                  . self::quotefix($this->params['search']) . '"');
        } else {
            $list = array();
            $all = $this->mpc('search ' . $this->params['searchType']
                  . ' "' . self::quotefix($this->params['search']) . '"');
            foreach ($all as $i) {
                if (preg_match('/^(.+)\//', $i, $matches)
                  && !in_array($matches[1], $list)
                ) {
                    $list[] = $matches[1];
                }
            }
        }

        $this->act($list);
    }

    /**
     * Recuse down a given dir to a certain depth. Collect all deepest hits.
     *
     * @param string $dir
     * @param integer|null $d
     * @return array
     */
    protected function recurse($dir, $d = null)
    {
        if (is_null($d)) $d = $this->params['deep'];

        $dirs = array();  // each is: array('name' => name, 'mtime' => mtime);
        $glob = glob("$dir/*", GLOB_ONLYDIR);
        if (!$glob) return array();
        foreach ($glob as $t) {

            // if it's the top level of the search, make sure we skip any dirs in 
            // the exclude array.
            if ($d == $this->params['deep']) {
                preg_match('/([^\/]+)$/', $t, $matches);
                if (in_array($matches[1], $this->params['exclude'])) {
                    continue;
                }
            }

            if ($d > 1) {
                $dirs = array_merge($dirs, $this->recurse($t, $d - 1));
            } else {
                $s = stat($t);
                $dirs[] = array(
                    'name'  => $t,
                    'mtime' => $s['mtime']
                );
            }
        }

        return $dirs;
    }
}
