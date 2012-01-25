<?php
/**
 * Web-based frontend for mpct.
 *
 * @author thread <thethread@gmail.com>
 * @website http://www.threadbox.net/
 */

include('config.php');
include('Mui.php');

$mui = new Mui(array(
    'mpct'  => $mpct,
    'hosts' => $hosts,
));

$mui->maybeHandlePost();

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
body { text-align: center }
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
        <?php echo $mui->getHostOptionTags(); ?>
    </select>

    <fieldset data-role="controlgroup">
    <input type="radio" id="m_action_randomTracks" name="m[action]" value="randomTracks"<?php
        echo $m['action'] == 'randomTracks' ? ' checked' : '' ?>>
    <label for="m_action_randomTracks">Random Tracks</label>

    <input type="radio" id="m_action_randomAlbums" name="m[action]" value="randomAlbums"<?php
        echo $m['action'] == 'randomAlbums' ? ' checked' : '' ?>>
    <label for="m_action_randomAlbums">Random Albums</label>

    <input type="radio" id="m_action_thisAlbum" name="m[action]" value="thisAlbum"<?php
        echo $m['action'] == 'thisAlbum' ? ' checked' : '' ?>>
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
    <input type="range" name="m[count]" id="m_count" value="<?php echo $m['count'] ?>" min="1" max="20"  />

    <input type="checkbox" id="m_append" name="m[append]"<?php echo $m['append'] ? ' checked' : '' ?>>
    <label for="m_append">Append</label>

    <input type="submit" id="m_submit" value="  Go  ">
    <div id="message"></div>
</form>

<div data-role="controlgroup" data-type="horizontal">
    <a id="a_prev" data-role="button" data-icon="arrow-l">Prev</a>
    <a id="a_toggle" data-role="button">Toggle</a>
    <a id="a_next" data-role="button" data-icon="arrow-r">Next</a>
</div>


<ul data-role="listview" data-inset="true">
    <li role="header" data-role="list-divider">Playlist</li>
<?php
    foreach (split("\n", trim(`$mpct --raw playlist`)) as $line) {
        echo '<li>' . htmlspecialchars($line) . "</li>\n";
    }
?>
</ul>

</div>

</div>
</body>
</html>
<?php
function mpct($params) {
    global $host, $mpct;

    if (preg_match('/^(.+):(\d+)$/', $host, $matches)) {
        $hostparam = "-h {$matches[1]} -p {$matches[2]} $params";
    } else {
        $hostparam = "-h $host $params";
    }

    return `$mpct $hostparam $params`;
}
