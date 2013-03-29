<div class="bpa_images">
<?php $rel = md5(microtime() . rand());?>
<?php foreach ($images as $img) { ?>
	<?php if ( ! $img ) continue; ?>

    <?php $info = pathinfo($img);?>
    <?php $thumbnail = file_exists( bpa_get_image_dir($activity_blog_id) . $info['filename'] . '-bpat.' . strtolower($info['extension'])) ?
        bpa_get_image_url($activity_blog_id) . $info['filename'] . '-bpat.' . strtolower($info['extension'])
        :
        bpa_get_image_url($activity_blog_id) . $img
    ;
    ?>
    <a href="<?php echo bpa_get_image_url($activity_blog_id) . $img; ?>" class="<?php echo $use_thickbox; ?>" rel="<?php echo $rel;?>">
        <img src="<?php echo $thumbnail;?>" />
    </a>

<?php } ?>
</div>