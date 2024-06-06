<div class="wrap">
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