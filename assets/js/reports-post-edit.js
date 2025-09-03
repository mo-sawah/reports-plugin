jQuery(document).ready(function($) {
    let mediaUploader;

    $('#rp_upload_file_button').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Report File',
            button: {
                text: 'Choose File'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#rp_download_link').val(attachment.url);
            $('#rp_remove_file_button').show();
        });

        mediaUploader.open();
    });

    $('#rp_remove_file_button').on('click', function(e) {
        e.preventDefault();
        $('#rp_download_link').val('');
        $(this).hide();
    });
});
