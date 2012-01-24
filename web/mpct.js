$(document).ready(function() {
    $('#m_submit').click(function(e) {
        e.preventDefault();
        $.ajax({
            type: 'POST',
            data: $("#m_form").serialize(),
            success: function(data) { $('#message').html('<pre>' + data + '</pre>'); }
        });
    });
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
});
