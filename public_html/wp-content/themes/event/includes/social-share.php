<?php
/**
 * Template to display social share buttons.
 * @since 1.0.0
 */

if ( Themify_Social_Share::is_enabled( 'single' ) || Themify_Social_Share::is_enabled( 'archive' ) ) :
?>
<?php $networks = Themify_Social_Share::get_active_networks();?>
<?php if(!empty($networks)):?>
<div class="post-share sharrre">
	<div class="box">
            <a href="javascript:void(0);"  class="share"><?php _e('Share','themify')?></a>
	</div>
	<div class="social-share clearfix">
		<?php foreach($networks as $k=>$n):?>
			<div class="<?php echo strtolower($k)?>-share sharrre">
				<a onclick="window.open('<?php echo Themify_Social_Share::get_network_url($k)?>','<?php echo $k?>','<?php echo Themify_Social_Share::get_window_params($k)?>')" title="<?php esc_attr_e($n)?>" rel="nofollow" href="javascript:void(0);" class="share"><span></span></a>
			</div>
		<?php endforeach;?>
	</div>
</div>
<?php endif;?>

<!-- .post-share -->

<?php endif; // social share enabled in archive or single ?>