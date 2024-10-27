<?php
/*
 * Display the content on the plugin settings page
 * */

if ( ! class_exists( 'Adsns_Settings_Tabs' ) ) {
    class Adsns_Settings_Tabs extends Bws_Settings_Tabs {

        private $adsns_client, $vi_revenue;

        /*
         * Constructor
         *
         * @access public
         *
         * @see Bws_Settings_Tabs::__constructor() for more information in default arguments.
         *
         * @param string $plugin_basename
         * */
        public function __construct( $plugin_basename ) {
            global $adsns_options, $adsns_plugin_info;

            $tabs = array(
                'settings'    => array( 'label' => __( 'Settings', 'adsense-plugin' ) ),
                'settings_vi'  => array( 'label' => __( 'VI Intelligence', 'adsense-plugin' ) ),
                'misc'        => array( 'label' => __( 'Misc', 'adsense-plugin' ) ),
                'custom_code' => array( 'label' => __( 'Custom Code', 'adsense-plugin' ) ),
                'license'     => array( 'label' => __( 'License Key', 'adsense-plugin' ) ),
            );

            parent::__construct( array(
                'plugin_basename'    => $plugin_basename,
                'plugins_info'        => $adsns_plugin_info,
                'prefix'             => 'adsns',
                'default_options'    => adsns_default_options(),
                'options'            => $adsns_options,
                'is_network_options' => is_network_admin(),
                'tabs'               => $tabs,
                'wp_slug'            => '',
                'pro_page'           => 'admin.php?page=adsense-pro.php',
                'bws_license_plugin' => 'adsense-pro/adsense-pro.php',
                'link_key'           => '2887beb5e9d5e26aebe6b7de9152ad1f',
                'link_pn'            => '80'
            ) );

            $this->vi_revenue = adsns_vi_get_revenue();

            $this->adsns_client = adsns_client();            

            add_filter( get_parent_class( $this ) . '_display_custom_messages', array( $this, 'display_custom_messages' ) );
        }

        /**
         * Display custom error\message\notice
         * @access public
         * @param  $save_results - array with error\message\notice
         * @return void
         */
        public function display_custom_messages( $save_results ) {
            if ( isset( $this->options['authorization_code'] ) ) {
                $this->adsns_client->setAccessToken( $this->options['authorization_code'] );
            }

            if ( $this->adsns_client->getAccessToken() ) {
                $adsns_adsense = new Google_Service_AdSense( $this->adsns_client );
                $adsns_adsense_accounts = $adsns_adsense->accounts;
                $adsns_adsense_adclients = $adsns_adsense->adclients;
                $adsns_adsense_adunits = $adsns_adsense->adunits;
                try {
                    $adsns_list_accounts = $adsns_adsense_accounts->listAccounts()->getItems();                    
                } catch ( Google_Service_Exception $e ) { 
                    $adsns_err = $e->getErrors(); ?>
                    <div class="error below-h2">
                        <p><?php printf( '<strong>%s</strong> %s %s',
                            __( 'Account Error:', 'adsense-plugin' ),
                            $adsns_err[0]['message'],
                            sprintf( __( 'Create account in %s', 'adsense-plugin' ), '<a href="https://www.google.com/adsense" target="_blank">Google AdSense.</a>' )
                        ); ?></p>
                    </div>
                <?php } catch ( Exception $e ) { ?>
                    <div class="error below-h2">
                        <p><strong><?php _e( 'Error', 'adsense-plugin' ); ?>:</strong> <?php echo $e->getMessage(); ?></p>
                    </div>
                <?php }
            }            
        }

        public function save_options() {           
            $message = $notice = $error = '';

            if ( isset( $_POST['adsns_logout'] ) ) {
                unset( $this->options['authorization_code'], $this->options['publisher_id'] );            
            } else {

                if ( ! empty( $_POST['adsns_authorization_code'] ) ) {
                    try {
                        $this->adsns_client->authenticate( sanitize_text_field( $_POST['adsns_authorization_code'] ) );
                        $this->options['authorization_code'] = sanitize_text_field( $this->adsns_client->getAccessToken() );

                        if ( isset( $this->options['authorization_code'] ) ) {
                            $this->adsns_client->setAccessToken( $this->options['authorization_code'] );
                        }

                        if ( $this->adsns_client->getAccessToken() ) {
                            $adsns_adsense = new Google_Service_AdSense( $this->adsns_client );
                            $adsns_adsense_accounts = $adsns_adsense->accounts;
                            $adsns_adsense_adclients = $adsns_adsense->adclients;
                            $adsns_adsense_adunits = $adsns_adsense->adunits;
                            try {
                                $adsns_list_accounts = $adsns_adsense_accounts->listAccounts()->getItems();

                                $this->options['publisher_id'] = $adsns_list_accounts[0]['id'];

                                adsns_vi_create_ads_file( 'google', adsns_vi_get_google_ads_file_content() );
                            } catch ( Exception $e ) {}
                        }
                    } catch ( Exception $e ) {}
                }

                if ( isset( $this->options['publisher_id'] ) ) {                    
                    $this->options['include_inactive_ads'] = ( isset( $_POST['adsns_include_inactive_id'] ) ) ? 1 : 0;
                }

                if ( isset( $_POST['adsns_authorization_code'] ) && isset( $_POST['adsns_authorize'] ) && ! $this->adsns_client->getAccessToken() ) {
                    $error .= __( 'Invalid authorization code. Please, try again.', 'adsense-plugin' );
                }                
            }

            update_option( 'adsns_options', $this->options );                
                
            $message = __( "Settings saved.", 'adsense-plugin' );

            return compact( 'message', 'notice', 'error' );
        }

        public function tab_settings() { ?>
            <h3 class="bws_tab_label"><?php _e( 'General Settings', 'adsense-plugin' ); ?></h3>
            <?php $this->help_phrase(); ?>
            <hr>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e( 'Remote work with Google AdSense', 'adsense-plugin' ); ?></th>
                    <td>
                        <?php if ( ! isset( $_POST['adsns_logout'] ) && $this->adsns_client->getAccessToken() ) { ?>
                            <div id="adsns_api_buttons">
                                <input class="button-secondary" name="adsns_logout" type="submit" value="<?php _e( 'Log out from Google AdSense', 'adsense-plugin' ); ?>" />
                            </div>
                        <?php } else {
                            $adsns_auth_url = $this->adsns_client->createAuthUrl(); ?>
                            <div id="adsns_authorization_notice">
                                <?php _e( "Please authorize via your Google Account to manage ad blocks.", 'adsense-plugin' ); ?>
                            </div>
                            <a id="adsns_authorization_button" class="button-primary" href="<?php echo $adsns_auth_url; ?>" target="_blank" onclick="window.open(this.href,'','top='+(screen.height/2-560/2)+',left='+(screen.width/2-640/2)+',width=640,height=560,resizable=0,scrollbars=0,menubar=0,toolbar=0,status=1,location=0').focus(); return false;"><?php _e( 'Get Authorization Code', 'adsense-plugin' ); ?></a>
                            <div id="adsns_authorization_form">
                                <input id="adsns_authorization_code" class="bws_no_bind_notice" name="adsns_authorization_code" type="text" autocomplete="off" maxlength="100">
                                <input id="adsns_authorize" class="button-primary" name="adsns_authorize" type="submit" value="<?php _e( 'Authorize', 'adsense-plugin' ); ?>">
                            </div>
                        <?php } ?>
                    </td>
                </tr>
                <?php if ( isset( $this->options['publisher_id'] ) ) { ?>
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Your Publisher ID', 'adsense-plugin' ); ?></th>
                        <td>
                            <span id="adsns_publisher_id"><?php echo $this->options['publisher_id']; ?></span>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Show idle ad blocks', 'adsense-plugin' ); ?></th>
                        <td>
                            <input id="adsns_include_inactive_id" type="checkbox" name="adsns_include_inactive_id" <?php checked( $this->options['include_inactive_ads'], 1 ); ?> value="1" />
                        </td>
                    </tr>
                    <?php if ( ! $this->hide_pro_tabs ) { ?>
                        </table>                        
                        <div class="bws_pro_version_bloc">
                            <div class="bws_pro_version_table_bloc">
                                <button type="submit" name="bws_hide_premium_options" class="notice-dismiss bws_hide_premium_options" title="<?php _e( 'Close', 'adsense-plugin' ); ?>"></button>
                                <div class="bws_table_bg"></div>
                                <table class="form-table bws_pro_version">                                    
                                    <tr valign="top">
                                        <th scope="row"><?php _e( 'Add HTML code in head', 'adsense-plugin' ); ?></th>
                                        <td>
                                            <textarea disabled="disabled" name="adsns_add_html" class="widefat" rows="8" style="font-family:Courier New;"></textarea>
                                            <p class="bws_info"><?php _e( 'Paste the code you provided when you created your AdSense account. This will add your code between the <head> and </head> tags.', 'adsense-plugin' ); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <?php $this->bws_pro_block_links(); ?>
                        </div>
                        <table class="form-table">
                    <?php } ?>
                <?php } ?>
            </table>                        
        <?php }

        public function tab_settings_vi() {
            global $adsns_vi_token, $adsns_vi_settings_api; ?>
            <h3 class="bws_tab_label"><?php _e( 'VI Intelligence Settings', 'adsense-plugin' ); ?></h3>
            <?php $this->help_phrase(); ?>
            <hr>
            <div class="bws_tab_sub_label"><?php _e( 'Overview', 'adsense-plugin' ); ?></div>
            <div class="adsns_vi_widget_header">
                <div class="adsns_vi_widget_header_content">
                    <div class="adsns_vi_widget_logo">
                        <img src="<?php echo plugins_url( 'images/vi_logo_white.svg', dirname( __FILE__ ) ); ?>" alt="video intelligence" title="video intelligence" />
                    </div>
                    <?php if ( ! $adsns_vi_token && ! $this->vi_revenue ) { ?>
                        <div class="adsns_vi_widget_title"><?php _e( 'Video content and video advertising – powered by video intelligence', 'adsense-plugin' ); ?></div>
                    <?php } else { ?>
                        <div class="adsns_vi_widget_title"><?php _e( 'vi stories - video content and video advertising', 'adsense-plugin' ); ?></div>
                    <?php } ?>
                </div>
            </div>
            <div class="adsns_vi_widget_body">
                <?php if ( ! $adsns_vi_token && ! $this->vi_revenue ) { ?>
                    <p>
                        <?php _e( 'Advertisers pay more for video advertising when it\'s matched with video content. This new video player will insert both on your page. It increases time on site, and commands a higher CPM than display advertising.', 'adsense-plugin' );
                        ?>
                    </p>
                    <p>
                        <?php _e( 'You\'ll see video content that is matched to your sites keywords straight away. A few days after activation you\'ll begin to receive revenue from advertising served before this video content.', 'adsense-plugin' ); ?>
                    </p>
                    <ul>
                        <li><?php _e( 'The set up takes only a few minutes', 'adsense-plugin' ); ?></li>
                        <li><?php _e( 'Up to 10x higher CPM than traditional display advertising', 'adsense-plugin' ); ?></li>
                        <li><?php _e( 'Users spend longer on your site thanks to professional video content', 'adsense-plugin' ); ?></li>
                        <li><?php _e( 'The video player is customizable to match your site', 'adsense-plugin' ); ?></li>
                    </ul>
                    <?php if ( ! empty( $adsns_vi_settings_api['demoPageURL'] ) ) { ?>
                        <p>
                            <?php printf( __( 'Watch a %s of how vi stories work.', 'adsense-plugin' ), sprintf( '<a href="%s" target="_blank">%s</a>', $adsns_vi_settings_api['demoPageURL'], __( 'demo', 'adsense-plugin' ) ) ); ?>
                        </p>
                    <?php }
                } else {
                    if ( ! isset( $this->vi_revenue['netRevenue'] ) || ! isset( $this->vi_revenue['mtdReport'] ) ) { ?>
                        <p class="adsns_revenue_api_error">
                            <?php _e( 'There was an error processing your request, our team was notified.', 'adsense-plugin' ); ?>
                        </p>
                        <p class="adsns_revenue_api_error">
                            <?php _e( 'Please try again later.', 'adsense-plugin' ); ?>
                        </p>
                    <?php } else { ?>
                        <p>
                            <?php _e( 'Below you can see your current revenues.', 'adsense-plugin' ); ?>
                        </p>
                        <p>
                            <?php printf( __( 'Don’t see anything? Consult the %s.', 'adsense-plugin' ), sprintf( '<a href="https://www.vi.ai/frequently-asked-questions-vi-stories-for-wordpress/?utm_source=WordPress&utm_medium=Plugin%%20FAQ&utm_campaign=WP%%20gas" target="_blank">%s</a>', __( 'FAQs', 'adsense-plugin' ) ) ); ?>
                        </p>
                        <div class="adsns_vi_revenue_content">
                            <div class="adsns_vi_revenue_earnings">
                                <div class="adsns_vi_revenue_title adsns_vi_revenue_earnings_title">
                                    <span class="adsns_vi_revenue_title_icon dashicons dashicons-welcome-write-blog"></span><?php _e( 'Total earnings', 'adsense-plugin' ); ?>
                                </div>
                                <div class="adsns_vi_revenue_earnings_value">$<?php echo number_format( ( $this->vi_revenue['netRevenue'] !== NULL ? $this->vi_revenue['netRevenue'] : 0 ), 2, '.', ' ' ); ?></div>
                            </div>
                            <div class="adsns_vi_revenue_chart">
                                <div class="adsns_vi_revenue_title adsns_vi_revenue_chart_title">
                                    <span class="adsns_vi_revenue_title_icon dashicons dashicons-chart-area"></span><?php _e( 'Chart', 'adsense-plugin' ); ?>
                                </div>
                                <div class="adsns_vi_revenue_chart_canvas_wrapper">
                                    <canvas id="adsns_vi_revenue_chart_canvas" width="250" height="130"></canvas>
                                    <noscript>
                                        <div class="adsns_vi_revenue_chart_canvas_no_js"><?php _e( 'Please enable JavaScript.', 'adsense-plugin' ); ?></div>
                                    </noscript>
                                </div>
                                <?php $this->vi_revenue_data = $this->vi_revenue['mtdReport'] !== NULL ? $this->vi_revenue['mtdReport'] : array();
                                $vi_chart_data = array(
                                    'labels' => array(),
                                    'data'   => array()
                                );

                                foreach ( $this->vi_revenue_data as $data ) {
                                    $vi_chart_data['labels'][] = date_i18n( 'M d', strtotime( $data['date'] ) );
                                    $vi_chart_data['data'][] = $data['revenue'];
                                }

                                $script = "(function($) {
                                        $(document).ready( function() {
                                            var $vi_chart_data = " . json_encode( $vi_chart_data ) . ";
                                            $('#adsns_vi_revenue_chart_canvas').trigger( 'displayWidgetChart', $vi_chart_data );
                                        } );
                                    })(jQuery);";

                                wp_register_script( 'adsns_vi_revenue_chart_canvas', '' );
                                wp_enqueue_script( 'adsns_vi_revenue_chart_canvas' );
                                wp_add_inline_script( 'adsns_vi_revenue_chart_canvas', sprintf( $script ) ); ?>
                            </div>
                            <div class="clear"></div>
                        </div>
                    <?php }
                } ?>
                <div class="adsns_vi_widget_footer">
                    <?php if ( ! $adsns_vi_token ) { ?>
                        <p><?php printf(
                            __( 'By clicking Sign Up button you agree to send current domain, email and affiliate ID to %s.', 'adsense-plugin' ),
                            sprintf( '<span>%s</span>', __( 'video intelligence', 'adsense-plugin' ) )
                        ); ?></p>
                        <div>
                            <a href="admin.php?page=adsense-plugin.php&action=vi_login" id="adsns_vi_widget_button_login" class="button button-secondary adsns_vi_widget_button"><?php _e( 'Log In', 'adsense-plugin' )?></a>
                            <a href="admin.php?page=adsense-plugin.php&action=vi_signup" id="adsns_vi_widget_button_signup" class="button button-primary adsns_vi_widget_button"><?php _e( 'Sign Up', 'adsense-plugin' )?></a>
                        </div>
                    <?php } else { ?>
                        <div>
                            <?php if ( ! empty( $adsns_vi_settings_api['dashboardURL'] ) ) { ?>
                                <a href="<?php echo $adsns_vi_settings_api['dashboardURL']; ?>" id="adsns_vi_widget_button_dashboard" class="button button-primary adsns_vi_widget_button" target="_blank"><?php _e( 'Publisher Dashboard', 'adsense-plugin' )?></a>
                            <?php } ?>
                            <button id="adsns_vi_widget_button_log_out" class="button button-secondary adsns_vi_widget_button" name="adsns_vi_logout" type="submit"><?php _e( 'Log Out', 'adsense-plugin' )?></button>
                        </div>
                    <?php } ?>
                </div>
            </div>            
        <?php }

        public function bws_pro_block_links() {
            global $wp_version; ?>
            <div class="bws_pro_version_tooltip">
                <a class="bws_button" href="<?php echo esc_url( 'https://bestwebsoft.com/products/wordpress/plugins/google-adsense/?k=' . $this->link_key . '&amp;pn=' . $this->link_pn . '&amp;v=' . $this->plugins_info["Version"] . '&amp;wp_v=' . $wp_version ); ?>" target="_blank" title="<?php echo $this->plugins_info["Name"]; ?>"><?php _e( 'Upgrade to Pro', 'bestwebsoft' ); ?></a>
                <div class="clear"></div>
            </div>
        <?php }
    }
}
