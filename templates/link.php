<div class="liveurl">
    <div class="inner">
        <?php if ( isset ( $image ) && ! empty ( $image ) ) : ?>
            <div class="image"><img src='<?php echo $image ?>'></div>
        <?php endif; ?>

        <div class="details">
            <div class="info">
                <div class="title"><a href="<?php echo $url; ?>"><?php echo $title ?></a></div>
                <div class="description"><?php echo $description ?></div>
                <?php if ( $title != $url  ) : ?>
                    <div class="url"><a href="<?php echo $url; ?>"><?php echo $url ?></a></div>
                <?php endif; ?>
            </div>

            <div class="video"></div>
        </div>

    </div>
</div>