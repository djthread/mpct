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

        $this->host = isset($_SESSION['host']) ? $_SESSION['host'] : $this->hosts[0];

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
        $m = $_POST['m'];

        if (!isset($m['host']) || !in_array($m['host'], $hosts)) {
            $this->halt('Error: invalid host.');
        } else if (!isset($m['action'])) {
            $this->halt('Error: invalid action.');
        }
        $this->host = $m['host'];

        switch ($m['action']) {
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

        if (in_array($m['action'], array('randomTracks', 'randomAlbums'))) {
            if (!isset($m['count']) || !ctype_digit($m['count']) || $m == 0 || $m > 99) {
                $count = 1;
            } else $count = $m['count'];
            $params .= " -c $count";
        }

        if (isset($m['BT']) && $m['BT'] != '00' && preg_match('/^[a-z0-9]{2}$/', $m['BT'])) {
            $params .= ' -bt ' . $m['BT'];
        }

        if (isset($m['append']) && $m['append']) {
            $params .= ' -a';
        }

        // save it for later !
        $_SESSION['m'] = $m;

        $dispcmd = preg_replace('/^.*\//', '.../', $mpct) . " $params";
        echo "\$ $dispcmd\n";

        $cmd = "$mpct $params";
        echo `$cmd`;
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
