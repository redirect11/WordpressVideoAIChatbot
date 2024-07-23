<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://localhost
 * @since      1.0.0
 *
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/admin/partials
 */

// printf(
//     '<div style="%s">%s</div>',
//     sprintf(
//         'background: var(--wp--preset--color--vivid-purple, #9b51e0); color: var(--wp--preset--color--white, #ffffff); padding: var(--wp--preset--spacing--20, 1.5rem); font-size: %s;',
//         esc_attr( $options['size'] )
//     ),
//     esc_html( $options['message'] )
// );

?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->


<div class="wrap">
    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <br/>
    <form action="options.php" method="post">
        <?php 
        settings_fields('video_ai_chatbot_group'); // Output nonce, action, and option_page fields for a settings page.
        do_settings_sections('chatbot-settings'); // Stampa tutte le sezioni di una determinata pagina di impostazioni.
        echo '</div>'; //TODO check why this is needed  
        echo '</div>'; //TODO check why this is needed  
        echo '</div>'; //TODO check why this is needed  
        submit_button(); // Stampa il pulsante di invio per il form.
        ?>
    </form>
</div>