$(document).ready(function() {
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

    $('#m_action_randomTracks').click(twiddle);
    $('#m_action_randomAlbums').click(twiddle);
    $('#m_action_thisAlbum').click(twiddle);
    twiddle();

    $('#m_submit').click(function(e) {
        e.preventDefault();
        $.ajax({
            type: 'POST',
            data: $("#m_form").serialize(),
            success: function(data) { updateMessage(data); }
        });
    });

    var action, actions = ['prev', 'toggle', 'next'];

    for (action in actions) {
        action = actions[action];
        $('#a_' + action).click(function () {
            var c_action = action;
            return function (e) {
                alert('m[host]=' + $('#m_host').val() +
                        '&m[action]=' + c_action);
                e.preventDefault();
                $.ajax({
                    type: 'POST',
                    data: 'm[host]=' + $('#m_host').val() +
                        '&m[action]=' + c_action,
                    success: function (x) { updateMessage(x); }
                });
            };
        }());
    }
});
