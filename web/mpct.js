// Setup jQuery Mobile
$(document).bind("mobileinit", function(){
    $.extend($.mobile, {
        // This is so my playlist links don't do some crap they're not supposed to.
        linkBindingEnabled: false
    });
});
        
$(document).ready(function() {
    var h = function (str) { return $('<span>').text(str).html(); };

    var updateMessage = function (msg) {
        $('#message').html('<pre>' + msg + '</pre>');
    };

    var twiddle = function(e) {
        if ($('#m_action_randomTracks').attr('checked')
         || $('#m_action_randomAlbums').attr('checked')
        ) {
            $('#m_count').prop('disabled', false);
            $('#m_BT').prop('disabled', false);
        } else {
            $('#m_count').prop('disabled', true);
            $('#m_BT').prop('disabled', true);
        }
    };

    var updateUI = function(json) {
        json   = $.parseJSON(json);
        var pl = $('#pl');

        $('#message').html('<pre>' + h(json.msg) + '</pre>');
        pl.html(
            '<li role="header" data-role="list-divider">Playlist</li>' + json.pl);
        pl.listview('refresh');
        $('a.pli').on('click', pliClick);
    };

    var pliClick = function (e) {
        e.preventDefault();
        $.ajax({
            type: 'POST',
            data: 'm[host]=' + $('#m_host').val() + '&' +
                $(e.target).attr('href').replace(/^\?/, ''),
            success: updateUI
        });
    };

    $('#m_action_randomTracks').click(twiddle);
    $('#m_action_randomAlbums').click(twiddle);
    $('#m_action_thisAlbum').click(twiddle);
    twiddle();

    $('#m_submit').click(function(e) {
        e.preventDefault();
        $.ajax({
            type: 'POST',
            data: $("#m_form").serialize(),
            success: updateUI
        });
    });

    var action, actions = ['prev', 'toggle', 'next'];

    for (action in actions) {
        action = actions[action];
        $('#a_' + action).click(function () {
            var c_action = action;
            return function (e) {
                e.preventDefault();
                $.ajax({
                    type: 'POST',
                    data: 'm[host]=' + $('#m_host').val() +
                        '&m[action]=' + c_action,
                    success: updateUI
                });
            };
        }());
    }

    $('a.pli').on('click', pliClick);
});
