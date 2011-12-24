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
 * @author thread <thethread@gmail.com>
 * @website http://www.threadbox.net/
 */

MPCWorker::runFromCLIArguments($argv);

class MPCWorker
{
    /**
     * Top level dirs to pull from when random tracks/albums are selected 
     * against the full collection. Make this array empty to actually pull from 
     * the full collection
     *
     * @param array
     */
    protected $toplevelWhitelist = array(
        'Ambient', 'Ambient Beats', 'Breakbeat', 'Breakcore, Gabber, and Noise', 'CC', 'Chill Out and Dub',
        'Classical', 'Compilations', 'DJ Beats', 'Drum \'n Bass', 'Dub Techno', 'Dubstep', 'Electronic and Electro',
        'Folk', 'Goa', 'House', 'IDM', 'Jazz', 'Metal', 'Minimalistic', 'Pop', 'Post-rock', 'Rap and Hip Hop',
        'Reggae and Dub', 'Rock', 'Soul', 'Soundtracks', 'Techno', 'Trance', 'Trip-Hop', 'World and New Age',
    );

    /**
     * Top-level directory map (short code => full path)
     *
     * The keys will be the short codes usable with the --by-toplevel/-bt flag 
     * and the ones you will get to pick from if you do not specify one to the 
     * flag.
     */
    protected $toplevelMap = array(
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
        'ms' => 'Misc',
        'nm' => 'Non-music',
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

    protected $params = array();
    protected $albums = array();

    /**
     * Do yo shit (from the command line arguments)
     *
     * @param array $argv
     * @return null
     */
    public static function runFromCLIArguments($argv)
    {
        $func = null;
        $help = false;

        $params = array(
            'host'       => 'localhost',
            'port'       => '6600',
            'mpc'        => '/usr/local/bin/mpc',
            'count'      => 1,
            'append'     => false,
            'byToplevel' => false,
            'short'      => null,  // short code for "by toplevel"
            'quiet'      => false,
            'debug'      => false,
        );

        array_shift($argv);  // take off the script name. useless.

        // my god, this code is gross. it splits up single args into multiple 
        // for my alfred extension.
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
                $params['byToplevel'] = true;
                if ($arg = array_shift($myargs)) {
                    if (self::getToplevel($arg)) {
                        $params['short'] = $arg;
                    } else {
                        array_unshift($myargs, $arg); // put it back
                    }
                }
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
                echo '
   -h,  --hostname       set the target host (default: localhost)
   -p,  --port           set the target port (default: 6600)
   -rt, --random-tracks  add random tracks to the playlist
   -10, --ten            play/add 10 random tunes
   -bt, --by-toplevel    ask which toplevel dir to use (a short code CAN follow)
   -ra, --random-album   play/add random album
   -c,  --count          how many tracks to add (default: 10)
   -ta, --this-album     play/add the album from which the current song is
   -a,  --append         add tunage, peserving the current playlist
        --mpc            full path to mpc executable
   -d,  --debug          echo debugging information
   -q,  --quiet          sssshhh
   -?,  --help           this.

';
            exit;
        }

        $w = new self($params);
        $w->$func();
    }

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
     * Add some number of random tunes to the playlist. Play.
     *
     * @param array $params
     * @return null
     */
    public function randomTracks()
    {
        if ($this->params['byToplevel']) {
            $toplevelDir = self::getToplevel($this->params['short']);
        } else {
            $toplevelDir = '/';
        }

        for ($i=0; $i<$this->params['count']; $i++) {

            $track = $this->getRandomTrack($toplevelDir);

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
        if ($this->params['byToplevel']) {
            $toplevelDir = self::getToplevel($this->params['short']);
        } else {
            $toplevelDir = '/';
        }

        for ($i=0; $i<$this->params['count']; $i++) {
            $track = $this->getRandomTrack($toplevelDir);
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
     * @param string $dir (toplevel to start from)
     * @return null
     */
    public function getRandomTrack($dir)
    {
        // If $dir is /, then we want to pick from the whitelisted toplevels.
        $cur = $dir == '/' ? $this->toplevelWhitelist[rand(0, count($this->toplevelWhitelist) - 1)] : $dir;

        do {
            $dirs = $this->mpc("ls \"$cur\"");
            if (!$dirs) {
                if ($this->params['debug']) echo "Hit a dead end: $cur\n";
                $cur = $dir == '/' ? $this->toplevelWhitelist[rand(0, count($this->toplevelWhitelist) - 1)] : $dir;
                continue;
            }
            $cur = $dirs[rand(0, count($dirs) -  1)];
        } while (!preg_match('/(\.flac|\.mp3|\.ogg)$/i', $cur));

        return $cur;
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
    protected function getToplevel($short = null)
    {
        if ($short) {
            if (array_key_exists($short, $this->toplevelMap)) {
                return $this->toplevelMap[$short];
            } else {
                die("Invalid short code: $short\n");
            }
        }

        echo "\n";
        foreach ($this->toplevelMap as $short => $full) {
            echo "    $short  $full\n";
        }
        echo "\n";

        $stdin = fopen('php://stdin', 'r');
        do {
            echo "  > ";
            $choice = trim(fgets($stdin));
        } while (!array_key_exists($choice, $this->toplevelMap));
        fclose($stdin);

        return $this->toplevelMap[$choice];
    }
}
