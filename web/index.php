<?php
/**
 * Web-based frontend for mpct.
 *
 * @author thread <thethread@gmail.com>
 * @website http://www.threadbox.net/
 */

// BEGIN CONFIG  ---------------------------------

// Full path location of the mpct script
$mpct  = '/home/thread/apps/mpct/mpct.php';

// Array of servers to allow switching between
// Note: adding ":6601" etc for a non-standard port is allowed.
$hosts = array(
    'therver',
    'thair'
);

// -----------------------------------------------


session_start();

$actions = array('randomTracks', 'randomAlbums', 'thisAlbum');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!array_key_exists('m', $_POST) || !is_array($_POST['m'])) {
        die('Error: m is missing.');
    }
    $m = $_POST['m'];

    if (!isset($m['host']) || !in_array($m['host'], $hosts)) {
        die('Error: invalid host.');
    } else if (!isset($m['action'])) {
        die('Error: invalid action.');
    }

    switch ($m['action']) {
    case 'randomTracks':
        $params = '--random-tracks';
        break;
    case 'randomAlbums':
        $params = '--random-album';
        break;
    case 'thisAlbum':
        $params = '--this-album';
        break;
    default:
        die('Error: invalid action.');
    }

    if (preg_match('/^(.+):(\d+)$/', $m['host'], $matches)) {
        $params = "-h {$matches[1]} -p {$matches[2]} $params";
    } else {
        $params = "-h {$m['host']} $params";
    }

    if ($m['action'] != 'thisAlbum') {
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
    die();
}

$m = isset($_SESSION['m']) ? $_SESSION['m'] : array(
    'host'   => $hosts[0],
    'action' => 'randomTracks',
    'BT'     => null,
    'count'  => 5,
    'append' => false
);

?>
<html>
<head>
<title>mpct</title>
<meta name="viewport" content="width=device-width, initial-scale=1"> 
<link rel="stylesheet" href="http://code.jquery.com/mobile/1.0/jquery.mobile-1.0.min.css" />
<script type="text/javascript" src="http://code.jquery.com/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="http://code.jquery.com/mobile/1.0/jquery.mobile-1.0.min.js"></script>
<script type="text/javascript" src="mpct.js"></script>
<style type="text/css">
div#message { font-size: 70%; }
</style>
</head>
<body>
<div data-role="page" data-theme="a" data-title="mpct">

<div data-role="header">
    <h1>mpct</h1>
</div>

<div data-role="content">
<form id="m_form" method="post">
    <label for="m_host" style="display:none">Host</label>
    <select id="m_host" name="m[host]">
    <?php
    foreach ($hosts as $host) {
        echo "\t<option"
            . ($m['host'] == $host ? ' selected' : '')
            .">$host</option>\n";
    }
    ?>
    </select>

    <fieldset data-role="controlgroup">
    <input type="radio" id="m_action_randomTracks" name="m[action]" value="randomTracks"<?=
        $m['action'] == 'randomTracks' ? ' checked' : '' ?>>
    <label for="m_action_randomTracks">Random Tracks</label>

    <input type="radio" id="m_action_randomAlbums" name="m[action]" value="randomAlbums"<?=
        $m['action'] == 'randomAlbums' ? ' checked' : '' ?>>
    <label for="m_action_randomAlbums">Random Albums</label>

    <input type="radio" id="m_action_thisAlbum" name="m[action]" value="thisAlbum"<?=
        $m['action'] == 'thisAlbum' ? ' checked' : '' ?>>
    <label for="m_action_thisAlbum">This Album</label>
    </fieldset>

    <select id="m_BT" name="m[BT]">
    <?php
        $lines = array_merge(
            array('00 All Genres'),
            split("\n", trim(`$mpct --get-toplevels`))
        );
        foreach ($lines as $line) {
            if (!preg_match('/^([a-z0-9]{2}) (.+)$/', $line, $matches)) {
                continue;
            }
            echo '<option value="' . $matches[1] . '"'
                . ($m['BT'] == $matches[1] ? ' selected' : '')
                . '>' . htmlspecialchars($matches[2])
                . '</option>';
        }
    ?>
    </select>

    <label for="m_count" style="display:none">Count</label>
    <input type="range" name="m[count]" id="m_count" value="<?=$m['count'] ?>" min="1" max="20"  />

    <input type="checkbox" id="m_append" name="m[append]"<?= $m['append'] ? ' checked' : '' ?>>
    <label for="m_append">Append</label>

    <input type="submit" id="m_submit" value="  Go  ">
    <div id="message"></div>
</form>
</div>

</div>
</body>
</html>
