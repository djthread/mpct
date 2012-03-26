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
    const VERSION = '0.6';

    /**
     * The parameter defaults, overridden by the config file & command line
     *
     * @var array
     */
    protected static $paramDefaults = array(
        'func'       => null,   // Actual function to call. It will gather the target(s)
        'action'     => 'mpc',  // 'mpc' to add to mpd, 'deadbeef' to send it there,
                                //   exe to execute cmds, or 'list' to simply list the
                                //   hits

        'host'       => null,   // override the host default/environment
        'port'       => null,   // override the host default/environment
        'mpc'        => null,   // full path/file to the mpc binary
        'refresh'    => false,  // refresh MPD's latestRoot
        'num'        => null,   // num of results. defaults depending on action.
        'append'     => false,  // just add to the end of the playlist
        'choose'     => true,   // choose from the results !
        'bt'         => null,   // short code for "by toplevel"
        'simpleOut'  => false,  // Simple listing, used if action is list
        'exe'        => null,   // execute cmd for each hit. X is replaced with result.
        'exclude'    => array(),  // array of dirs to skip over
        'modes'      => array(),  // label => array of key/vals to override parameters
        'mode'       => null,   // selected label of modes array, if any
        'fullPaths'  => false,  // show system-absolute pathnames in output
        'colors'     => true,   // use colors! yayy!! \o/
        'debug'      => false,  // show tons of unorganized output
        'quiet'      => false,  // show less output... different output
        'extensions' => 'flac,mp3,ogg',  // extensions to look for on music files

        'rawCmd'     => null,   // overrides everything, execute args for mpc

        // latest-mode params
        'latestRoot' => '/storage/music/tmp/stage2',  // root dir for latest music
        'mpdRoot'    => '/storage/music',             // root dir of mpd
        'deep'       => 2,                            // how many dirs deep to look

        // override if you must...
        'defNumTrks' => 10,    // default num for --random-tracks
        'defNumAlbs' => 10,    // default num for --random-albums
        'defNumLa'   => 10,    // default num for --latest
        'alfredMode' => false, // do certain things for an alfred plugin

        // internal only
        'mpcCmd'     => null,  // full mpc cmd prefix: binary, host, port
        'extRegex'   => null,  // regex to recognize music file extensions
        'btRandom'   => null,  // "latest": using toplevel picking? false = all music
    );

    /**
     * Actual params being used
     *
     * @var array
     */
    protected static $params = array();

    /**
     * The actual subject items we want to operate on
     *
     * @var array
     */
    protected static $subject = array();

    /**
     * Will be true when the user uses 'm' at the selection prompt.
     *
     * @var boolean
     */
    protected static $more = false;

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
     * Resulting array of "mpc ls /" just so we don't have to keep asking for it.
     *
     * @var array
     */
    protected static $rootCache = array();

    /**
     * Array of albums, loaded from the cache at ~/.mpct/albumCache
     *
     * @var array
     */
    protected static $albumCache = array();

    /**
     * Protected contructor. Invoke this object with runFromCLIArguments()
     *
     * @param array $params
     */
    protected function __construct(array $params)
    {
        self::$params = $params;

        self::$params['mpcCmd'] =
              (self::$params['mpc']  ? self::$params['mpc'] : 'mpc')
            . (self::$params['host'] ? ' -h ' . self::$params['host'] : '')
            . (self::$params['port'] ? ' -p ' . self::$params['port'] : '');

        // Build regex to look for music file extensions
        self::$params['extRegex'] = '/(\.'
            . implode('|\.', split(',', self::$params['extensions']))
            . ')$/i';

        if (!self::$params['num']) {
            switch (self::$params['func']) {
            case 'randomTracks':
                self::$params['num'] = self::$params['defNumTrks'];
                break;
            case 'randomAlbums':
                self::$params['num'] = self::$params['defNumAlbs'];
                break;
            case 'latest':
                self::$params['num'] = self::$params['defNumLa'];
                break;
            default:
                self::$params['num'] = 1;
            }
        }

        if (self::$params['debug']) {
            print_r(self::$params);
        }

        // For refresh flag. Exact action depends on the func.
        if (self::$params['refresh']) {
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
        $cfile = $_SERVER['HOME'] . '/.mpct.conf.php';
        if (file_exists($cfile)) {
            include $cfile;
        }

        self::$toplevelMap  = isset($map) && is_array($map) ? $map : array();
        self::$toplevelDirs = array_values(self::$toplevelMap);
        $params['btRandom'] = (bool)self::$toplevelMap;

        $myargs = array();
        $params = array(
            'btRandom'   => (bool)self::$toplevelMap,
            'alfredMode' => null,
        );

        array_shift($argv);  // take off the script name. useless.

        // I don't like this code much at all. It splits up a single arg into 
        // multiple ones so my alfred extension works. (Alfred sends all 
        // parameters, including spaces, as a single argument.)
        foreach ($argv as $av) {
            if ($av == '--alfred') {
                $params['alfredMode'] = true;
                $params['colors']     = false;
                $params['quiet']      = true;
                break;
            }
        }
        if ($params['alfredMode']) {
            foreach ($argv as $av) {
                if ($av == '--alfred') continue;
                foreach (split(' ', $av) as $c) {
                    $myargs[] = $c;
                }
            }
        } else $myargs = $argv;

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
            case '--refresh': case '-r': case 'r':
                $params['refresh'] = true;
                break;
            case '--list': case '-l': case 'l':
                $params['action'] = 'list';
                $params['choose'] = false;
                break;
            case '--simple': case '-s': case 's':
                $params['action']    = 'list';
                $params['simpleOut'] = true;
                $params['choose']    = false;
                break;
            case '--raw': case '-w': case 'w':
                $params['func'] = 'raw';
                $params['rawCmd'] = implode(' ', $myargs);
                $myargs = array();  // all done!
                break;
            case '--latest': case '-la': case 'la':
                $params['func'] = 'latest';
                break;
            case '--search-artist': case '-sa': case 'sa':
                $params['func']       = 'search';
                $params['searchType'] = 'artist';
                $params['search']     = array_shift($myargs);
                break;
            case '--search-album': case '-sb': case 'sb':
                $params['func']       = 'search';
                $params['searchType'] = 'album';
                $params['search']     = array_shift($myargs);
                break;
            case '--search-title': case '-st': case 'st':
                $params['func']       = 'search';
                $params['searchType'] = 'title';
                $params['search']     = array_shift($myargs);
                break;
            case '--random-tracks': case '-rt': case 'rt':
                $params['func'] = 'randomTracks';
                break;
            case '--random-albums': case '-ra': case 'ra':
                $params['func'] = 'randomAlbums';
                break;
            case '--this-album': case '-ta': case 'ta':
                $params['func']   = 'playThisAlbum';
                $params['choose'] = false;
                break;
            case '--choose': case '-c': case 'c':
                $params['choose'] = true;
                break;
            case '--go': case '-g': case 'g':
                $params['choose'] = false;
                break;
            case '--append': case '-a': case 'a':
                $params['append'] = true;
                break;
            case '--execute': case '-x': case 'x':
                $params['action'] = 'exe';
                $params['exe'] = array_shift($myargs);
                break;
            case '--num': case '-n': case 'n':
                if (!($arg = array_shift($myargs))
                  || !ctype_digit($arg)
                ) {
                    self::out('--num must be followed by a number',
                        array('fatal' => true));
                }
                $params['num'] = $arg;
                break;
            case '--by-toplevel': case '-bt': case 'bt':
                $params['bt'] = null;
                if (($arg = array_shift($myargs))
                  && array_key_exists($arg, self::$toplevelMap)
                ) {
                    $params['bt'] = $arg;
                } else if ($arg) {
                    array_unshift($myargs, $arg); // put it back
                }
                $params['bt'] = $params['bt'] ?: self::getToplevel();
                break;
            case '--full-paths': case '-f': case 'f':
                $params['fullPaths'] = true;
                break;
            case '--debug': case '-d': case 'd':  // undocumented
                $params['debug'] = true;
                break;
            case '--quiet': case '-q': case 'q':
                $params['quiet'] = true;
                break;
            case '--mode': case '-o': case 'o':
                if (!$arg = array_shift($myargs)) {
                    die("Mode parameter was missing.\n");
                }
                $params['mode'] = $arg;
                break;
            case '--aliases': case '-al': case 'al':
                $params['func'] = 'aliases';
                break;
            case '--get-toplevels':
                // imma keep dis secret. only really needed for the web interface.
                foreach (self::$toplevelMap as $k => $v) {
                    echo "$k $v\n";
                }
                die();
                break;
            default:
                die("what?: $arg");
            }
        }

        // building the final parameter array ...
        $final = array_merge(self::$paramDefaults,
            isset($p) && is_array($p) ? $p : array());

        // add on the mode settings
        if ($params['alfredMode'] && array_key_exists('alfred', $final['modes'])) {
            $final = array_merge($final, $final['modes']['alfred']);
        }
        if (array_key_exists('mode', $params)) {
            foreach (split(',', $params['mode']) as $mode) {
                if (array_key_exists('modes', $final)
                  && array_key_exists($mode, $final['modes'])
                ) {
                    $final = array_merge($final, $final['modes'][$mode]);
                } else {
                    die("Couldn't find mode: $mode\n");
                }
            }
        }

        // command-line params last !
        $final = array_merge($final, $params);

        $w    = new self($final);
        $func = self::$params['func'] ?: 'help';

        do {
            self::$more = false;
            $w->$func();
        } while (self::$more);

        $w->act();
    }

    /**
     * Display help text and exit
     *
     * @return null
     */
    protected function help()
    {
        $version = self::VERSION;
        $self    = basename(__FILE__);
        echo "
DJ Thread's MPC Tool, v$version

Subjects (You need one of these):
 -rt, --random-tracks  Use random tracks 
 -ra, --random-albums  Use random albums
 -sa, --search-artist  Use tracks with artist names containing the parameter
 -sb, --search-album   Use tracks with album names containing the parameter
 -st, --search-title   Use tracks with titles containing the parameter
 -ta, --this-album     Use the currently playing album
 -la, --latest         Use latest albums

Action Overrides (Default is to replace MPD playlist and hit play):
 -l,  --list           List only mode
 -s,  --simple         Simple list only mode
 -b,  --deadbeef       Add to deadbeef
 -x,  --execute        Execute the argument for each result. X is replaced with
                       the absolute location. eg. -x 'cp -av X /mnt/hdd'
 -w,  --raw            The rest of the command line will go straight to mpc

Modifiers:
 -bt, --by-toplevel    Ask which toplevel dir to use (a short code CAN follow)
 -n,  --num            Number of tracks to add (default depends on action)
 -c,  --choose         Select one or more from the results (default: on)
 -g,  --go             GO, use all results! (Disable choose mode)
 -a,  --append         Add tunage, preserving the current playlist
      --mpc            Full path to mpc executable
 -h,  --host           set the target host (default: localhost)
 -p,  --port           set the target port (default: 6600)
 -r,  --refresh        Refresh MPD's latestRoot first.
 -f,  --full-paths     Show system-absolute pathnames in output
 -q,  --quiet          Less output
 -o,  --mode           Specify the key(s) of the 'modes' array in the config
                       file to overwrite default params with. eg. -o mode1,mode2
 -al, --aliases        Nifty aliases. Recommended: $self -al >> ~/.bashrc
 -?,  --help           this.
                (The leading hyphen is optional for short flags.)

";
        exit;
    }

    /**
     * Display recommended aliases
     *
     * @return null
     */
    protected function aliases()
    {
        $self = __FILE__;
        echo "
alias m='$self'
alias mrt='$self --random-tracks'
alias mra='$self --random-albums'
alias msa='$self --search-artist'
alias msb='$self --search-album'
alias mst='$self --search-title'
alias mta='$self --this-album'
alias mla='$self --latest'
alias mr='$self --raw'
";
        exit;
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
        for ($i=0; $i<self::$params['num']; $i++) {
            $tracks[] = $this->getRandomTrack();
        }

        $this->add($tracks);
    }

    /**
     * @return null
     */
    public function randomAlbums()
    {
        $albums = array();

        for ($i=0; $i<self::$params['num']; $i++) {
            $track = $this->getRandomTrack();
            if (!preg_match('/^(.+)\//', $track, $matches)) {
                self::out("Couldn't strip dir off of file: $track\n");
                $i--; continue;
            }

            $albums[] = $matches[1];
        }

        $this->add($albums);
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
            self::out("The current track doesn't belong to an album!",
                array('fatal' => true));
        }

        $this->add(array($matches[1]));
    }

    /**
     * Execute a raw command with mpc
     *
     * @return null
     */
    public function raw()
    {
        $this->mpc(self::$params['rawCmd'], false, true);
    }

    /**
     * Returns the full path to a random track
     *
     * This "new" version works by loading a list of all albums, picking one,
     * then choosing a track at random from that.
     *
     * @return string
     */
    public function getRandomTrack()
    {
        $albums = self::$albumCache ?: (self::$albumCache = $this->mpc('list album'));
        $album  = null;

        if (!$albums) {
            self::out('Failed to find any albums via "mpc list album" ?',
                array('fatal' => true));
        }

        do {
            do {
                $album = $albums[rand(0, count($albums) - 1)];
            } while (!$album);

            $files = $this->mpc('find album "' . self::quotefix($album) . '"');
            $file  = null;
        } while (!$files);

        do {
            $file = $files[rand(0, count($files) - 1)];
        } while (!$file);

        return $file;
    }

    /**
     * Returns the full path to a random track
     *
     * This "old" version works by recursing the tree. It's not random enough.
     *
     * @return string
     */
    public function getRandomTrackOld()
    {
        $dirs = null;
        $cur  = null;

        while (true) {  // i don't *think* this could be infinite . . . :D
            if ($dirs) {
                $cur = $dirs[rand(0, count($dirs) - 1)];
            } else {
                // if we have cur, then we this isn't our first iteration.
                if ($cur) self::out("Hit a dead end: $cur", array('debug' => true));

                if ($dirs = $this->lsStartDir()) {
                    $cur = $dirs[rand(0, count($dirs) - 1)];
                } else continue;
            }

            if (self::hasInterestingExtension($cur)) {
                return $cur;
            }

            $dirs = $this->lsDir($cur);
        }
    }

    /**
     * List the contents of the starting dir in our spider into the collection.
     *
     * @return array
     */
    protected function lsStartDir()
    {
        $dirs = array();

        if (self::$params['btRandom']) {

            $tl = self::$params['bt']
                ? self::getToplevel(self::$params['bt'])
                : self::$toplevelDirs[rand(0, count(self::$toplevelDirs) - 1)];

            if (!$dirs = $this->lsDir($tl, true)) {
                // ok, some toplevel is empty or something.
                // no more by-toplevel randomness anymore.
                self::out("Toplevel '$tl' is empty or missing. Toplevel dirs "
                    . 'are disabled.', array('debug' => true));
                self::$params['btRandom'] = false;
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
     * @param boolean $failok
     * @return array
     */
    protected function lsDir($dir, $failok = false)
    {
        if ($dir == '/') {
            if (!self::$rootCache) {
                self::$rootCache = $this->mpc('ls /');
            }
            return self::$rootCache;
        }

        return $this->mpc('ls "' . self::quotefix($dir) . '"',
            false, false, array('failok' => $failok));
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
            self::out("{$mpc[0]}\n{$mpc[1]}");
        } else {
            self::out(implode("\n", $mpc));
        }
    }

    /**
     * Add items as subjects to be operated on via act()
     *
     * @param array $items
     * @param array $o
     * @return null
     */
    protected function add(array $items, array $o = array())
    {
        if (self::$params['choose']) {
            foreach ($items as &$i) {
                if (is_array($i)) {
                    $i['disp'] = isset($i['disp']) ? $i['disp']
                               : self::colorify($i['name']);
                } else {
                    $i = array('name' => $i, 'disp' => self::colorify($i));
                }
            }
            $items = $this->getSelectionFrom($items, true);
        }

        // Normalize for our subject array
        foreach ($items as &$i) {
            if (is_array($i)) {
                $i = $i['name'];
            }
        }

        self::$subject = array_merge(self::$subject, $items);
    }

    /**
     * Take the chosen action on a given item with an mpd-relative path !
     *
     * If an array is passed in, I'll assume each element has a 'name' element
     *
     * @return null
     */
    protected function act()
    {
        if (self::$params['debug']) print_r(self::$subject);

        if (!self::$subject) {
            self::out('No items found.', array('fatal' => true));
        }

        $c = count(self::$subject);
        for ($i=0; $i<$c; $i++) {

            $x = self::$subject[$i];

            if (self::$params['action'] == 'exe' && self::$params['exe']) {
                $cmd = str_replace('X',
                    '"' . self::quotefix(self::$params['mpdRoot'] . '/'
                    . $x) . '"', self::$params['exe']);
                $this->cmd($cmd, true, true);
            } else if (self::$params['action'] == 'mpc') {
                $echo = !self::$params['quiet'];
                if ($i == 0 && !self::$params['append']) $this->mpc('clear', $echo);
                $this->mpc('add "' . self::quotefix($x) . '"', $echo);
                if ($i == 0 && !self::$params['append']) $this->mpc('play', $echo);
            } else if (self::$params['action'] == 'deadbeef') {
                $this->cmd('deadbeef "' . self::quotefix($x) . '"',
                    !self::$params['quiet']);
            } else if (self::$params['action'] == 'list') {
                // make a colorized display version if option is enabled
                if (self::$params['simpleOut']) {
                    echo $x . "\n";
                } else {
                    echo  self::getnum($i+1, $c)
                        . self::colorify($x)
                        . "\n";
                }
            } else {
                self::out("Action is what ({$this->action}) ?", array('fatal' => true));
            }
        }

        if (self::$params['action'] == 'mpc' && self::$params['quiet']) {
            $this->echoStatus();
        }
    }

    /**
     * Reload the latestRoot location... wait for it.
     *
     * @return null
     */
    protected function refreshMpd()
    {
        $it = str_replace(self::$params['mpdRoot'] . '/', '',
            self::$params['latestRoot']);
        $g = $this->mpc('update "' . self::quotefix($it) . '"', true);
        echo "Waiting for DB to refresh..";
        do {
            echo '.';
            sleep(1);
            $lines = $this->mpc();
            $found = false;
            foreach ($lines as $l) {
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
     * @param array $o
     * @return array
     */
    public function mpc($cmd = null, $echoCmd = false, $echoResult = false,
        array $o = array())
    {
        $o = array_merge(array(
            'failok' => false,
        ), $o);

        $retval = null;
        $cmd    = self::$params['mpcCmd'] .  ' ' . $cmd;
        $out    = $this->cmd($cmd, $echoCmd, $echoResult, $retval);

        if (isset($out[0]) && $out[0] == 'error: Connection refused') {
            self::out($out[0], array('fatal' => true));
        }

        $out = array_filter($out,
            function ($i) { return substr($i, 0, 7) != 'error: '; });

        if ($retval != 0 && !$o['failok']) {
            self::out('mpc fail.', array('fatal' => true));
        }

        return $out;
    }

    /**
     * Run a command. Return the lines of returned output in an array.
     *
     * @param string $cmd
     * @param boolean $echoCmd
     * @param boolean $echoResult
     * @return array
     */
    protected function cmd($cmd, $echoCmd, $echoResult, &$retval = null)
    {
        $list = array();

        if ($echoCmd || self::$params['debug']) {
            self::out(' -> ' . $cmd);
        }

        exec($cmd . ' 2>&1', $list, $retval);

        if ($list && ($echoResult || self::$params['debug'])) {
            self::out(implode("\n", $list));
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
        $o = array_merge(array(
            'debug' => false,
            'fatal' => false,
            'warn'  => false,
            'color' => null,
        ), $o);

        if ($o['fatal'] || $o['warn']) $o['color'] = 'red';

        if ($o['debug'] && !self::$params['debug']) return;

        echo $o['color'] ? self::col($msg, $o['color']) : $msg;
        echo "\n";

        if ($o['fatal']) die();
    }

    /**
     * Add some color to some output
     *
     * @param string $txt
     * @param string $color
     */
    protected static function col($txt, $color)
    {
        if (!self::$params['colors']) {
            return $txt;
        }

        $_colors = array(
            'gray'        => "[1;30m",
            'light red'   => "[1;31m",
            'light green' => "[1;32m",
            'yellow'      => "[1;33m",
            'light blue'  => "[1;34m",
            'magenta'     => "[1;35m",
            'light cyan'  => "[1;36m",
            'white'       => "[1;37m",
            'normal'      => "[0m",
            'black'       => "[0;30m",
            'red'         => "[0;31m",
            'green'       => "[0;32m",
            'brown'       => "[0;33m",
            'blue'        => "[0;34m",
            'cyan'        => "[0;36m",
            'bold'        => "[1m",
            'underscore'  => "[4m",
            'reverse'     => "[7m",
        );

        return chr(27) . $_colors[$color] . $txt . chr(27) . '[0m';
    }

    /**
     * Colorify a single result or array of them
     *
     * @param string|array $str
     * @param array $o
     * @return string|array
     */
    protected static function colorify($in, array $o = array())
    {
        if (!self::$params['colors']) {
            return $in;
        }

        $arr   = $in;
        $isArr = is_array($in);

        if (!$isArr) $arr = array($in);

        foreach ($arr as &$e) {
            $p1 = ''; $p2 = $e;
            if (preg_match('/^(.+\/)(.+)$/', $e, $matches)) {
                $path = $matches[1];
                $c    = substr_count($path, '/') % 2 ? 'cyan' : 'normal';
                while (($pos = strpos($path, '/')) !== false && $p = substr($path, 0, $pos + 1)) {
                    $p1   .= self::col($p, $c);
                    $c     = $c == 'cyan' ? 'normal' : 'cyan';
                    $path  = substr($path, strlen($p));
                }
                $p2 = $matches[2];
            }
            $regex = '/^(.+?)((?:-| - |\.)?(?:[\(\[].+|flac|ogg|mp3|v0|\d+cd|web|20\d\d+|19\d\d).*)/i';
            if (preg_match($regex, $p2, $matches)) {
                $p2 = $matches[1] . self::col($matches[2], 'brown');
            }
            // $disp = str_replace(array('EP'), array(self::col('EP', 'red')), $p1 . $p2);
            $e = '';

            if (isset($o['mtime'])) {
                $e .= self::col(date('m-d', $o['mtime']) . '. ', 'cyan');
            }

            $e .= $p1 . $p2;
        }

        return $isArr ? $arr : $arr[0];
    }

    /**
     * Get the nicely formatted number for our listing
     *
     * @param integer $num
     * @param integer $total (count of the array being iterated)
     */
    protected function getnum($num, $total)
    {
        $nums = strlen((string)$total) + 1;
        return self::col(sprintf("%{$nums}d. ", $num), 'green');
    }

    /**
     * Returns true if the provided file has a file extension we might like to 
     * add to a playlist
     *
     * @param string $name
     * @return boolean
     */
    protected static function hasInterestingExtension($name)
    {
        return preg_match(self::$params['extRegex'], $name);
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

        return self::getSelectionFrom(self::$toplevelMap, false, true);
    }

    /**
     * Allow the user to select one or more items from a list
     *
     * @param array $options
     * @param boolean $multi
     * @param boolean $returnKeys
     * @return array|string
     */
    protected static function getSelectionFrom(array $options,
        $multi = false, $returnKeys = false)
    {
        // is the array associative?
        $isAssoc = array_keys($options) !== range(0, count($options) - 1);

        echo "\n";
        $c = count($options);
        foreach ($options as $k => $v) {
            if (is_array($v)) $v = isset($v['disp']) ? $v['disp'] : $v['name'];
            if ($isAssoc) {
                echo self::col("    $k.", 'green') . " $v\n";
            } else {
                echo self::getnum($k+1, $c) . "$v\n";
            }
        }
        echo "\n";

        $stdin = fopen('php://stdin', 'r');
        $ret   = array();
        do {
            echo  self::col('>', 'gray')
                . self::col('>', 'green')
                . self::col('> ', 'light green');
            $choice = trim(fgets($stdin));
            $retry  = false;
            if (!$choice) {
                if (self::$subject) {
                    break;
                } else {
                    self::out('Fine.'); die();
                }
            } else if ($multi) {
                $ret = array();
                foreach (preg_split('/[, ]+/', $choice) as $bit) {
                    if ($bit == 'a') {               // a is an append shortcut
                        self::$params['append'] = true;
                    } else if ($bit == 'm') {        // user wants another list
                        self::$more = true;
                    } else if ($isAssoc) {           // associative arrays
                        if (!array_key_exists($bit, $options)) {
                            self::out("Invalid input: $bit", array('warn' => true));
                            $retry = true;
                        } else $ret[] = $returnKeys ? $bit : $options[$bit];
                    } else if (in_array($bit, array('?', 'h'))) {
                        echo "\nSeparate parameters with spaces or commas.\n"
                            . "\t1   - Select option #1\n"
                            . "\t1-5 - Select options #1 through #5\n"
                            . "\tA   - Select all listed options\n"
                            . "\ta   - Enable append mode (same as -a parameter)\n"
                            . "\tm   - List another set of results after this one\n"
                            . "\th   - this help :)\n\n";
                        $retry = true;
                    } else if (ctype_digit($bit)) {  // numeric-indexed array
                        $b = $bit - 1;
                        if (!array_key_exists($b, $options)) {
                            self::out("Invalid input: $bit", array('warn' => true));
                            $retry = true;
                        } else $ret[] = $returnKeys ? $b : $options[$b];
                    } else if ((preg_match('/^(\d+)-(\d+)$/', $bit, $m)  // ranges
                      && $m[1] > 0 && $m[1] <= count($options)
                      && $m[2] > 0 && $m[2] <= count($options)
                      && $m[1] <= $m[2])
                        || ($bit == 'A' && ($m[1] = 1) && ($m[2] = count($options)))
                    ) {
                        for ($i=$m[1]; $i<=$m[2]; $i++) {
                            $ret[] = $returnKeys ? ($i-1) : $options[$i-1];
                        }
                    } else {
                        self::out("Invalid input: $bit", array('warn' => true));
                        $retry = true;
                    }
                }
            } else {
                $c = $isAssoc ? $choice : $choice-1;
                if (!array_key_exists($c, $options)) {
                    self::out("Invalid input: $choice", array('warn' => true));
                    $retry = true;
                } else $ret = $returnKeys ? $c : $options[$c];
            }
        } while ($retry);
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
        $dirs = $this->recurse(self::$params['latestRoot']);

        if (!$dirs) self::out("Couldn't find anything in latestRoot: "
            . self::$params['latestRoot'], array('fatal' => true));

        // Sort them by mod time
        usort($dirs, function($a, $b) {
            return $a['mtime'] < $b['mtime'];
        });

        $n = self::$params['num'] < count($dirs) ? self::$params['num'] : count($dirs);
        $target = array_slice($dirs, 0, $n);

        foreach ($target as &$t) {
            $t['disp'] = str_replace(self::$params['latestRoot'] . '/', '', $t['name']);
            if (!self::$params['simpleOut']) {
                $t['disp'] = self::colorify($t['disp'], array('mtime' => $t['mtime']));
            }
            $t['name'] = str_replace(self::$params['mpdRoot'] . '/', '', $t['name']);
        }

        $this->add($target);
    }

    /**
     * Gather a list of targets via mpc search
     *
     * @return null
     */
    public function search()
    {
        if (!self::$params['search']) {
            self::out('Please provide a search term.', array('fatal' => true));
        }

        if (self::$params['searchType'] == 'title') {
            $list = $this->mpc('search title "'
                  . self::quotefix(self::$params['search']) . '"');
        } else {
            $list = array();
            $all = $this->mpc('search ' . self::$params['searchType']
                  . ' "' . self::quotefix(self::$params['search']) . '"');
            foreach ($all as $i) {
                if (preg_match('/^(.+)\//', $i, $matches)
                  && !in_array($matches[1], $list)
                ) {
                    $list[] = $matches[1];
                }
            }
        }

        if (!$list) self::out('No results :(', array('fatal' => true));

        $this->add($list);
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
        if (is_null($d)) $d = self::$params['deep'];

        $results = array();  // each is: array('name' => name, 'mtime' => mtime);
        // $glob = glob("$dir/*", GLOB_ONLYDIR);
        $globTarget = "$dir/*";
        if (self::$params['debug']) self::out("globbing: $globTarget");
        $glob = glob($globTarget);
        if (self::$params['debug']) self::out(print_r($glob,true));
        if (!$glob) return array();
        foreach ($glob as $t) {

            // if it's the top level of the search, make sure we skip any results in 
            // the exclude array.
            if ($d == self::$params['deep']) {
                preg_match('/([^\/]+)$/', $t, $matches);
                if (in_array($matches[1], self::$params['exclude'])) {
                    continue;
                }
            }

            $isfile = is_file($t);

            if ($d > 1 && !$isfile) {
                $results = array_merge($results, $this->recurse($t, $d - 1));
            } else if ($d > 1 && $isfile) {
                // a file found, but we're not deep enough yet; ignore.
            } else if ($isfile && !self::hasInterestingExtension($t)) {
                // a file found, but doesn't have an extension we want
            } else {
                $s = stat($t);
                $results[] = array(
                    'name'  => $t,
                    'mtime' => $s['mtime']
                );
            }
        }

        return $results;
    }
}
