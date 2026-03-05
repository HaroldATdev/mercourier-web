<h1><?php echo __('Import/Export Settings', 'wpc-import-export'); ?></h1>
<div class="postbox">
    <div class="inside">
        <form method="POST" action="options.php">
            <?php settings_fields( 'wpcie_registered_settings_group' ); ?>
            <?php do_settings_sections( 'wpcie_registered_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Email Notification upon Import?', 'wpc-import-export' ); ?></th>
                    <td>
                        <input type="checkbox" name="wpcie_email_notification" value="1" <?php echo checked( wpcie_email_notification(), 1 ) ?>>
                        <p class="description"><?php esc_html_e('Note: This will enable email notification upon importing data.', 'wpc-import-export' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Disabled in Frontend Manager Dashboard', 'wpc-import-export' ); ?></th>
                    <td>
                        <input type="checkbox" name="wpcie_disable" value="1" <?php echo checked( wpcie_disable(), 1 ) ?>>
                        <p class="description"><?php esc_html_e('Note: This will disable the Import/Export menu in the frontend manager dashboard.', 'wpc-import-export' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Restrict user roles', 'wpc-import-export'); ?></th>
                    <td>
                        <select class="wpcie-select" name="wpcie_restricted_role[]" multiple="multiple" style="width:720px;">
                            <?php
                            if( !empty( $roles ) ){
                                foreach ($roles as $_key => $_value) {
                                    ?><option value="<?php echo $_key; ?>" <?php echo in_array( $_key, $rest_roles ) ? 'selected' : ''; ?> ><?php echo $_value; ?></option><?php
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Note: This will restrict selected roles to access the Import/Export page in the frontend manager dashboard.', 'wpc-import-export'); ?></p>
                    </td>
                </tr>
            </table>
            <input class="primary button-primary" type="submit" name="submit" value="<?php esc_html_e('Save Settings', 'wpc-import-export' ); ?>" />
        </form>
    </div>
</div>