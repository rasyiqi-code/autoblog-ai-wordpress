<?php
namespace Autoblog\License;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AgencyOS License Checker for Autoblog AI.
 * Adapted from AgencyOS License Server template.
 */
class AgencyOS_License_Checker {
    // AgencyOS URL (Auto-detected)
    private $api_url = 'https://crediblemark.com/api/public/verify-license'; 
    private $product_slug;
    private $option_name;

    public function __construct( $product_slug ) {
        $this->product_slug = $product_slug;
        $this->option_name = 'agencyos_license_' . $product_slug;

        add_action( 'admin_menu', [ $this, 'register_license_menu' ], 20 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        
        // Daily check or on update
        if ( ! get_transient( $this->option_name . '_check' ) ) {
            $this->validate_license();
        }
    }

    public function register_license_menu() {
        // Add as a submenu of Autoblog AI instead of a separate theme page
        add_submenu_page(
            'autoblog', // Parent slug
            'Autoblog License',
            'License Activation',
            'manage_options',
            $this->product_slug . '-license',
            [ $this, 'render_license_page' ]
        );
    }

    public function register_settings() {
        register_setting( $this->product_slug . '_license_group', $this->option_name );
    }

    public function render_license_page() {
        $license_key = get_option( $this->option_name );
        $status = get_option( $this->option_name . '_status' );
        $domain = parse_url( site_url(), PHP_URL_HOST );
        ?>
        <div class="wrap autoblog-license-wrapper">
            <style>
                .autoblog-license-wrapper {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    max-width: 600px;
                    margin: 40px auto;
                }
                .autoblog-license-header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .autoblog-license-header h1 {
                    font-size: 28px;
                    font-weight: 700;
                    color: #1e293b;
                    margin-bottom: 10px;
                    border: none;
                }
                .autoblog-license-header p {
                    font-size: 16px;
                    color: #64748b;
                }
                .autoblog-license-card {
                    background: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08), 0 2px 10px rgba(0,0,0,0.04);
                    padding: 40px;
                    border: 1px solid rgba(0,0,0,0.05);
                }
                .autoblog-form-group {
                    margin-bottom: 25px;
                }
                .autoblog-form-group label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 10px;
                    color: #334155;
                    font-size: 14px;
                }
                .autoblog-license-input {
                    width: 100%;
                    padding: 14px 18px;
                    font-size: 16px;
                    border: 2px solid #e2e8f0;
                    border-radius: 10px;
                    background-color: #f8fafc;
                    transition: all 0.3s ease;
                    box-sizing: border-box;
                    font-family: monospace;
                    letter-spacing: 1px;
                    color: #0f172a;
                }
                .autoblog-license-input:focus {
                    border-color: #3b82f6;
                    background-color: #ffffff;
                    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
                    outline: none;
                }
                /* Status Badge Styles */
                .autoblog-status-container {
                    display: flex;
                    align-items: center;
                    margin-bottom: 30px;
                    background: #f8fafc;
                    padding: 16px 20px;
                    border-radius: 10px;
                    border: 1px solid #e2e8f0;
                }
                .autoblog-status-label {
                    font-weight: 600;
                    margin-right: auto;
                    color: #475569;
                    font-size: 14px;
                }
                .autoblog-badge {
                    display: inline-flex;
                    align-items: center;
                    padding: 6px 14px;
                    border-radius: 20px;
                    font-weight: 700;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .autoblog-badge.active {
                    background: rgba(16, 185, 129, 0.1);
                    color: #10b981;
                    border: 1px solid rgba(16, 185, 129, 0.2);
                }
                .autoblog-badge.inactive {
                    background: rgba(239, 68, 68, 0.1);
                    color: #ef4444;
                    border: 1px solid rgba(239, 68, 68, 0.2);
                }
                .autoblog-badge svg {
                    width: 16px;
                    height: 16px;
                    margin-right: 6px;
                }
                
                .autoblog-btn-primary {
                    display: block;
                    width: 100%;
                    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                    color: white;
                    border: none;
                    padding: 16px 24px;
                    font-size: 16px;
                    font-weight: 600;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
                    text-align: center;
                }
                .autoblog-btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
                    background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
                    color: white;
                }
                .autoblog-btn-primary:active {
                    transform: translateY(1px);
                    box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
                }
                
                .autoblog-note {
                    display: flex;
                    align-items: flex-start;
                    margin-top: 30px;
                    padding: 18px;
                    background: rgba(59, 130, 246, 0.05);
                    border-radius: 10px;
                    border: 1px solid rgba(59, 130, 246, 0.15);
                    color: #1e293b;
                    font-size: 13.5px;
                    line-height: 1.6;
                }
                .autoblog-note svg {
                    flex-shrink: 0;
                    width: 22px;
                    height: 22px;
                    color: #3b82f6;
                    margin-right: 14px;
                    margin-top: 1px;
                }
                .autoblog-note code {
                    background: #e2e8f0;
                    padding: 3px 6px;
                    border-radius: 5px;
                    color: #0f172a;
                    font-size: 13px;
                }
            </style>

            <div class="autoblog-license-header">
                <h1>Autoblog AI License</h1>
                <p>Unlock premium features and AI generative pipelines</p>
            </div>
            
            <div class="autoblog-license-card">
                <form method="post" action="options.php">
                    <?php settings_fields( $this->product_slug . '_license_group' ); ?>
                    <?php do_settings_sections( $this->product_slug . '_license_group' ); ?>
                    
                    <div class="autoblog-status-container">
                        <span class="autoblog-status-label">Current Status</span>
                        <?php if ( $status === 'active' ) : ?>
                            <div class="autoblog-badge active">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Active
                            </div>
                        <?php else : ?>
                            <div class="autoblog-badge inactive">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Inactive
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="autoblog-form-group">
                        <label for="autoblog_license_key">License Key</label>
                        <input type="text" 
                               id="autoblog_license_key"
                               name="<?php echo esc_attr( $this->option_name ); ?>" 
                               value="<?php echo esc_attr( $license_key ); ?>" 
                               class="autoblog-license-input" 
                               placeholder="KEY-XXXX-XXXX-XXXX" 
                               autocomplete="off" />
                    </div>
                    
                    <button type="submit" name="submit" class="autoblog-btn-primary">
                        Save & Activate License
                    </button>
                    
                </form>

                <div class="autoblog-note">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <strong>Note:</strong> License verification requires an active internet connection to communicate with <code>crediblemark.com</code>. Your license is securely tied to this domain (<strong><?php echo esc_html( $domain ); ?></strong>).
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function validate_license() {
        $key = get_option( $this->option_name );
        if ( empty( $key ) ) {
            update_option( $this->option_name . '_status', 'inactive' );
            return;
        }

        $domain = parse_url( site_url(), PHP_URL_HOST );

        $response = wp_remote_post( $this->api_url, [
            'body' => json_encode([
                'key' => $key,
                'productSlug' => $this->product_slug,
                'device' => $domain
            ]),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 15
        ]);

        if ( is_wp_error( $response ) ) return;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['valid'] ) && $body['valid'] === true ) {
            update_option( $this->option_name . '_status', 'active' );
            set_transient( $this->option_name . '_check', 'valid', DAY_IN_SECONDS );
        } else {
            update_option( $this->option_name . '_status', 'invalid' );
            delete_transient( $this->option_name . '_check' );
        }
    }

    public function is_active() {
        return get_option( $this->option_name . '_status' ) === 'active';
    }
}
