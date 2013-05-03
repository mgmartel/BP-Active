<?php
// We all do things we're not proud of sometimes
$bpa_obj = BP_Active::init();
$bpa_data = BP_Active::get( etivite_bp_edit_get_the_activity_id() );
$link = $embed = $images = false;

if ( ! empty ( $bpa_data ) ) {
    if ( isset ( $bpa_data['link'] ) && ! empty ( $bpa_data['link'] ) ) {

        if ( isset ( $bpa_data['link']['embed'] ) && ! empty ( $bpa_data['link']['embed'] ) ) {
            $embed = $bpa_data['link']['embed'];
            unset($bpa_data['link']['embed']);
        }
        if ( ! empty ( $bpa_data['link'] ) )
            $link = $bpa_data['link']['url'];

    }
    if ( isset ( $bpa_data['images'] ) && ! empty ( $bpa_data['images'] ) ) {
        $images = $bpa_data['images'];
        $bpa_obj->max_images -= count ( $bpa_data['images'] );
    }
}
?>

<?php get_header( 'buddypress' ); ?>

	<div id="content">
		<div class="padder">

<div class="activity no-ajax">

	<ul id="activity-stream" class="activity-list item-list">
		<li id="activity-1" class="activity activity_update">

		<?php do_action( 'bp_before_edit_activity_edit_form' ) ?>

		<?php if ( etivite_bp_edit_the_activity() ) : ?>
			<form action="<?php etivite_bp_edit_action() ?>" method="post" id="whats-new-form" class="standard-form">

				<div class="activity-avatar">
					<?php etivite_bp_edit_the_avatar( 'type=full&width=100&height=100' ); ?>
				</div>

				<div class="activity-content">
					<h3><?php _e( 'Edit Activity', 'bp-activity-edit' ) ?></h3>

					<div class="activity-header">
						<?php if ( false && is_super_admin() ) : ?>
							<label for="activity_action"><?php _e( 'Action:', 'bp-activity-edit' ) ?></label>
							<input type="text" name="activity_action" id="activity_action" value="<?php etivite_bp_edit_the_activity_action() ?>" />
                        <?php else: ?>
                            <?php echo $GLOBALS['activity_edit_template']['activities'][0]->action; ?>
						<?php endif; ?>
					</div>
					<div class="activity-inner">
						<label for="activity_content"><?php _e( 'Content:', 'bp-activity-edit' ) ?></label>
						<textarea name="activity_content" id="whats-new"><?php etivite_bp_edit_the_activity_content() ?></textarea>
					</div>
                    <div class="activity-meta">
					</div>
                    <div id="whats-new-options">
                        <input type="hidden" name="data" id="bpa_hidden_data" value=''>
                        <div id="whats-new-submit">
                            <input type="submit" name="save_changes" id="save_changes" value="<?php _e( 'Save Changes', 'bp-activity-edit' ) ?>" />
                        </div>
                    </div>

                    <?php if ( $images ) : ?>
                        <div id="existing-images">
                            <h3><?php _e('Previously attached','bp-active') ?></h3>
                            <?php $bpa_display_obj = new BP_Active_Display;
                            $bpa_display_obj->show_images( $images, etivite_bp_edit_get_the_activity_id() ); ?>
                        </div>
                    <?php endif; ?>

				</div>

				<?php do_action( 'etivite_bp_edit_activity_edit_form' ) ?>

				<?php wp_nonce_field( 'etivite_bp_edit_activity_post'. etivite_bp_edit_get_the_activity_id() ) ?>
			</form><!-- #forum-topic-form -->

		<?php else : ?>
			<div id="message" class="info">
				<p><?php _e( 'This activity does not exist.', 'bp-activity-edit' ) ?></p>
			</div>
		<?php endif;?>
		</li>
	</ul>

</div>
        </div>
    </div>

<script>
    (function($){

        $(document).ready(function() {
            <?php if ( $link || $embed ) : ?>
                var linkHandler = bpA.handler.link;

                <?php if ( $link ) : ?>
                    linkHandler.setLink('<?php echo $link; ?>');
                <?php endif; ?>

                <?php if ($embed ) : ?>
                    linkHandler.setEmbed('<?php echo $embed; ?>');
                <?php endif; ?>

            <?php endif; ?>

            $("#whats-new-form").on("submit",function(e) {
                $("#bpa_hidden_data").val(JSON.stringify(bpA.getAll()));
                $(this).find("#save_changes").addClass("loading");
            });
            $("textarea#whats-new").trigger('focus');
        });
    })(jQuery);
</script>
<style>
    .activity-list .activity-content {
        margin-left: 120px;
    }
    .activity-list .activity-content .activity-inner {
        overflow: visible;
    }
</style>

<?php get_footer() ?>
