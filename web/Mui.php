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
     * The available actions
     *
     * @var array
     */
    protected $actions = array(
        'randomTracks',
        'randomAlbums',
        'thisAlbum',
        'prev',
        'toggle',
        'next'
    );

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
            'host'   => $hosts[0],
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
            $params = '--random-album'; break;
        case 'thisAlbum':
            $params = '--this-album'; break;
        case 'prev':
            $params = '--raw prev'; break;
        case 'toggle':
            $params = '--raw toggle'; break;
        case 'next':
            $params = '--raw next'; break;
        default:
            $this->halt('Error: invalid action.');
        }

        if (in_array($this->m['action'], array('randomTracks', 'randomAlbums'))) {
            if (!isset($this->m['count']) || !ctype_digit($this->m['count']) || $this->m == 0 || $this->m > 99) {
                $count = 1;
            } else $count = $this->m['count'];
            $params .= " -c $count";
        }

        if (isset($this->m['BT']) && $this->m['BT'] != '00' && preg_match('/^[a-z0-9]{2}$/', $this->m['BT'])) {
            $params .= ' -bt ' . $this->m['BT'];
        }

        if (isset($this->m['append']) && $this->m['append']) {
            $params .= ' -a';
        }

        // save it for later !
        $_SESSION['m'] = $this->m;

        echo $this->fancy($params);

        $this->halt();
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
                  . ($this->host == $host ? ' selected' : '')
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
            split("\n", $this->mpct('--get-toplevels'))
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
        foreach (split("\n", $this->mpct('--raw playlist')) as $line) {
            $ret .= '<li>' . htmlspecialchars($line) . "</li>\n";
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

        $cmd = "{$this->mpct} $hostparam $params";
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
     * @param string $msg
     * @return null
     */
    protected function halt($msg)
    {
        die($msg);
    }
}
