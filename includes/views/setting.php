<?php
$thrivedesk_options = thrivedesk_options();
$api_token = $thrivedesk_options['api_token'] ?? '';
?>

<div class="wrap">
    <h1>Thrive Desk Settings</h1>

    <form method="post" action="#">
        <?php wp_nonce_field('thrivedesk_save_settings', 'thrivedesk_save_settings'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="api_token">API Token</label></th>
                    <td><input name="api_token" type="text" id="api_token" value="<?php echo $api_token; ?>" class="regular-text"></td>
                </tr>
            </tbody>
        </table>

        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
    </form>
</div>