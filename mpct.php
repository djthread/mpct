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

/**
 * The only configuration in this script is this array.
 *
 * Top-level directory map (short code => full path)
 *
 * The keys will be the short codes usable with the --by-toplevel/-bt flag. 
 *
 * ALSO: When you do regular random (not by toplevel) these dirs will also 
 * be used for randomness. I've left a few that I am not generally 
 * interested in out of this list. If you want to start at the root and 
 * have random music selected from the entire collection, simply empty this
 * array.
 */
$toplevelMap = array(
    'am' => 'Ambient',
    'ab' => 'Ambient Beats',
    'bl' => 'Bootlegs',
    'bb' => 'Breakbeat',
    'bc' => 'Breakcore, Gabber, and Noise',
    'ch' => 'Chill Out and Dub',
    'cl' => 'Classical',
    'co' => 'Compilations',
    'dj' => 'DJ Beats',
    'db' => 'Drum \'n Bass',
    'dt' => 'Dub Techno',
    'du' => 'Dubstep',
    'el' => 'Electronic and Electro',
    'fo' => 'Folk',
    'go' => 'Goa',
    'ho' => 'House',
    'id' => 'IDM',
    'ja' => 'Jazz',
    'me' => 'Metal',
    'mi' => 'Minimalistic',
    'po' => 'Pop',
    'pr' => 'Post-rock',
    'ra' => 'Rap and Hip Hop',
    're' => 'Reggae and Dub',
    'ro' => 'Rock',
    'sl' => 'Soul',
    'so' => 'Soundtracks',
    'te' => 'Techno',
    'th' => 'Thread',
    'tr' => 'Trance',
    'th' => 'Trip-Hop',
    'we' => 'Weird',
    'wo' => 'World and New Age',
);

MPCWorker::runFromCLIArguments($argv, $toplevelMap);

class MPCWorker
{
    const VERSION = '0.01';

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
     * The parameters, filled from defaults & the command line
     *
     * @var array
     */
    protected $params = array();

    /**
     * @param array $params
     */
    protected function __construct(array $params)
    {
        $this->params = $params;

        $this->params['mpcCmd'] = sprintf('%s -h %s -p %s',
            $this->params['mpc'],
            $this->params['host'],
            $this->params['port']);
    }

    /**
     * Do yo shit (from the command line arguments)
     *
     * @param array $argv
     * @param array $toplevelMap
     * @return null
     */
    public static function runFromCLIArguments($argv, array $toplevelMap = array())
    {
        $func               = null;
        $help               = false;
        self::$toplevelMap  = $toplevelMap;
        self::$toplevelDirs = array_values($toplevelMap);
        self::$btRandom     = (bool)$toplevelMap;

        $params = array(
            'host'       => 'localhost',
            'port'       => '6600',
            'mpc'        => '/usr/bin/mpc',
            'count'      => 1,
            'append'     => false,
            'short'      => null,  // short code for "by toplevel"
            'quiet'      => false,
            'debug'      => false,
        );

        array_shift($argv);  // take off the script name. useless.

        // I don't like this code much at all. It splits up a single arg into 
        // multiple ones so my alfred extension works. (Alfred sends all 
        // parameters, including spaces, as a single argument.)
        $myargs = array();
        foreach ($argv as $av) {
            if ($ss = split(' --', $av)) {
                $myargs[] = $ss[0];
                for ($i=1; $i<count($ss); $i++) {
                    $myargs[] = "--{$ss[$i]}";
                }
            } else if ($ss = split(' -', $av)) {
                $myargs[] = $ss[0];
                for ($i=1; $i<count($ss); $i++) {
                    $myargs[] = "-{$ss[$i]}";
                }
            } else {
                $myargs = $av;
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
            case '--random-album': case '-ra':
                $func = 'randomAlbum';
                break;
            case '--this-album': case '-ta':
                $func = 'playThisAlbum';
                break;
            case '--append': case '-a':
                $params['append'] = true;
                break;
            case '--ten': case '-10':
                $func = 'randomTracks';
                $params['count'] = 10;
                break;
            case '--random-tracks': case '-rt':
                $func = 'randomTracks';
                break;
            case '--count': case '-c':
                if (!($arg = array_shift($myargs))
                  || !ctype_digit($arg)
                ) {
                    die('--count must be followed by a number');
                }
                $params['count'] = $arg;
                break;
            case '--by-toplevel': case '-bt':
                if ($arg = array_shift($myargs)) {
                    if (self::getToplevel($arg)) {
                        $params['short'] = $arg;
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
            case '--debug': case '-d':
                $params['debug'] = true;
                break;
            case '--quiet': case '-q':
                $params['quiet'] = true;
                break;
            case '--help': case '-?':
                $help = true;
                break;
            default:
                die("what?: $arg");
            }
        }

        if ($help || !$func) {
            $version = self::VERSION;
            echo "
DJ Thread's MPC Tool, v$version

   -h,  --host           set the target host (default: localhost)
   -p,  --port           set the target port (default: 6600)
   -rt, --random-tracks  add random tracks to the playlist
   -ra, --random-album   play/add random album
   -ta, --this-album     play/add the album from which the current song is
   -bt, --by-toplevel    ask which toplevel dir to use (a short code CAN follow)
   -10, --ten            play/add 10 random tunes
   -c,  --count          how many tracks to add (default: 10)
   -a,  --append         add tunage, peserving the current playlist
        --mpc            full path to mpc executable
   -d,  --debug          echo debugging information
   -q,  --quiet          sssshhh
   -?,  --help           this.

";
            exit;
        }

        $w = new self($params);
        $w->$func();
    }

    /**
     * Add some number of random tunes to the playlist. Play.
     *
     * @param array $params
     * @return null
     */
    public function randomTracks()
    {
        for ($i=0; $i<$this->params['count']; $i++) {

            $track = $this->getRandomTrack();

            if ($i == 0 && !$this->params['append']) $this->mpc('clear');
            $this->mpc('add "' . str_replace('"', '\\"', $track) . '"');
            if ($i == 0 && !$this->params['append']) $this->mpc('play');
        }

        if (!$this->params['quiet']) $this->echoStatus();
    }

    /**
     * @return null
     */
    public function randomAlbum()
    {
        for ($i=0; $i<$this->params['count']; $i++) {
            $track = $this->getRandomTrack();
            $album = $this->mpc('listall -f %album% "' . str_replace('"', '\\"', $track) . '"');

            if (!$album || !$album[0]) {
                if ($this->params['debug']) echo "Found track with no album: $track\n";
                $i--;
                continue;
            }

            if ($i == 0 && !$this->params['append']) $this->mpc('clear');
            $this->addAlbum($album[0]);
            if ($i == 0 && !$this->params['append']) $this->mpc('play 1');
        }

        if (!$this->params['quiet']) $this->echoStatus();
    }

    /**
     * Add to the playlist and play the album that the current song belongs to.
     *
     * @return null
     */
    public function playThisAlbum()
    {
        $status = $this->mpc('-f %album% current');

        if (!$status || !$status[0]) {
            die("The current track doesn't belong to an album!\n");
        }

        if (!$this->params['append']) $this->mpc('clear');
        $this->addAlbum($status[0]);
        if (!$this->params['append']) $this->mpc('play 1');
        if (!$this->params['quiet']) $this->echoStatus();
    }

    /**
     * Add an album by its name to the playlist
     *
     * @param string $album
     * @return null
     */
    public function addAlbum($album)
    {
        $album = $this->quotefix($album);
        $this->cmd(
            "{$this->params['mpcCmd']} find album \"$album\" | {$this->params['mpcCmd']} add");
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
                if ($this->params['debug']) echo "Hit a dead end: $cur\n";
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
     * If by-toplevel is enabled, we will TODO
     *
     * @return array
     */
    protected function lsStartDir()
    {
        $dirs = array();

        if (self::$btRandom) {

            $tl = $this->params['short']
                ? self::getToplevel($this->params['short'])
                : self::$toplevelDirs[rand(0, count(self::$toplevelDirs) - 1)];

            if (!$dirs = $this->lsDir($tl)) {
                // ok, some toplevel is empty or something. no more by-toplevel randomness anymore.
                if ($this->params['debug']) echo "Toplevel '$tl' is empty or missing. Toplevel dirs are disabled.\n";
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

        return $this->mpc('ls "' . $this->quotefix($dir) . '"');
    }

    /**
     * @param string $str
     * @return string
     */
    protected function quotefix($str)
    {
        return str_replace('"', '\\"', $str);
    }

    /**
     * Echo the MPC info that I want
     *
     * @return null
     */
    protected function echoStatus()
    {
        $mpc = $this->mpc();
        echo "{$mpc[0]}\n{$mpc[1]}\n";
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
        $result = $this->cmd($cmd, $echoCmd || $this->params['debug']);
        if ($echoResult) {
            echo implode("\n", $result) . "\n";
        }
        return $result;
    }

    /**
     * Run a command. Return the lines of returned output in an array.
     *
     * @param string $cmd
     * @return array
     */
    protected function cmd($cmd, $debug = false)
    {
        $list = array();
        if ($debug) echo "[$cmd]\n";
        exec($cmd, $list);
        return $list;
    }

    /**
     * @param string $short
     * @return string (the full toplevel dir name)
     */
    protected static function getToplevel($short = null)
    {
        if ($short) {
            if (array_key_exists($short, self::$toplevelMap)) {
                return self::$toplevelMap[$short];
            } else {
                return false;
            }
        }

        echo "\n";
        foreach (self::$toplevelMap as $short => $full) {
            echo "    $short  $full\n";
        }
        echo "\n";

        $stdin = fopen('php://stdin', 'r');
        do {
            echo "  > ";
            $choice = trim(fgets($stdin));
        } while (!array_key_exists($choice, self::$toplevelMap));
        fclose($stdin);

        return self::$toplevelMap[$choice];
    }
}
