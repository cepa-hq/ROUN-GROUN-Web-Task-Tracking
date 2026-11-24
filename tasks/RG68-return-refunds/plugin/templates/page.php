<?php defined('ABSPATH') || exit;
/** RG-28: never call a classic theme header from a block-theme host. */
if(class_exists('RGHC_Shell')){RGHC_Shell::open();echo RGR_Centre::render();RGHC_Shell::close();}
elseif(function_exists('wp_is_block_theme')&&wp_is_block_theme()){
?><!doctype html><html <?php language_attributes();?>><head><meta charset="<?php bloginfo('charset');?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head();?></head><body <?php body_class();?>><?php wp_body_open();block_template_part('header');echo RGR_Centre::render();block_template_part('footer');wp_footer();?></body></html><?php
}else{get_header();echo RGR_Centre::render();get_footer();}
