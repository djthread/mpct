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
        var b = $('#m_useBT');
        if ($('#m_action_randomTracks').attr('checked')
         || $('#m_action_randomAlbums').attr('checked')
        ) {
                        b.prop('disabled', false);
            $('#m_count').prop('disabled', false);
        } else {
                        b.prop('disabled', true);
            $('#m_count').prop('disabled', true);
        }
        if (b.attr('disabled') || !b.attr('checked')) {
            $('#m_BT').prop('disabled', true);
        } else {
            $('#m_BT').prop('disabled', false);
        }
    };
    $('#m_action_randomTracks').click(twiddle);
    $('#m_action_randomAlbums').click(twiddle);
    $('#m_action_thisAlbum').click(twiddle);
    $('#m_useBT').click(twiddle);
    twiddle();
});
