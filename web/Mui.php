<?php
/**
 * mpct UI worker class
 *
 * @author thread <thethread@gmail.com>
 * @website http://www.threadbox.net/
 */

class Mui
{
    /**
     * The full path to mpct.php
     *
     * @var string
     */
    protected $mpct;

    /**
     * The array of available mpd-running host targets
     *
     * @var array
     */
    protected $hosts;

    /**
     * The host being targeted
     *
     * @var string
     */
    protected $host;

    /**
     * The action being made when posting
     *
     * @var string
     */
    protected $action;

    /**
     * The m array that was last posted.
     *
     * @var array
     */
    protected $m;

    /**
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        if (!isset($config['mpct'])) $this->halt('You must define $mpct in config.php.');
        if (!isset($config['hosts']) || !is_array($config['hosts'])) {
            $this->halt('You must define the $hosts array in config.php.');
        }

        $this->mpct  = $config['mpct'];
        $this->hosts = $config['hosts'];

        session_start();

        $this->m = isset($_SESSION['m']) ? $_SESSION['m'] : array(
            'host'   => $this->hosts[0],
            'action' => 'randomTracks',
            'BT'     => null,
            'count'  => 5,
            'append' => false
        );
    }

    /**
     * If a post was made, handle it.
     *
     * @return null
     */
    public function maybeHandlePost()
    {
        if (!isset($_SERVER['REQUEST_METHOD'])
          || $_SERVER['REQUEST_METHOD'] != 'POST'
        ) {
            return;
        }

        if (!array_key_exists('m', $_POST) || !is_array($_POST['m'])) {
            $this->halt('Error: invalid post.');
        }

        $mOld    = $this->m;
        $this->m = $_POST['m'];

        if (!isset($this->m['host']) || !in_array($this->m['host'], $this->hosts)) {
            $this->halt('Error: invalid host.');
        } else if (!isset($this->m['action'])) {
            $this->halt('Error: invalid action.');
        }

        switch ($this->m['action']) {
        case 'randomTracks':
            $params = '--random-tracks'; break;
        case 'randomAlbums':
            $params = '--random-albums'; break;
        case 'thisAlbum':
            $params = '--this-album'; break;
        case 'prev':
            $params = '--raw prev'; break;
        case 'toggle':
            $params = '--raw toggle'; break;
        case 'next':
            $params = '--raw next'; break;
        case 'pl':
            $params = '--raw play'; break;
        default:
            $this->halt('Error: invalid action.');
        }

        if (in_array($this->m['action'], array('randomTracks', 'randomAlbums'))) {
            $count = 1;
            if (isset($this->m['count'])
              && ctype_digit($this->m['count'])
              && $this->m['count'] > 0
              && $this->m['count'] < 99
            ) {
                $count = $this->m['count'];
            }
            $params .= " -n $count";

            if (isset($this->m['BT']) && $this->m['BT'] != '00' && preg_match('/^[a-z0-9]{2}$/', $this->m['BT'])) {
                $params .= ' -bt ' . $this->m['BT'];
            }
        }

        if (in_array($this->m['action'], array('randomTracks', 'randomAlbums', 'thisAlbum'))) {
            if (isset($this->m['append']) && $this->m['append']) {
                $params .= ' -a';
            }
        }

        if ($this->m['action'] == 'pl') {
            if (!isset($this->m['i']) || !ctype_digit($this->m['i'])) $this->halt('invalid i');
            $params .= ' ' . $this->m['i'];
        }

        // save it for later, but maybe not everything.
        $actionsToPersist = array('randomTracks', 'randomAlbums', 'thisAlbum');
        if (!in_array($this->m['action'], $actionsToPersist)) $this->m['action'] = $mOld['action'];
        if (!$this->m['count'])                               $this->m['count']  = $mOld['count'];

        $_SESSION['m'] = $this->m;

        $this->halt(array(
            'msg' => $this->fancy($params),
            'pl'  => $this->getPlaylistLis(),
        ));
    }

    /**
     * Build and return the host option tags
     *
     * @return string
     */
    public function getHostOptionTags()
    {
        $ret = '';
        foreach ($this->hosts as $host) {
            $ret .= "\t<option"
                  . ($this->m['host'] == $host ? ' selected' : '')
                  .">$host</option>\n";
        }
        return $ret;
    }

    /**
     * Build and return the toplevel option tags
     *
     * @return string
     */
    public function getBTOptionTags()
    {
        $ret = '';
        $lines = array_merge(
            array('00 All Genres'),
            explode("\n", $this->mpct('--get-toplevels'))
        );
        foreach ($lines as $line) {
            if (!preg_match('/^([a-z0-9]{2}) (.+)$/', $line, $matches)) {
                continue;
            }
            $ret .= '<option value="' . $matches[1] . '"'
                . ($m['BT'] == $matches[1] ? ' selected' : '')
                . '>' . htmlspecialchars($matches[2])
                . "</option>\n";
        }
        return $ret;
    }

    /**
     * Build and return the playlist li tags
     *
     * @return string
     */
    public function getPlaylistLis()
    {
        $ret = '';
        $i   = 1;
        foreach (explode("\n", $this->mpct('--raw playlist')) as $line) {
            $ret .= '<li><a class="pli" href="?m[action]=pl&m[i]=' . $i . '">'
                  . htmlspecialchars($line) . "</a></li>\n";
            $i++;
        }
        return $ret;
    }

    /**
     * Get the last posted value for a given key.
     *
     * @param string $key
     * @return mixed
     */
    public function m($key)
    {
        return array_key_exists($key, $this->m) ? $this->m[$key] : null;
    }

    /**
     * Call mpct. We'll figure out the host part...
     *
     * @param string $params
     * @param boolean $fancy
     * @return string
     */
    public function mpct($params, $fancy = false) {

        if (preg_match('/^(.+):(\d+)$/', $this->m['host'], $matches)) {
            $hostparam = "-h {$matches[1]} -p {$matches[2]}";
        } else {
            $hostparam = "-h {$this->m['host']}";
        }

        $cmd = "{$this->mpct} $hostparam -o webui $params";
        $out = trim(`$cmd`);

        return $fancy ? array($cmd, $out) : $out;
    }

    /**
     * Execute mpct with the parameters and return some nice output
     *
     * @param string $params
     * @return string
     */
    public function fancy($params)
    {
        list($cmd, $out) = $this->mpct($params, true);

        return '$ ' . preg_replace('/^.*\//', '.../', $cmd) . "\n"
            . $out;
    }

    /**
     * die
     *
     * @param string|array $msg
     * @return null
     */
    protected function halt($msg)
    {
        // TODO: include playlist
        die(is_array($msg) ? json_encode($msg) : json_encode(array('msg' => $msg)));
    }
}

function pre_r($in) {
    echo '<pre>' . print_r($in, true) . '</pre>';
}
