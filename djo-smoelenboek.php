<?php
/*
 * Plugin Name: DJO Smoelenboek
 * Plugin URI: https://github.com/rmoesbergen/djo-smoelenboek
 * Description: Plugin voor de DJO smoelenboek koppeling met de ledenadministratie
 * Author: Ronald Moesbergen
 * Version: 0.0.1
 */

defined('ABSPATH') or die('Go away');

if (!class_exists('DJO_Smoelenboek')) {
  class DJO_Smoelenboek {

    private static $db = null;

    public static function init() {
      add_shortcode('djo_smoelenboek', array('DJO_Smoelenboek', 'smoelenboek'));
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

      register_setting( 'djo-smoelenboek', 'djo-smoelen-server', $options );
      register_setting( 'djo-smoelenboek', 'djo-smoelen-username', $options );
      register_setting( 'djo-smoelenboek', 'djo-smoelen-password', $options );
      register_setting( 'djo-smoelenboek', 'djo-smoelen-database', $options );
    }

    private static function db_connect() {
      if (! self::$db) {
        try {
          $db_host = get_option('djo-smoelen-server');
          $db_db = get_option('djo-smoelen-database');
          $db_user = get_option('djo-smoelen-username');
          $db_pass = get_option('djo-smoelen-password');

          self::$db = new PDO("mysql:host={$db_host};dbname={$db_db}", $db_user, $db_pass);
        } catch (Exception $exception) {
          return new WP_Error('admin_db_failed', $exception->getMessage());
        }
      }
      return self::$db;
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
        <th scope="row">Username</th>
        <td><input type="text" name="djo-smoelen-username" value="<?php echo esc_attr( get_option('djo-smoelen-username') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Password</th>
        <td><input type="text" name="djo-smoelen-password" value="<?php echo esc_attr( get_option('djo-smoelen-password') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Database</th>
        <td><input type="text" name="djo-smoelen-database" value="<?php echo esc_attr( get_option('djo-smoelen-database') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Server</th>
        <td><input type="text" name="djo-smoelen-server" value="<?php echo esc_attr( get_option('djo-smoelen-server') ); ?>" /></td>
        </tr>
      </table>
    <?php
      submit_button();
      echo '</form>';
      echo '</div>';
    }

    public static function smoelenboek($args) {
      $params = shortcode_atts(array('dag' => 'vr'), $args);
      $dag = $params['dag'];

      $db = DJO_Smoelenboek::db_connect();
      if (is_wp_error($db)) return $db->get_error_message();
      $query = "SELECT contact.id, contact.voornaam, contact.achternaam, dagdeel.dag " .
               "FROM contact " .
               "INNER JOIN contact_dagdeel cd " .
               "INNER JOIN dagdeel ".
               "ON (cd.contact_id = contact.id AND cd.dagdeel_id = dagdeel.id)" .
               "WHERE contact.eind_datum IS NULL ".
               "AND dagdeel.eind_datum IS NULL ".
               "AND dagdeel.dag = ? ".
               "ORDER by FIELD(type, 'bestuur', 'begeleider', 'aspirant_begeleider', 'senior', 'lid', 'lid,strippenkaart')";

      $stmt = $db->prepare($query);
      $results = $stmt->execute([$dag]);
      $output = "<div id='gallery-1' class='gallery gallery-columns-4 gallery-size-thumbnail'>\n";
      $counter = 1;
      while ($row = $stmt->fetch()) {
        $id = $row['id'];
        $voornaam = $row['voornaam'];

        $is_present = wp_remote_get('https://admin.djoamersfoort.nl/thumb.php?id='.$id);
        if (!is_wp_error($is_present) && $is_present['body'] == '1') {
          $output .= "<dl class='gallery-item'>\n";
          $output .= "<dt class='gallery-icon portrait'>\n";
          $output .= "<img width='100' height='150' src='https:\/\/admin.djoamersfoort.nl\/images\/contacten\/$id.jpg' class='attachment-thumbnail size-thumbnail' alt='' aria-describedby='gallery-1-$id' />\n";
          $output .= "</dt>\n";
          $output .= "<dd class='wp-caption-text gallery-caption' id='gallery-1-$id'>$voornaam</dd>\n";
          $output .= "</dl>\n";

          if ($counter++ == 4) {
            $counter = 1;
            $output .= '<br style="clear: both" />';
          }
        }
      }
      $output .= '<br style="clear: both"/></div>';

      return $output;
    }
  }

  DJO_Smoelenboek::init();
}
