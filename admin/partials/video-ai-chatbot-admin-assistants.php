<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <br />
    <div id="react-assistants-notices"></div>
    <br />
    <div id="react-assistants-page"></div>
</div>

<script type="text/javascript">
    jQuery('#create-new-assistant').on('click', function() {
        var win = window.open('https://platform.openai.com/assistants', '_blank');
        if (win) {
            win.focus();
        } else {
            alert('Please allow popups for this website');
        };
    });
</script>