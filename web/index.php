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
        $params = "--host {$matches[1]} --port {$matches[2]} $params";
    } else {
        $params = "--host {$m['host']} $params";
    }

    // We'll just add the count parameter... even if it's irrelevant (like -ta)
    if (!isset($m['count']) || !ctype_digit($m['count']) || $m == 0 || $m > 99) {
        $count = 1;
    } else $count = $m['count'];
    $params .= ' -c ' . $m['count'];

    if (isset($m['BT']) && preg_match('/^[a-z]{2}$/', $m['BT'])) {
        $params .= ' --by-toplevel ' . $m['BT'];
    }

    if (isset($m['append']) && $m['append']) {
        $params .= ' --append';
    }

    // save it for later !
    $_SESSION['m'] = $m;

    $cmd = "$mpct $params";
    echo "\$ $cmd\n";
    echo `$cmd`;
    die();
}

$m = isset($_SESSION['m']) ? $_SESSION['m'] : array(
    'host'   => $hosts[0],
    'action' => 'randomTracks',
    'useBT'  => false,
    'BT'     => null,
    'count'  => 5,
    'append' => false
);

?>
<html>
<head>
<title>mpct</title>
<link rel="stylesheet" href="style.css">
<script type="text/javascript" src="http://code.jquery.com/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="http://code.jquery.com/mobile/1.0/jquery.mobile-1.0.min.js"></script>
<script type="text/javascript" src="mpct.js"></script>
</head>
<body>
<form id="m_form" method="post">
<div class="group">
    <div class="field">
    <?php if (count($hosts) > 1) { ?>
    <select id="m_host" name="m[host]">
    <?php
    foreach ($hosts as $host) {
        echo "\t<option "
            . ($m['host'] == $host ? ' selected' : '')
            .">$host</option>\n";
    }
    ?>
    </select>
    <?php } else { ?>
    <label><?php echo $hosts[0]; ?></label>
    <input type="hidden" name="m[host]" value="<?= $hosts[0] ?>">
    <?php } ?>
    </div>
</div>
<div class="group">
    <div class="field">
    <input type="radio" id="m_action_randomTracks" name="m[action]" value="randomTracks"<?=
        $m['action'] == 'randomTracks' ? ' checked' : '' ?>>
    <label for="m_action_randomTracks">Random Tracks</label>
    </div>
    <div class="field">
    <input type="radio" id="m_action_randomAlbums" name="m[action]" value="randomAlbums"<?=
        $m['action'] == 'randomAlbums' ? ' checked' : '' ?>>
    <label for="m_action_randomAlbums">Random Albums</label>
    </div>
    <div class="field">
    <input type="radio" id="m_action_thisAlbum" name="m[action]" value="thisAlbum"<?=
        $m['action'] == 'thisAlbum' ? ' checked' : '' ?>>
    <label for="m_action_thisAlbum">This Album</label>
    </div>
</div>
<div class="group">
    <div class="field">
    <input type="checkbox" id="m_useBT" name="m[useBT]"<?php
        echo $m['useBT'] ? ' checked' : ''; ?>>
    <select id="m_BT" name="m[BT]">
    <?php
        $file = `$mpct --get-toplevels`;
        foreach (split("\n", trim(`$mpct --get-toplevels`)) as $line) {
            if (!preg_match('/^([a-z]{2}) (.+)$/', $line, $matches)) {
                continue;
            }
            echo '<option value="' . $matches[1] . '"'
                . ($m['BT'] == $matches[1] ? ' selected' : '')
                . '>' . htmlspecialchars($matches[2])
                . '</option>';
        }
    ?>
    </select>
    </div>
</div>
<div class="group">
    <div class="field">
    <input type="text" id="m_count" name="m[count]" size="3" maxlength="2" value="<?= $m['count'] ?>">
    <label for="m_count">Count</label>
    </div>
</div>
<div class="group">
    <div class="field">
    <input type="checkbox" id="m_append" name="m[append]"<?= $m['append'] ? ' checked' : '' ?>>
    <label for="m_append">Append</label>
    </div>
</div>
<div class="group">
    <div class="field">
    <input type="submit" id="m_submit" value="  Go  ">
    <div id="message"></div>
    </div>
</div>
<?php

?>
</body>
</html>
