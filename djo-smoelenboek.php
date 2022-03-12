<?php
/*
 * Plugin Name: DJO Smoelenboek
 * Plugin URI: https://github.com/rmoesbergen/djo-smoelenboek
 * Description: Plugin voor de DJO smoelenboek koppeling met de ledenadministratie
 * Author: Ronald Moesbergen
 * Version: 0.1.1
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
      </table>
      <?php
      submit_button();
      echo '</form>';
      echo '</div>';
    }

    public static function smoelenboek_user($args) {
      $params = shortcode_atts(array('userid' => 0, 'width' => 100, 'height' => 150), $args);
      $userid = $params['userid'];
      $width  = $params['width'];
      $height = $params['height'];

      if (get_current_user_id() == 0) { return; }
      if ($userid == 0) return "Please specify userid";

      $idp_access_token = get_user_meta(get_current_user_id(), 'woi_idp_access_token', true);
      $options = array('headers' => array('Authorization' => "Bearer $idp_access_token"));
      $url = get_option('djo-smoelen-url');
      $response = wp_remote_get("$url/$userid/", $options);

      if (is_array($response)) {
        $json = wp_remote_retrieve_body($response);
        $member = json_decode($json);
        $photo = $member->photo;

	    return "<img class='alignnone size-thumbnail' src='$photo' alt='' width='$width' height='$height' />";
      } else {
        return "Error receiving smoelenboek response: " . $response->get_error_message();
      }
    }

    private static function get_entries_by_type($smoelenboek, $dag, $types = DJO_Smoelenboek::TYPES_MEMBER) {
        $entries = array();
        foreach ($smoelenboek->{$dag} as $entry) {
            if (count(array_intersect($types, explode(',', $entry->types))) > 0) {
                array_push($entries, $entry);
            }
        }
        return $entries;
    }

    public static function smoelenboek($args) {
      $params = shortcode_atts(array('dag' => 'vrijdag', 'begeleider' => FALSE), $args);
      $dag = $params['dag'];
      $begeleider = $params['begeleider'];

      if (get_current_user_id() == 0) { return; }

      $idp_access_token = get_user_meta(get_current_user_id(), 'woi_idp_access_token', true);
      $options = array('headers' => array('Authorization' => "Bearer $idp_access_token"));
      $url = get_option('djo-smoelen-url');
      $response = wp_remote_get("$url/$dag/", $options);

      if (is_wp_error($response)) return "Error receiving smoelenboek response: " . $response->get_error_message();
      $json = wp_remote_retrieve_body($response);
      $smoelenboek = json_decode($json);
      if ($smoelenboek == null || !property_exists($smoelenboek, $dag)) {
        return "Geen toegang (meer) tot het smoelenboek, probeer ajb opnieuw in te loggen!";
      }

      $smoelenboek = DJO_Smoelenboek::get_entries_by_type($smoelenboek, $dag, $begeleider ? DJO_Smoelenboek::TYPES_MENTOR : DJO_Smoelenboek::TYPES_MEMBER);

      $output = "<div id='gallery-1' class='gallery gallery-columns-4 gallery-size-thumbnail'>\n";
      $counter = 1;
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

        if ($counter++ == 4) {
          $counter = 1;
          $output .= '<br style="clear: both" />';
        }
      }
      $output .= '<br style="clear: both"/></div>';

      return $output;
    }
  }

  DJO_Smoelenboek::init();
}
