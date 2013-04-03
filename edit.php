<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

class BP_Active_Editor
{
    public function __construct() {
        add_action( 'wp', array ( &$this, 'maybe_route_edit' ), 2 );
        add_filter( 'bp_located_template', array ( &$this, 'add_template' ), 10, 2 );
    }

    public function maybe_route_edit() {
        global $bp;

        // Many of these checks will be performed by the plugin as well,
        // but will be run only in the save action, so no real performace
        // problem.
        if ( !is_user_logged_in()
                || !bp_is_activity_component()
                || !bp_is_current_action( 'edit' )
                || ! isset ( $_POST['save_changes'] )
                || ! ( $activity_edit_template = bp_activity_get_specific( array( 'activity_ids' => $bp->action_variables[0] ) ) )
                || ! ( $activity = $activity_edit_template['activities'][0] )
                || ( ! $bp->loggedin_user->is_super_admin && $activity->user_id != $bp->loggedin_user->id )
                || ! etivite_bp_edit_activity_check_date_recorded( $activity->date_recorded ) )
            return;

        check_admin_referer( 'etivite_bp_edit_activity_post'. $activity->id );

        // Saving!
        $data = json_decode ( stripslashes($_POST['data']), true );
        // Get existing image data
        $curr_data = BP_Active::get( $activity->id );
        if ( isset ( $curr_data['images'] ) && ! empty ( $curr_data['images'] ) ) {
            $data['images'] = array_merge($data['images'], $curr_data['images']);
        }

        $bpa_obj = BP_Active::init();
        $bpa_obj->save($data,$activity->id);

        // Fix a bug in plugin
        if ( $bp->loggedin_user->is_super_admin
                && ( ! isset ( $_POST['activity_action'] ) || empty ( $_POST['activity_action'] ) ) )
            $_POST['activity_action'] = $activity->action;
    }

    public function add_template($located,$template_paths) {
        foreach ( $template_paths as $template_path ) {
            if ( substr($template_path,-26) == 'activity/activity-edit.php' ) {
                $located = BP_ACTIVE_TEMPLATES . 'activity-edit.php';
                break;
            }
        }
        return $located;

    }

}
new BP_Active_Editor;