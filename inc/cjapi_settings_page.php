<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

include_once 'cjapi_integrations.php';

if (!function_exists('cjapi_endsWith')) {
    function cjapi_endsWith($haystack, $needle)
    { // case insensitive version
        $expectedPosition = strlen($haystack) - strlen($needle);
        return strripos($haystack, $needle, 0) === $expectedPosition;
    }
}

function cjapi_enqueue_css()
{
    $pageData = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if ($pageData === 'cj-affiliate-tracking-settings') {
        wp_enqueue_style('cj_admin', plugins_url('/assets/cjapi-admin.css', CJAPI_PLUGIN_PATH . '/placeholder'), array(), CJAPI_PLUGIN_VERSION);
    }
}

add_action('admin_head', 'cjapi_enqueue_css');


function cjapi_create_toggle($setting, $isChecked, $yesno = false)
{
    ?>
    <!-- Provide a fallback that will be used when the toggle is unchecked (because unchecked checkboxes are not sent to the server) -->
    <!--<input type=hidden name="<?php echo esc_attr($setting) ?>" value='0' />-->

    <?php //echo var_export( filter_var($isChecked, FILTER_VALIDATE_BOOLEAN) );
    ?>
    <input type="checkbox" value='1'
           class="ow-toggle <?php echo esc_attr($yesno) ? 'ow-toggle-yes-no' : 'ow-toggle-on-off' ?> square-toggle"
           id="ow-toggle-<?php echo esc_attr($setting) ?>"
           name="<?php echo  esc_attr($setting) ?>" <?php echo esc_attr(filter_var($isChecked, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '') ?>>
    <label for="ow-toggle-<?php echo esc_attr($setting) ?>" class="ow-toggle-label"></label>
    <?php
}

class CJAPI_TrackingSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        $this->integrations_installed = cjapi_get_installed_integrations();
        $this->integrations_installed_and_enabled = cjapi_get_integrations();
        add_action( 'admin_menu', array( $this, 'cjapi_add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'cjapi_page_init' ) );
    }

    /**
     * Add options page
     */
    public function cjapi_add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'CJ Tracking Settings',
            'CJ Tracking',
            'manage_options',
            'cj-affiliate-tracking-settings',
            array($this, 'cjapi_create_admin_page')
        );
    }

    /**
     * Options page callback
     */
    public function cjapi_create_admin_page()
    {

        // cj_commit_rewrite_rules_for_proxy();

        // Set class property
        $this->options = get_option('cjapi_tracking_settings');
        ?>
        <style>
            .asterisk_input_enterprise_id::before {
                content: " *";
                color: #e32;
                position: absolute;
                margin: -3px 0px 0px -130px;
                font-size: x-large;
                padding: 0 5px 0 0;
            }

            .asterisk_input_at_id::before {
                content: " *";
                color: #e32;
                position: absolute;
                margin: -3px 0px 0px -157px;
                font-size: x-large;
                padding: 0 5px 0 0;
            }

            .asterisk_input_tag_id::before {
                content: " *";
                color: #e32;
                position: absolute;
                margin: -3px 0px 0px -175px;
                font-size: x-large;
                padding: 0 5px 0 0;
            }

            input::-webkit-outer-spin-button,
            input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            /* Firefox */
            input[type=number] {
                -moz-appearance: textfield;
            }
        </style>
        <script type="application/javascript">
            function validateCjForm(){
                let enterpriseId = document.getElementById('enterprise_id').value;
                let tagId = document.getElementById('tag_id').value;
                let actionId = document.getElementById('action_tracker_id').value;
                if(!enterpriseId || !tagId || !actionId){
                    alert("Enterprise ID, Action ID, Tag ID cannot be empty. Please enter valid details");
                    event.preventDefault();
                }

            }
        </script>


        <div class="wrap">
            <h1><?php echo esc_html_e('CJ Network Integration', 'cjtracking'); ?></h1>
            <form method="post" action="options.php" onsubmit="javascript: validateCjForm()">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'cjapi_tracking_settings_group' );
                do_settings_sections( 'cjapi-tracking-settings-page' );
                submit_button();
            ?>
            </form>

            <?php
            $enabled_integrations = cjapi_get_integrations();

 ?>


            

            <?php $ajax_nonce = wp_create_nonce( 'cj-tracking-feedback' ); ?>

            <script type="text/javascript" >
            jQuery('.email-form').submit(function() {
              event.preventDefault();
              var $form = jQuery(this)
              var data = {
                'security': '<?php echo esc_attr($ajax_nonce) ?>',
                'action': 'cj_tracking_contact_us',
                'message': $form.find('textarea').val() + "\n<br/><pre>" + $form.find('#debug-info').val() + '</pre>',
                'useremail': '<?php echo esc_attr(wp_get_current_user()->user_email) ?>'
              };

              // ajaxurl is always defined in the admin header and points to admin-ajax.php
              jQuery.post(ajaxurl, data, function(response) {
                if (response === 'success'){
                    $form.html('<p>'+$form.data('msg')+'</p>')
                } else {
                    console.log(response)
                    alert('There was an error');
                    throw new Exception('Sending message was not successful')
                }

            }).fail(function(xhr, status, err){
                console.log(status + ' ' + err);
                console.log(xhr);
                alert('There was an error');
            });

          });
          </script>


        </div>
        <?php
    }

    private function cjapi_get_theme_as_html(){
        $theme = wp_get_theme();
        $theme_info = array(
            'name' => $theme->get('Name'),
            'url' => $theme->get('ThemeURI'),
            'version' => $theme->get('Version')
        );

        $ret = '<table border="1"><tr><td>Name</td><td>URL</td><td>Version</td></tr><tr>';
        foreach($theme_info as $cell){
            $ret .= '<td>' . strip_tags($cell) . '</td>';
        }
        $ret .= '</tr></table>';

        return $ret;
    }

    private function cjapi_get_active_plugins_as_html(){
        $ret = '<table border="1"><tr><td>Name</td><td>Version</td><td>PluginURI</td><th>NetworkActive</td></tr>';
        $active = get_option('active_plugins');
        foreach($active as $plugin){
            $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
            $row = array(
                'Name' => $data['Name'],
                'Version' => $data['Version'],
                'PluginURI' => $data['PluginURI'],
                'NetworkActive' => is_plugin_active_for_network($plugin)
            );

            $ret .= '<tr>';
            foreach($row as $cell){
                $ret .= '<td>' . strip_tags($cell) . '</td>';
            }
            $ret .= '</tr>';

        }
        $ret .= "</table>";

        return $ret;
    }


    /**
     * Register and add settings
     */
    public function cjapi_page_init()
    {
        register_setting(
            'cjapi_tracking_settings_group', // Option group
            'cjapi_tracking_settings', // Option name
            array( $this, 'cjapi_sanitize' ) // Sanitize
        );

        add_settings_section(
            'cjapi_account_info_section', // ID
            '', // Title
            array( $this, 'cjapi_print_section_info' ), // Callback
            'cjapi-tracking-settings-page' // Page
        );

        add_settings_field(
            'cjapi-enterprise-id', // ID
            'Enterprise ID', // Title
            array($this, 'cjapi_enterprise_id_callback'), // Callback
            'cjapi-tracking-settings-page', // Page
            'cjapi_account_info_section', // Section
            array('label_for' => 'cjapi-enterprise-id')
        );

        add_settings_field(
            'cjapi-action_tracker_id', // ID
            'Action ID', // Title
            array($this, 'cjapi_action_tracker_id_callback'), // Callback
            'cjapi-tracking-settings-page', // Page
            'cjapi_account_info_section', // Section
            array('label_for' => 'cjapi-action_tracker_id')
        );

        add_settings_field(
            'cjapi_tag_id', // ID
            'Tag ID', // Title
            array($this, 'cjapi_tag_id_callback'), // Callback
            'cjapi-tracking-settings-page', // Page
            'cjapi_account_info_section', // Section
            array('label_for' => 'cjapi_tag_id')
        );


        /*add_settings_field(
            'uninstall', // ID
            'Uninstall', // Title
            array( $this, 'cjapi_uninstall_callback' ), // Callback
            'cjapi-tracking-settings-page', // Page
            'cjapi_account_info_section' // Section
        );*/


        add_settings_field(
            'cjapi_order_notes', // ID
            'Add debug info to order notes', // Title
            array( $this, 'cjapi_order_notes_callback' ), // Callback
            'cjapi-tracking-settings-page', // Page
            'advanced_section', // Section
            array('label_for'=>'cjapi_order_notes')
        );

        if (count($this->integrations_installed) > 1 ){
            add_settings_field(
                'cjapi_auto_detect_integrations', // ID
                'Turn on all available integrations', // Title
                array( $this, 'cjapi_auto_detect_integrations_callback' ), // Callback
                'cjapi-tracking-settings-page', // Page
                'advanced_section', // Section
                array('label_for'=>'cjapi_auto_detect_integration')
            );
            add_settings_field(
                'cjapi_enabled_integrations', // ID
                'Available integrations', // Title
                array( $this, 'cjapi_enable_integrations_callback' ), // Callback
                'cjapi-tracking-settings-page', // Page
                'advanced_section' // Section
            );
        }


        // add_settings_field(
        //     'other_params', // ID
        //     'Include additional params', // Title
        //     array( $this, 'other_params_callback' ), // Callback
        //     'cjapi-tracking-settings-page', // Page
        //     'advanced_section' // Section
        // );

       

    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function cjapi_sanitize( $input )
    {


        $new_input = array();

        if ( isset( $input['action_tracker_id'] ) )
            $new_input['action_tracker_id'] = sanitize_text_field( $input['action_tracker_id'] );

        if ( isset( $input['tag_id'] ) )
            $new_input['tag_id'] = sanitize_text_field( $input['tag_id'] );

        if ( isset( $input['cid'] ) )
            $new_input['cid'] = sanitize_text_field( $input['cid'] );

        if ( isset( $input['enterprise_id'] ) )
            $new_input['enterprise_id'] = sanitize_text_field( $input['enterprise_id'] );


        if ( isset( $input['order_notes'] ) )
            $new_input['order_notes'] = wp_validate_boolean( $input['order_notes'] );

        if ( isset( $input['other_params'] ) )
            $new_input['other_params'] = sanitize_textarea_field( $input['other_params'] );

        if ( isset( $input['storage_mechanism'] ) ){

                $new_input['storage_mechanism'] = 'cookies';
            
        }

        if ( isset( $input['cookie_duration'] ) )
            $new_input['cookie_duration'] = empty($input['cookie_duration']) ? $input['cookie_duration'] : (int)$input['cookie_duration'];


        $possible_options = array('report_all_fields', 'ignore_blank_fields', 'ignore_0_dollar_items');
        $new_input['blank_field_handling'] = isset($input['blank_field_handling']) && in_array($input['blank_field_handling'], $possible_options) ? $input['blank_field_handling'] : 'report_all_fields';

        $new_input['auto_detect_integrations'] = isset($input['auto_detect_integrations'])
                                                ? wp_validate_boolean($input['auto_detect_integrations'])
                                                /* default to true when we save the form and multiple integrations haven't been introduced yet,
                                                    so that as soon as a plugin that adds another integration is added we automatically start using it */
                                                : true;


        if ( count($this->integrations_installed) === 1 ){
            foreach ($this->integrations_installed as $integration){
                $url_friendly = $this->cjapi_js_make_url_friendly($integration);
                $new_input['integrations'][$url_friendly] = true;
            }
        }
        else if ( isset($input['integrations']) && is_array($input['integrations']) ){
            foreach ($this->integrations_installed as $integration){
                $url_friendly = $this->cjapi_js_make_url_friendly($integration);
                $new_input['integrations'][$url_friendly] = wp_validate_boolean( isset( $input['integrations'][$url_friendly] ) && $input['integrations'][$url_friendly] );
            }
        } else {
            foreach ($this->integrations_installed as $integration){
                $url_friendly = $this->cjapi_js_make_url_friendly($integration);
                $new_input['integrations'][$url_friendly] = false;
            }
        }

        return $new_input;
    }

    /**
     * Display the intro text
     */
    public function cjapi_print_section_info()
    {

    }

    /**
     * Display the Tag ID field
     */
    public function cjapi_tag_id_callback(){
        printf(
            '<span class="asterisk_input_tag_id">  </span><input type="number" onkeydown="javascript: return event.keyCode == 69 ? false : true" maxlength="21" oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);" id="tag_id" name="cjapi_tracking_settings[tag_id]"  value="%s" class="required" /><br/>
<label>A static number provided to you by CJ</label>',
            isset($this->options['tag_id']) ? esc_attr($this->options['tag_id']) : ''
        );
    }

    /**
     * Display the Action ID field
     */
    public function cjapi_action_tracker_id_callback()
    {
       
 
        printf(

            '<span class="asterisk_input_at_id">  </span><input type="number" onkeydown="javascript: return event.keyCode == 69 ? false : true" maxlength="21" oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);"  id="action_tracker_id" name="cjapi_tracking_settings[action_tracker_id]" value="%s" /><br/>
<label>A static number provided to you by CJ. In case you have multiple actions to be configured, contact CJ Client Integration team as it may need custom coding
</label>',
            isset($this->options['action_tracker_id']) ? esc_attr($this->options['action_tracker_id']) : ''
        );
    }

    /**
     * Display the tag CID field
     */
    public function cjapi_cid_callback()
    {
        printf(
            '<input type="text" id="cid" name="cjapi_tracking_settings[cid]" value="%s" />',
            isset( $this->options['cid'] ) ? esc_attr( $this->options['cid'] ) : ''
        );
    }

    /**
     * Display the Enterprise ID field
     */
    public function cjapi_enterprise_id_callback()
    {
        printf(
            '<span class="asterisk_input_enterprise_id"><input type="number"  onkeydown="javascript: return event.keyCode == 69 ? false : true" maxlength="21" oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);"  id="enterprise_id" name="cjapi_tracking_settings[enterprise_id]" value="%s" /><br/>
<label>A static number provided to you by CJ</label>',
            isset($this->options['enterprise_id']) ? esc_attr($this->options['enterprise_id']) : ''
        );
    }
    /**
     * Display the debug checkbox
     */
    public function cjapi_order_notes_callback()
    {
        printf(
            '<input type="checkbox" id="order_notes" name="cjapi_tracking_settings[order_notes]" value="true" %s />',
            isset( $this->options['order_notes'] ) ? 'checked="checked"' : ''
        );
    }






    public function cjapi_auto_detect_integrations_callback()
    {

        printf(
            '<input type="checkbox" class="ow-toggle ow-toggle-enable-disable" id="auto_detect_integration" name="cjapi_tracking_settings[auto_detect_integrations]" value="true" %s />
            <label for=auto_detect_integration class="ow-toggle-label"></label><br/>',
            checked( ! isset( $this->options['auto_detect_integrations'] ) || $this->options['auto_detect_integrations'], true, false)
        );

    }

    public function cjapi_enable_integrations_callback()
    {
        $installed_integrations = $this->integrations_installed;

        echo esc_html('<span id=cj-integrations-checkboxes>');
        foreach ($installed_integrations as $integration){
            $url_friendly = $this->cjapi_js_make_url_friendly($integration);
            $checked = checked( ! isset( $this->options['integrations'][$url_friendly] ) || $this->options['integrations'][$url_friendly], true, false);
            echo esc_html("<input type='checkbox' id='integration_$url_friendly' name='cjapi_tracking_settings[integrations][$url_friendly]' value='true' $checked />");
            echo esc_html("<label for='integration_$url_friendly'>$integration — " . CJAPI_INTEGRATION_DESCRIPTIONS[$integration] . "</label><br/>");
        }
        echo esc_html('</span>');

        echo esc_html('<span id=cj-integrations-checkboxes-auto-detected>');
        foreach($installed_integrations as $integration){
            echo esc_html('<input type=checkbox style="visibility:hidden" />');
            echo esc_html("<label>$integration — " . CJAPI_INTEGRATION_DESCRIPTIONS[$integration] . "</label><br/>");
        }
        echo esc_html('</span>');

        ?>
        <script>
            document.getElementById('cj-integrations-checkboxes').style.display = 'none';
            function toggleAutoDetectIntegration(){
                document.getElementById('cj-integrations-checkboxes').style.display = this.checked ? 'none' : 'inline';
                document.getElementById('cj-integrations-checkboxes-auto-detected').style.display = (! this.checked) ? 'none' : 'inline';
            }
            document.getElementById('auto_detect_integration').addEventListener('change', toggleAutoDetectIntegration)
            if ( ! document.getElementById('auto_detect_integration').checked){
                toggleAutoDetectIntegration()
            }


            function hide_woocommerce_settings_when_disabled(){
                if ( ! jQuery('#auto_detect_integration').is(':checked') && ! jQuery('#integration_woocommerce').is(':checked') ){
                    document.getElementById('storage_mechanism').parentNode.parentNode.style.display = 'none'
                } else {
                    document.getElementById('storage_mechanism').parentNode.parentNode.style.display = ''
                }
            }
            jQuery('#auto_detect_integration, #integration_woocommerce').change(hide_woocommerce_settings_when_disabled)
        </script>
        <?php
    }


    public function cjapi_uninstall_callback($input){
        ?>
        <p aria-labelledby=uninstall-btn>Before deleting this plugin, use the button below to remove any settings that where stored in the database</p><br/>
        <button id=uninstall-btn>Remove Plugin Data</button>
        </form>
        <script>
            document.getElementById('uninstall-btn').addEventListener('click', function(ev){
                ev.preventDefault()
                if (confirm('This will delete the plugin settings. Are you sure?')){
                    var data = {
                        'action': 'cjapi_tracking_uninstall',
                        'nonce': "<?php echo wp_create_nonce( 'cj-tracking-uninstall' ) ?>"
                    };
                    jQuery.post(ajaxurl, data, function(response) {
                        if (response.includes('success')){
                            document.getElementById('wpbody-content').innerHTML = ''
                        }
                        setTimeout(function(){
                            alert(response)
                            document.getElementById('wpbody-content').innerHTML = '<br/> <h3>Reloading page...</h3>'
                            location.reload()
                        }, 0);
                    })
                }
            })
        </script>
        <?php
    }

    public function cjapi_blank_field_handling_callback(){
        printf(
            '<p>If you are conditionally showing/hiding fields, you may choose to try out one of the following options to not send everything to CJ.</p><br/>' .
            '<select id="cj-blank-field-handling" name="cjapi_tracking_settings[blank_field_handling]" />' .
            '<option value=report_all_fields %s>Report all pricing fields (default)</option>' .
            '<option value=ignore_blank_fields %s>Ignore $0 fields that were not filled out</option>' .
            '<option value=ignore_0_dollar_items %s>Ignore all $0 fields</option>',
            ( ! isset( $this->options['blank_field_handling'] ) || $this->options['blank_field_handling'] === 'report_all_fields' ) ? 'selected' : '',
            ( isset( $this->options['blank_field_handling'] ) && $this->options['blank_field_handling'] === 'ignore_blank_fields' ) ? 'selected' : '',
            ( isset( $this->options['blank_field_handling'] ) && $this->options['blank_field_handling'] === 'ignore_0_dollar_items' ) ? 'selected' : ''
        );
    }

    private function cjapi_js_make_url_friendly($input){
        return cjapi_make_url_friendly($input);
    }

} /* end of CJAPI_TrackingSettingsPage class */

$my_settings_page = new CJAPI_TrackingSettingsPage();