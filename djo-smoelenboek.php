<?php
/*
 * Plugin Name: DJO Smoelenboek
 * Plugin URI: https://github.com/rmoesbergen/djo-smoelenboek
 * Description: Plugin voor de DJO smoelenboek koppeling met de ledenadministratie
 * Author: Ronald Moesbergen
 * Version: 0.3.0
 */

defined('ABSPATH') or die('Go away');

if (!class_exists('DJO_Smoelenboek')) {
  class DJO_Smoelenboek {

    private const TYPES_MEMBER = array('member','strippenkaart','senior');
    private const TYPES_MENTOR = array('begeleider', 'bestuur', 'aspirant');

    public static function init() {
      add_shortcode('djo_smoelenboek', array('DJO_Smoelenboek', 'smoelenboek'));
      add_shortcode('djo_smoelenboek_user', array('DJO_Smoelenboek', 'smoelenboek_user'));
      if ( is_admin() ) {
        add_action( 'admin_init', array('DJO_Smoelenboek', 'register_settings' ));
        add_action( 'admin_menu', array('DJO_Smoelenboek', 'adminmenu'));
      }
    }

    public static function register_settings() {
      $options = array(
        'type' => 'string',
        'default' => ''
      );

      register_setting( 'djo-smoelenboek', 'djo-smoelen-url', $options );
      register_setting( 'djo-smoelenboek', 'djo-smoelen-client-id', $options);
      register_setting( 'djo-smoelenboek', 'djo-smoelen-client-secret', $options);
      register_setting( 'djo-smoelenboek', 'djo-smoelen-token-url', $options);
    }

    public static function adminmenu() {
      add_options_page( 'DJO Smoelenboek settings', 'DJO Smoelenboek', 'manage_options', 'DJO_Smoelenboek', array('DJO_Smoelenboek', 'admin_options') );
    }

    public static function admin_options() {
      if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
      }
      echo '<div class="wrap">';
      echo '<h1>DJO Smoelenboek instellingen</h1>';
      echo '<form method="post" action="options.php">';
      settings_fields( 'djo-smoelenboek' );
      do_settings_sections( 'djo-smoelenboek' ); ?>

      <table class="form-table">
        <tr valign="top">
        <th scope="row">Ledenadministratie Smoelenboek API URL</th>
        <td><input type="text" name="djo-smoelen-url" value="<?php echo esc_attr( get_option('djo-smoelen-url') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">OAuth Token Endpoint URL</th>
        <td><input type="text" name="djo-smoelen-token-url" value="<?php echo esc_attr( get_option('djo-smoelen-token-url') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">OAuth Client ID</th>
        <td><input type="text" name="djo-smoelen-client-id" value="<?php echo esc_attr( get_option('djo-smoelen-client-id') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">OAuth Client Secret</th>
        <td><input type="text" name="djo-smoelen-client-secret" value="<?php echo esc_attr( get_option('djo-smoelen-client-secret') ); ?>" /></td>
        </tr>
      </table>
      <?php
      submit_button();
      echo '</form>';
      echo '</div>';
    }

    public static function get_access_token() {
      $access_token = get_transient('djo-smoelenboek-access-token');
      if ($access_token) return $access_token;

      // No token in cache -> get a new one
      $client_id = get_option('djo-smoelen-client-id');
      $client_secret = get_option('djo-smoelen-client-secret');
      $token_url = get_option('djo-smoelen-token-url');

      $options = array(
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( "${client_id}:${client_secret}" ),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body' => 'grant_type=client_credentials',
            );
      $token_response = wp_remote_get($token_url, $options);
      if (is_wp_error($token_response)) return $token_response;

      $token = json_decode(wp_remote_retrieve_body($token_response));
      // Cache the new token
      set_transient('djo-smoelenboek-access-token', $token->access_token, $token->expires_in);
      return $token->access_token;
    }


    public static function api_request($url) {
      $token = DJO_Smoelenboek::get_access_token();
      if (is_wp_error($token)) return $token;

      $options = array('headers' => array('Authorization' => "Bearer $token"));
      $response = wp_remote_get("$url/$userid/", $options);
      if (is_wp_error($response)) return $response;

      return json_decode(wp_remote_retrieve_body($response));
    }

    public static function smoelenboek_user($args) {
      $params = shortcode_atts(array('userid' => 0, 'width' => 100, 'height' => 150), $args);
      $userid = $params['userid'];
      $width  = $params['width'];
      $height = $params['height'];

      if ($userid == 0) return "Please specify userid";

      $url = get_option('djo-smoelen-url');
      $member = DJO_Smoelenboek::api_request("$url/$userid/");
      if (is_wp_error($member))
        return "Error receiving smoelenboek response: " . $member->get_error_message();

      $photo = $member->photo;

      return "<img class='alignnone size-thumbnail' src='$photo' alt='' width='$width' height='$height' />";
    }

    private static function get_entries_by_type($smoelenboek, $types = DJO_Smoelenboek::TYPES_MEMBER) {
        $entries = array();
        foreach ($smoelenboek as $entry) {
          if (count(array_intersect($types, explode(',', $entry->types))) > 0) {
            array_push($entries, $entry);
          }
        }
        return $entries;
    }

    public static function smoelenboek($args) {
      $params = shortcode_atts(array('begeleider' => FALSE), $args);
      $begeleider = $params['begeleider'];

      if (get_current_user_id() == 0) { return; }

      $url = get_option('djo-smoelen-url');
      $smoelenboek = DJO_Smoelenboek::api_request("$url/");

      if (is_wp_error($smoelenboek))
        return "Error receiving smoelenboek response: " . $smoelenboek->get_error_message();
      if (!$smoelenboek)
        return "Geen toegang (meer) tot het smoelenboek, probeer ajb opnieuw in te loggen!";

      $smoelenboek = DJO_Smoelenboek::get_entries_by_type($smoelenboek, $begeleider ? DJO_Smoelenboek::TYPES_MENTOR : DJO_Smoelenboek::TYPES_MEMBER);

      $output = "<div id='gallery-1' class='gallery gallery-columns-4 gallery-size-thumbnail'>\n";
      foreach ($smoelenboek as $row) {
        $id = $row->id;
        $voornaam = $row->first_name;
        $imgurl = $row->photo;

        $output .= "<dl class='gallery-item'>\n";
        $output .= "<dt class='gallery-icon portrait'>\n";
        $output .= "<img width='100' height='150' src='$imgurl' class='attachment-thumbnail size-thumbnail' alt='' />\n";
        $output .= "</dt>\n";
        $output .= "<dd class='wp-caption-text gallery-caption' id='gallery-1-$id'>$voornaam</dd>\n";
        $output .= "</dl>\n";
      }
      $output .= '<br style="clear: both"/></div>';

      return $output;
    }
  }

  DJO_Smoelenboek::init();
}
